<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Timetable\Heuristics;

use CourseSelection\Timetable\EngineEnvironment;
use CourseSelection\Timetable\EngineSettings;
use CourseSelection\DecisionTree\NodeHeuristic;

/**
 * Implementation of the NodeHeuristic interface for the Timetabling Engine
 *
 * @version v14
 * @since   6th May 2017
 */
abstract class Heuristic implements NodeHeuristic
{
    protected $environment;
    protected $settings;

    public function __construct(EngineEnvironment $environment, EngineSettings $settings)
    {
        $this->environment = $environment;
        $this->settings = $settings;
    }

    /**
     * @param   object &$node
     * @param   int    $depth
     * @return  bool
     */
    abstract public function sortDecisions(&$options, &$node);
}
