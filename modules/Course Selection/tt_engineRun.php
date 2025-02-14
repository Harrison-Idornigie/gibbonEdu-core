<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Timetable;

use CourseSelection\BackgroundProcess;
use Gibbon\Domain\System\SettingGateway;
use CourseSelection\Domain\TimetableGateway;
use CourseSelection\Domain\SettingsGateway;
use Illuminate\Support\Collection;

include '../../gibbon.php';

// Module Bootstrap
require 'module.php';

// Cancel out now if we're not running via CLI
if (!isCommandLine()) {
    die( __('This script cannot be run from a browser, only via CLI.') );
}

// Setup default settings
ini_set('memory_limit', '2048M');
ini_set('max_execution_time', 900);
set_time_limit(900);
getSystemSettings($guid, $connection2);
setCurrentSchoolYear($guid, $connection2);

// Incoming variables from command line
$gibbonSchoolYearID = (isset($argv[1]))? $argv[1] : null ;

$processor = new BackgroundProcess($session->get('absolutePath').'/uploads/engine');
$timetableGateway = $container->get('CourseSelection\Domain\TimetableGateway');
$settingsGateway = $container->get('CourseSelection\Domain\SettingsGateway');

// Build a set of class information for the school year
$classResults = $timetableGateway->selectTimetabledClassesBySchoolYear($gibbonSchoolYearID);
$classData = ($classResults && $classResults->rowCount() > 0)? $classResults->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE) : array();

// Build a set of students information
$settingGateway = $container->get(SettingGateway::class);
$studentOrder = $settingGateway->getSettingByScope('Course Selection', 'studentOrder');
$studentResults = $timetableGateway->selectApprovedStudentsBySchoolYear($gibbonSchoolYearID, $studentOrder);
$studentData = ($studentResults && $studentResults->rowCount() > 0)? $studentResults->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE) : array();

$enrolmentsResults = $timetableGateway->selectCourseEnrolmentsBySchoolYear($gibbonSchoolYearID);
$enrolmentsData = ($enrolmentsResults && $enrolmentsResults->rowCount() > 0)? $enrolmentsResults->fetchAll(\PDO::FETCH_GROUP) : array();

$selectionsResults = $timetableGateway->selectApprovedCourseSelectionsBySchoolYear($gibbonSchoolYearID);
$selectionsData = ($selectionsResults && $selectionsResults->rowCount() > 0)? $selectionsResults->fetchAll(\PDO::FETCH_GROUP) : array();



// TESTING!
// $studentData = collect($studentData)->filter(function($value, $key) {
//     return $key == '0000001944';
// })->toArray();

// echo '<pre>';
// print_r($studentData);
// echo '</pre>';



// Build the course selections grouped by student
foreach ($studentData as $gibbonPersonIDStudent => &$student) {
    $enrolments = (!empty($enrolmentsData[$gibbonPersonIDStudent]))? $enrolmentsData[$gibbonPersonIDStudent] : array();
    $enrolments = collect($enrolments)->keyBy('gibbonCourseID');

    // Create pseudo-results for existing enrolments (hack-ish?)
    foreach ($enrolments as $gibbonCourseID => $enrolment) {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID,
                      'gibbonPersonIDStudent' => $gibbonPersonIDStudent,
                      'weight' => 0.0,
                      'gibbonCourseID' => $gibbonCourseID,
                      'gibbonCourseClassID' => $enrolment['gibbonCourseClassID'],
                      'status' => 'Complete',
                      'flag' => '',
                      'reason' => 'Locked',
        );

        $timetableGateway->insertResult($data);
    }

    // Grab existing timetable information, for finding conflicts
    $enrolmentTTDays = $enrolments->reduce(function($ttDays, &$item) {
        $ttDays = array_merge($ttDays, explode(',', $item['ttDays']));
        return $ttDays;
    }, array());

    $studentData[$gibbonPersonIDStudent]['ttDays'] = array_unique($enrolmentTTDays);

    $selections = (!empty($selectionsData[$gibbonPersonIDStudent]))? $selectionsData[$gibbonPersonIDStudent] : array();

    // Condense the result set down group by Student > Course > Classes
    $selectionsData[$gibbonPersonIDStudent] = collect($selections)->filter(function(&$item) use ($enrolments) {
        $noExistingEnrolments = empty($enrolments[$item['gibbonCourseID']]);
        $hasTTDays = !empty($item['ttDays']);
        return $noExistingEnrolments && $hasTTDays;
    })->map(function(&$item) {
        $item['ttDays'] = explode(',', $item['ttDays']);
        return $item;
    })->groupBy('gibbonCourseID')->filter(function($items) {
        return count($items) > 0;
    })->toArray();
}


$courseSelectionData = collect($selectionsData);

$factory = new EngineFactory();

// Engine Settings
$settings = $factory->createSettings();
$settings->timetableConflictTollerance = $settingGateway->getSettingByScope('Course Selection', 'timetableConflictTollerance');
$settings->optimalWeight = 1.0;
$settings->maximumOptimalResults = 0;
$settings->minimumStudents = $settingGateway->getSettingByScope('Course Selection', 'classEnrolmentMinimum');
$settings->targetStudents = $settingGateway->getSettingByScope('Course Selection', 'classEnrolmentTarget');
$settings->maximumStudents = $settingGateway->getSettingByScope('Course Selection', 'classEnrolmentMaximum');

$settings->genderBalancePriority = $settingGateway->getSettingByScope('Course Selection', 'genderBalancePriority');
$settings->targetEnrolmentPriority = $settingGateway->getSettingByScope('Course Selection', 'targetEnrolmentPriority');
$settings->coreCoursePriority = $settingGateway->getSettingByScope('Course Selection', 'coreCoursePriority');
$settings->avoidConflictPriority = $settingGateway->getSettingByScope('Course Selection', 'avoidConflictPriority');
$settings->autoResolveConflicts = $settingGateway->getSettingByScope('Course Selection', 'autoResolveConflicts');

$settings->heuristic = 'Class Size';
$settings->validator = 'Conflict';
$settings->evaluator = 'Weighted';

// Engine Environment
$environment = $factory->createEnvironment();
$environment->setClassData($classData);
$environment->setStudentData($studentData);

// Build the engine
$engine = $factory->createEngine($settings);
$engine->buildEngine($environment);

// Add the student course selections data
$courseSelectionData->filter()->each(function($courses, $gibbonPersonIDStudent) use ($engine) {
    $engine->addData($gibbonPersonIDStudent, array_values($courses) );
});

// Run
$results = $engine->runEngine();

$data = array('gibbonSchoolYearID' => $gibbonSchoolYearID,);
$flagData = array('gibbonSchoolYearID' => $gibbonSchoolYearID,);

// Make this a method, somewhere?
foreach ($results as $gibbonPersonIDStudent => $result) {
    $data['gibbonPersonIDStudent'] = $gibbonPersonIDStudent;
    $data['weight'] = $result->weight ?? 0.0;

    if (!empty($result->values) && is_array($result->values)) {
        foreach ($result->values as $class) {
            $data['gibbonCourseID'] = $class['gibbonCourseID'];
            $data['gibbonCourseClassID'] = $class['gibbonCourseClassID'];
            $data['status'] = (!empty($class['flag']))? 'Flagged' : 'Complete';
            $data['flag'] = $class['flag'] ?? null;
            $data['reason'] = $class['reason'] ?? null;

            $timetableGateway->insertResult($data);
        }
    } else {
        $data['gibbonCourseID'] = null;
        $data['gibbonCourseClassID'] = null;
        $data['status'] = 'Failed';
        $data['flag'] = null;
        $data['reason'] = null;
        $data['weight'] = -10.0;

        $timetableGateway->insertResult($data);
    }
}

// Save the performance stats, may be interested in the results later ...
$settingsGateway->update('Course Selection', 'timetablingResults', json_encode($engine->getPerformance()));

// End the process and output the result to terminal (output file)
$processor->stopProcess('engine');
die( __('Complete')."\n" );
