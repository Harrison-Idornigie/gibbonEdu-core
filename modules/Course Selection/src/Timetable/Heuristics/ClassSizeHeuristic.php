<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Timetable\Heuristics;

/**
 * Timetabling Heuristic that orders the results to fill empty classes first
 *
 * @version v14
 * @since   11th May 2017
 */
class ClassSizeHeuristic extends Heuristic
{
    /**
     * @param   object &$node
     * @param   int    $depth
     * @return  bool
     */
    public function sortDecisions(&$options, &$node)
    {
        $environment = &$this->environment;

        // Look for any existing enrolments and return them if found (eg: no decisions to make)
        $currentEnrolment = array_filter($options, function($item){
            return (isset($item['currentEnrolment']) && $item['currentEnrolment'] == 1);
        });

        if (count($currentEnrolment) > 0) {
            return $currentEnrolment;
        }

        // Exclude options
        $options = array_filter($options, function($item) use ($environment) {
            if (!isset($item['gibbonCourseClassID'])) return false;
            return empty($environment->getClassValue($item['gibbonCourseClassID'], 'excluded'));
        });



        // Sorts by timetable conflicts first, then number of students in the class
        // usort($options, function($a, $b) use ($environment) {

        //     $aCount = $environment->getEnrolmentCount($a['gibbonCourseClassID']);
        //     $bCount = $environment->getEnrolmentCount($b['gibbonCourseClassID']);

        //     // Avoid filling empty classes first
        //     if ($aCount == 0) return -1;

        //     // De-prioritize those that are over the minimum
        //     if ($aCount >= $this->settings->minimumStudents) {
        //         return $bCount - $aCount;
        //     }
        //     if ($bCount >= $this->settings->minimumStudents) return 1;

        //     return $aCount - $bCount;
        // });

        return $options;
    }
}
