<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Timetable\Evaluators;

use CourseSelection\Timetable\EngineEnvironment;
use CourseSelection\Timetable\EngineSettings;
use CourseSelection\DecisionTree\NodeEvaluator;

/**
 * Implementation of the NodeEvaluator interface for the Timetabling Engine
 *
 * @version v14
 * @since   4th May 2017
 */
abstract class Evaluator implements NodeEvaluator
{
    protected $environment;
    protected $settings;

    /**
     * Internal Counters
     */
    protected $optimalNodesEvaluated;

    /**
     * Performance Metrics
     */
    protected $performance = array(
        'nodeEvaluations'   => 0,
        'treeEvaluations'   => 0,
        'incompleteResults' => 0,
    );

    public function __construct(EngineEnvironment $environment, EngineSettings $settings)
    {
        $this->environment = $environment;
        $this->settings = $settings;
    }

    /**
     * @param   object  &$node
     * @return  float
     */
    abstract public function evaluateNodeWeight(&$node, $treeDepth) : float;

    /**
     * @param   array  &$nodes
     * @return  bool
     */
    public function evaluateTreeCompletion(&$tree, &$leaves) : bool
    {
        $this->performance['treeEvaluations']++;

        // The tree is potentially complete if we've already seen a number of optimal results
        if (!empty($this->settings->maximumOptimalResults)) {
            if ($this->optimalNodesEvaluated >= $this->settings->maximumOptimalResults) {
                return true;
            }
        }

        return false;
    }

    public function getBestNodeInSet(&$nodes)
    {
        $bestResult = current($nodes);
        $bestWeight = -INF;

        foreach ($nodes as $node) {
            if ($node->weight > $bestWeight) {
                $bestResult = $node;
                $bestWeight = $node->weight;
            }
        }

        if (empty($bestResult)) $this->performance['incompleteResults']++;

        return $bestResult;
    }

    public function reset()
    {
        $this->optimalNodesEvaluated = 0;
    }

    public function getPerformance()
    {
        return $this->performance;
    }
}
