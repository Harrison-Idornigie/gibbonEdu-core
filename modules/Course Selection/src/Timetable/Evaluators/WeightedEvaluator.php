<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Timetable\Evaluators;

/**
 * Evaluator that weights based on gender balance, class size, etc.
 *
 * @version v14
 * @since   4th May 2017
 */
class WeightedEvaluator extends Evaluator
{
    /**
     * @param   object  &$node
     * @return  float
     */
    public function evaluateNodeWeight(&$node, $treeDepth) : float
    {
        $this->performance['nodeEvaluations']++;

        $weightTotal = 0.0;
        $weightCumulative = 0.0;

        // Sub-weighting: Gender Balance
        $weightTotal += $this->settings->genderBalancePriority;
        $weightCumulative += ($this->settings->genderBalancePriority * $this->getGenderBalanceWeight($node));

        // Sub-weighting: Class Enrolment
        $weightTotal += $this->settings->targetEnrolmentPriority;
        $weightCumulative += ($this->settings->targetEnrolmentPriority * $this->getEnrolmentWeight($node));

        // Sub-weighting: Course Priority
        $weightTotal += $this->settings->coreCoursePriority;
        $weightCumulative += ($this->settings->coreCoursePriority * $this->getCorePriorityWeight($node));

        // Sub-weighting: Timetable Conflicts
        //$weightTotal += $this->settings->avoidConflictPriority;
        //$weightCumulative += ($this->settings->avoidConflictPriority * $this->getConflictWeight($node));

        // Get the weighted weight :P
        $weight = ($weightTotal > 0)? ($weightCumulative / $weightTotal) : 0;

        // Post-weighting: Flagged Classes
        $weight += $this->getFlaggedWeight($node) * 2;

        $weight += $this->getConflictWeight($node);

        // Possibly use this to short-cut out of result sets that already have a number of optimal results?
        if ($weight >= $this->settings->optimalWeight) {
            $this->optimalNodesEvaluated++;
        }

        return $weight;
    }

    /**
     * Normalized from -1 to 1
     *
     * @param    object  &$node
     * @return   float
     */
    protected function getGenderBalanceWeight(&$node)
    {
        $weight = 0.0;

        $gender = $this->environment->getStudentValue(current($node->values)['gibbonPersonID'], 'gender');

        foreach ($node->values as $values) {
            $students = $this->environment->getEnrolmentCount($values['gibbonCourseClassID']);

            if (empty($students)) continue;

            $studentsMale = $this->environment->getEnrolmentCount($values['gibbonCourseClassID'], 'M');
            $studentsFemale = $this->environment->getEnrolmentCount($values['gibbonCourseClassID'], 'F');

            if ($gender == 'F') {
                $balance = ($studentsMale / $students) - ($studentsFemale / $students);
            } else {
                $balance = ($studentsFemale / $students) - ($studentsMale / $students);
            }

            $weight += $balance;
        }

        return $weight / count($node->values);
    }

    /**
     * Normalized from -1 to 1
     *
     * @param    object  &$node
     * @return   float
     */
    protected function getEnrolmentWeight(&$node)
    {
        $weight = 0.0;

        foreach ($node->values as $values) {
            $students = $this->environment->getEnrolmentCount($values['gibbonCourseClassID']);
            $priority = $this->environment->getClassValue($values['gibbonCourseClassID'], 'priority');

            if ($priority < 1) {
                $priority = 1;
            }

            if ($students < $this->settings->minimumStudents) {
                $percent = 1.0;
            } else {
                $percent = 1.0 - (($students / $this->settings->maximumStudents) * 2.0);
            }

            // Adjust by priority? to ensure small less-important classes arent out-weighting larger important ones
            $weight += $percent ; // / $priority
        }

        return $weight / count($node->values);
    }

    /**
     * Normalized from 0 to 1
     *
     * @param    object  &$node
     * @return   float
     */
    protected function getCorePriorityWeight(&$node)
    {
        $environment = $this->environment;

        $min = $this->environment->getMinPriority();
        $max = $this->environment->getMaxPriority();

        $priority = array_reduce($node->values, function($total, $item) use (&$environment, &$min, &$max) {
            $priority = $environment->getClassValue($item['gibbonCourseClassID'], 'priority');
            return $total + (1.0 - (($priority - $min) / max($max - $min, 1)));
        }, 0);

        return  ($priority / count($node->values));
    }

    /**
     * Not normalized? -n to 0
     *
     * @param    object  &$node
     * @return   float
     */
    protected function getConflictWeight(&$node)
    {
        if (empty($node->conflicts) || count($node->conflicts) == 0) {
            return 0.0;
        }

        $min = $this->environment->getMinPriority();
        $max = $this->environment->getMaxPriority();

        $weight = array_reduce($node->conflicts, function($total, $item) use (&$min, &$max) {
            $priority = $this->environment->getClassValue($item['gibbonCourseClassID'], 'priority');
            return $total + (1.0 - (($priority - $min) / max($max - $min, 1))) + 0.5;
        }, 0.0);

        return $weight * -1.0;
    }

    /**
     * Not normalized? -n to 0
     *
     * @param    object  &$node
     * @return   float
     */
    protected function getFlaggedWeight(&$node)
    {
        return array_reduce($node->values, function($total, &$item) {
            $total += (!empty($item['flag']))? -1 : 0;
            return $total;
        }, 0);
    }
}
