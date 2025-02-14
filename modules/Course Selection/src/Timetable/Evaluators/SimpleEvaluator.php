<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Timetable\Evaluators;

/**
 * Evaluator that simply returns 1.0 weight (for testing)
 *
 * @version v14
 * @since   4th May 2017
 */
class SimpleEvaluator extends Evaluator
{
    public function __construct()
    {
    }

    /**
     * @param   object  &$node
     * @return  float
     */
    public function evaluateNodeWeight(&$node, $treeDepth) : float
    {
        $this->performance['nodeEvaluations']++;

        return 0.0;
    }

    public function evaluateTreeCompletion(&$tree, &$leaves) : bool
    {
        return false;
    }
}
