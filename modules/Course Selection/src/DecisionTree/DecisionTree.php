<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\DecisionTree;

/**
 * Decision Tree
 *
 * A generic implementation of a basic decision tree: not tied to a specific type of data.
 * The validator and evaluator interfaces provide hooks for making decisions.
 *
 * @version v14
 * @since   3rd May 2017
 */
class DecisionTree
{
    protected $heuristic;
    protected $validator;
    protected $evaulator;

    public function __construct(NodeHeuristic $heuristic, NodeValidator $validator, NodeEvaluator $evaulator)
    {
        $this->heuristic = $heuristic;
        $this->validator = $validator;
        $this->evaulator = $evaulator;
    }

    public function buildTree(array &$decisions)
    {
        $tree = array();
        $leaves = array();

        // Create an empty node and push it to the root of the tree
        $tree[] = new Node(array());

        // Continue adding branches to the tree until the goal is met
        while (!$this->isGoalSatisfied($tree, $leaves)) {
            $tree = $this->createBranches($tree, $leaves, $decisions);
        }

        return $leaves;
    }

    protected function isGoalSatisfied(&$tree, &$leaves)
    {
        return (count($tree) == 0) || $this->evaulator->evaluateTreeCompletion($tree, $leaves);
    }

    protected function createBranches(&$tree, &$leaves, &$decisions)
    {
        $node = array_pop($tree);

        $nodeDepth = $node->getDepth();
        $treeDepth = count($decisions);

        if ($nodeDepth == $treeDepth) {
            if ($this->validator->validateNode($node, $treeDepth)) {
                // Complete (and valid) nodes become leaves
                $node->weight = $this->evaulator->evaluateNodeWeight($node, $treeDepth);
                array_push($leaves, $node);
            }
        } else {
            // Decisions in the tree become branches
            $branches = $decisions[$nodeDepth];

            // Shake the tree a bit: sort branches by a heuristic
            $branches = $this->heuristic->sortDecisions($branches, $node);

            foreach ($branches as $branch) {
                // Combine the result of this decision with the previous decisions
                $values = array_slice($node->values, 0);
                array_push($values, $branch);

                // Each branch leads to a new node on the tree
                array_push($tree, new Node($values));
            }
        }

        return $tree;
    }
}
