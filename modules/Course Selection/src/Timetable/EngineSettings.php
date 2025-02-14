<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Timetable;

/**
 * Timetabling Engine: Settings
 *
 * Holds a set of pre-defined and user-defined configuration values to modify how the engine runs.
 *
 * @version v14
 * @since   4th May 2017
 */
class EngineSettings
{
    /**
     * Default Configuration Settings
     */
    protected $settings = array(
        'heuristic'                   => 'Class Size',
        'validator'                   => 'Conflict',
        'evaluator'                   => 'Weighted',
        'genderBalancePriority'       => 0.5,
        'targetEnrolmentPriority'     => 1.0,
        'coreCoursePriority'          => 1.0,
        'avoidConflictPriority'       => 2.0,
        'autoResolveConflicts'        => true,
        'minimumStudents'             => 8,
        'targetStudents'              => 14,
        'maximumStudents'             => 24,
        'timetableConflictTollerance' => 0,
        'optimalWeight'               => 1.0,
        'maximumOptimalResults'       => 0,
    );

    public function __construct($settings = array())
    {
        $this->settings = array_replace($this->settings, $settings);
    }

    public function __get($key)
    {
        if (!isset($this->settings[$key])) {
            throw new \Exception('Could not access engine setting: invalid key '.$key);
        }

        return $this->settings[$key];
    }

    public function __set($key, $value)
    {
        if (!isset($this->settings[$key])) {
            throw new \Exception('Could not access engine setting: invalid key '.$key);
        }

        $this->settings[$key] = $value;
    }
}
