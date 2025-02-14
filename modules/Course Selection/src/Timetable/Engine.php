<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Timetable;

use CourseSelection\DecisionTree\DecisionTree;

/**
 * Timetabling Engine
 *
 * Handles batch processing of student timetable generation via decision tree.
 * The engine is made up of several replaceable parts, allowing for a high
 * degree of control over the timetabling process.
 *
 * @version v14
 * @since   4th May 2017
 */
class Engine
{
    /**
     * Configuration Objects
     */
    protected $factory;
    protected $settings;
    protected $environment;

    /**
     * Replaceable Parts
     */
    protected $heuristic;
    protected $validator;
    protected $evaulator;
    protected $solver;

    /**
     * Data Storage
     */
    protected $dataSet = array();
    protected $resultSet = array();

    /**
     * Metrics
     */
    protected $performance = array();

    public function __construct(EngineFactory $factory, EngineSettings $settings)
    {
        $this->factory = $factory;
        $this->settings = $settings;
    }

    public function getPerformance()
    {
        return $this->performance;
    }

    public function getEnvironment()
    {
        return $this->environment;
    }

    public function addData($gibbonPersonIDStudent, $data)
    {
        if (empty($data) || !is_array($data)) {
            throw new \Exception("Invalid data fed into engine: not a valid two-dimensional array for student {$gibbonPersonIDStudent}.");
        }

        $this->dataSet[$gibbonPersonIDStudent] = $data;
    }

    public function buildEngine(EngineEnvironment $environment)
    {
        // Factory is responsible for creating and configuring the parts that go in the engine
        $this->environment = $environment;
        $this->heuristic = $this->factory->createHeuristic($this->environment, $this->settings);
        $this->validator = $this->factory->createValidator($this->environment, $this->settings);
        $this->evaluator = $this->factory->createEvaluator($this->environment, $this->settings);
        $this->solver = $this->factory->createSolver($this->heuristic, $this->validator, $this->evaluator);
    }

    public function runEngine()
    {
        if (empty($this->solver) || empty($this->heuristic) || empty($this->validator) || empty($this->evaluator)) {
            throw new \Exception('Engine cannot run: missing some parts!');
        }

        if (empty($this->dataSet) || !is_array($this->dataSet)) {
            throw new \Exception('Engine cannot run: no decisions to make. Feed some data into the engine.');
        }

        $this->startEngine();

        foreach ($this->dataSet as $gibbonPersonIDStudent => $data) {
            $this->validator->reset();
            $this->evaluator->reset();

            $results = $this->solver->makeDecisions($data);
            $bestResult = $this->evaluator->getBestNodeInSet($results);

            // Post-process the results
            $this->environment->updateEnrolmentCountsFromResult($bestResult);
            $this->validator->resolveConflicts($bestResult);

            $this->resultSet[$gibbonPersonIDStudent] = $bestResult;
        }

        $this->stopEngine();

        return $this->resultSet;
    }

    protected function startEngine()
    {
        $this->performance['startTime'] = microtime(true);
        $this->performance['startMemory'] = memory_get_usage();
    }

    protected function stopEngine()
    {
        $this->performance = array_merge(
            $this->performance,
            $this->validator->getPerformance(),
            $this->evaluator->getPerformance()
        );

        $this->performance['stopTime'] = microtime(true);
        $this->performance['stopMemory'] = memory_get_usage();

        $units = array('bytes','KB','MB','GB','TB','PB');
        $bytes = max( 0, ($this->performance['stopMemory'] - $this->performance['startMemory']));
        $memory = round($bytes/pow(1024,($i=floor(log($bytes,1024)))),2).' '.$units[$i];
        $time = number_format($this->performance['stopTime'] - $this->performance['startTime'], 6).' sec';

        $this->performance['time'] = $time;
        $this->performance['memory'] = $memory;
        $this->performance['totalResults'] = count($this->resultSet);
    }
}
