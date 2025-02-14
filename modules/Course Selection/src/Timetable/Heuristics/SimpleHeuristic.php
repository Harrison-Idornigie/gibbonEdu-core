<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Timetable\Heuristics;

/**
 * Timetabling Heuristic that does nothing ...
 *
 * @version v14
 * @since   11th May 2017
 */
class SimpleHeuristic extends Heuristic
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
        return $options;
    }
}
