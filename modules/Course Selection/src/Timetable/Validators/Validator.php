<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Timetable\Validators;

use CourseSelection\Timetable\EngineEnvironment;
use CourseSelection\Timetable\EngineSettings;
use CourseSelection\DecisionTree\NodeValidator;

/**
 * Implementation of the NodeValidator interface for the Timetabling Engine
 *
 * @version v14
 * @since   4th May 2017
 */
abstract class Validator implements NodeValidator
{
    protected $environment;
    protected $settings;

    /**
     * Performance Metrics
     */
    protected $performance = array(
        'nodeValidations'   => 0,
    );

    public function __construct(EngineEnvironment $environment, EngineSettings $settings)
    {
        $this->environment = $environment;
        $this->settings = $settings;
    }

    /**
     * @param   object  &$node
     * @param   int     &$treeDepth
     * @return  bool
     */
    abstract public function validateNode(&$node, $treeDepth) : bool;

    public function reset()
    {

    }

    public function getPerformance()
    {
        return $this->performance;
    }
}
