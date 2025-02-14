<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\DecisionTree;

/**
 * Interface for making a decision between different options
 *
 * @version v14
 * @since   4th May 2017
 */
interface NodeHeuristic
{
    /**
     * Should return one item from a set of options that best suits the given heuristic.
     *
     * @param   object &$node
     * @param   int    $depth
     * @return  bool
     */
    public function sortDecisions(&$options, &$node);
}
