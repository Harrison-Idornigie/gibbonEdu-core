<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Timetable;

/**
 * Timetabling Engine: Environment
 *
 * Holds the additional data about the timetable as a whole, nessesary to make individual decisions.
 *
 * @version v14
 * @since   4th May 2017
 */
class EngineEnvironment
{
    protected $classData = array();
    protected $studentData = array();

    protected $enrolmentData = array();

    protected $minPriority = 0.0;
    protected $maxPriority = 0.0;

    public function getClassData()
    {
        return $this->classData;
    }

    public function setClassData($classData = array())
    {
        $this->classData = $classData;

        foreach ($this->classData as $course) {
            // Class enrolments can be grouped for purposes of combining student numbers across courses (from course meta data)
            $class = strval($course['classNameShort']);
            $enrolmentGroup = (!empty($course['enrolmentGroup']))? $course['enrolmentGroup'] : $course['gibbonCourseClassID'];
            $this->setClassValue($course['gibbonCourseClassID'], 'enrolmentGroup', $enrolmentGroup);

            // Build the initial class enrolment counts
            $this->enrolmentData[$enrolmentGroup][$class]['total'] = $course['students'] ?? 0;
            $this->enrolmentData[$enrolmentGroup][$class]['M'] = $course['studentsMale'] ?? 0;
            $this->enrolmentData[$enrolmentGroup][$class]['F'] = $course['studentsFemale'] ?? 0;

            $this->minPriority = min($this->minPriority, floatval($course['priority']));
            $this->maxPriority = max($this->maxPriority, floatval($course['priority']));
        }
    }

    public function getStudentData()
    {
        return $this->studentData;
    }

    public function setStudentData($studentData = array())
    {
        $this->studentData = $studentData;
    }

    public function getClassValue($classID, $key)
    {
        return (isset($this->classData[$classID][$key]))? $this->classData[$classID][$key] : 0;
    }

    public function setClassValue($classID, $key, $value)
    {
        $this->classData[$classID][$key] = $value;
    }

    public function getStudentValue($studentID, $key)
    {
        return (isset($this->studentData[$studentID][$key]))? $this->studentData[$studentID][$key] : 0;
    }

    public function setStudentValue($studentID, $key, $value)
    {
        $this->studentData[$studentID][$key] = $value;
    }

    public function getMinPriority()
    {
        return $this->minPriority;
    }

    public function getMaxPriority()
    {
        return $this->maxPriority;
    }

    public function getEnrolmentCount($classID, $group = 'total')
    {
        $enrolmentGroup = $this->getClassValue($classID, 'enrolmentGroup');
        $class = $this->getClassValue($classID, 'classNameShort');

        return $this->enrolmentData[$enrolmentGroup][$class][$group] ?? 0;
    }

    public function incrementEnrolmentCount($classID, $studentID, $increment = 1)
    {
        $enrolmentGroup = $this->getClassValue($classID, 'enrolmentGroup');
        $class = $this->getClassValue($classID, 'classNameShort');
        $gender = $this->getStudentValue($studentID, 'gender');

        $this->enrolmentData[$enrolmentGroup][$class]['total'] += $increment;
        $this->enrolmentData[$enrolmentGroup][$class][$gender] += $increment;
    }

    public function updateEnrolmentCountsFromResult(&$result)
    {
        if (empty($result)) return;

        foreach ($result->values as $value) {
            if (empty($value)) continue;
            if (!empty($value['flag'])) continue;
            if (!empty($value['currentEnrolment'])) continue;

            $this->incrementEnrolmentCount($value['gibbonCourseClassID'], $value['gibbonPersonID']);
        }
    }
}
