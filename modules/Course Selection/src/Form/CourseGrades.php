<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Form;

use Gibbon\Forms\Layout\Element;

/**
 * Course Grades - Form Element
 *
 * @version v14
 * @since   19th April 2017
 */
class CourseGrades extends Element
{
    protected $grades;

    public function __construct()
    {
    }

    public function fromResults($gradesRequest)
    {
        $this->grades = ($gradesRequest->rowCount() > 0)? $gradesRequest->fetchAll() : array();

        return $this;
    }

    public function fromArray($grades)
    {
        $this->grades = (is_array($grades))? $grades : array();

        return $this;
    }

    public function getOutput()
    {
        $output = '';

        $output .= implode('', array_map(function ($grade) {
            if (empty($grade['grade'])) return '';
            $output = ($grade['schoolYearStatus'] == 'Current')? '<div title="Current Year '.$grade['reportName'].'" class="courseGrades" style="background:#fff4da;">' : '<div title="Final Grade" class="courseGrades" style="background:#D4F6DC;">';
            $output .= $grade['courseNameShort'].' ('.$grade['schoolYearName'].'): ';

            if ($grade['reportID'] >= 25) {
                $output .= intval($grade['grade']);
            } else {
                $output .= intval($grade['grade']).'%';
            }
            
            $output .= '</div>';
            return $output;
        }, $this->grades));

        return $output;
    }
}
