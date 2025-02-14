<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\DecisionTree;

/**
 * Interface for evaluating a node's weight
 *
 * @version v14
 * @since   4th May 2017
 */
interface NodeEvaluator
{
    /**
     * Should return a weighting for the node, based on its suitability as a solution to the problem.
     *
     * @param   object  &$node
     * @return  float
     */
    public function evaluateNodeWeight(&$node, $treeDepth) : float;

    /**
     * Should return true if the tree is complete based on the problem conditions.
     *
     * @param   array  &$nodes
     * @return  bool
     */
    public function evaluateTreeCompletion(&$tree, &$leaves) : bool;
}
