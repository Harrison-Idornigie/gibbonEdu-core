<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\DecisionTree;

/**
 * Interface for validating a node is complete
 *
 * @version v14
 * @since   4th May 2017
 */
interface NodeValidator
{
    /**
     * Should return true if the values in the node are valid within the constraints of the problem.
     *
     * @param   object &$node
     * @param   int    $depth
     * @return  bool
     */
    public function validateNode(&$node, $treeDepth) : bool;
}
