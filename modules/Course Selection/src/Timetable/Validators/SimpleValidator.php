<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Timetable\Validators;

/**
 * Validator that only looks for completion of the node decisions
 *
 * @version v14
 * @since   4th May 2017
 */
class SimpleValidator extends Validator
{
    public function __construct()
    {
    }

    /**
     * @param   object  &$node
     * @param   int     &$treeDepth
     * @return  bool
     */
    public function validateNode(&$node, $treeDepth) : bool
    {
        $this->performance['nodeValidations']++;

        return (count($node->values) >= $treeDepth);
    }

    public function resolveConflicts(&$node)
    {

    }
}
