<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Timetable\Heuristics;

/**
 * Timetabling Heuristic that simply shuffles all options.
 *
 * @version v14
 * @since   11th May 2017
 */
class RandomizeHeuristic extends Heuristic
{
    public function __construct()
    {
    }

    /**
     * @param   object &$node
     * @param   int    $depth
     * @return  bool
     */
    public function sortDecisions(&$options, &$node)
    {
        // Shake the tree
        shuffle($options);

        return $options;
    }
}
