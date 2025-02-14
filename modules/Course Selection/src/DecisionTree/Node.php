<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\DecisionTree;

/**
 * Node
 *
 * @version v14
 * @since   3rd May 2017
 */
class Node
{
    public $values;
    public $weight = 0.0;

    public function __construct($values)
    {
        $this->values = $values;
    }

    public function getDepth()
    {
        return count($this->values);
    }
}
