<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Form;

use Gibbon\Forms\FormFactory;
use CourseSelection\Domain\SelectionsGateway;

/**
 * CourseSelectionFormFactory
 *
 * Handles Form object creation for the Course Selection process
 *
 * @version v14
 * @since   19th April 2017
 */
class CourseSelectionFormFactory extends FormFactory
{

    public function __construct()
    {
    }

    public static function create()
    {
        return new CourseSelectionFormFactory();
    }

    public function createCourseSelection($name)
    {
        return new CourseSelection($name);
    }

    public function createCourseGrades()
    {
        return new CourseGrades();
    }

    public function createCourseProgressByBlock($blockData)
    {
        return new CourseProgressByBlock($blockData);
    }

    public function createCourseProgressByOffering($offeringData)
    {
        return new CourseProgressByOffering($offeringData);
    }
}
