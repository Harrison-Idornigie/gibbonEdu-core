<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Form;

use Gibbon\Forms\Input\Input;

/**
 * Course Selection - Form Element
 *
 * @version v14
 * @since   19th April 2017
 */
class CourseSelection extends Input
{
    protected $description;
    protected $checked = array();
    protected $readOnly;
    protected $selectStatus = false;

    protected $blockID;
    protected $courses;
    protected $selectedChoices;

    public function __construct($name)
    {
        $this->setName($name);
        $this->addClass('courseChoice');
    }

    public function fromResults($coursesRequest)
    {
        $this->courses = ($coursesRequest->rowCount() > 0)? $coursesRequest->fetchAll() : array();

        return $this;
    }

    public function fromArray($courses)
    {
        $this->courses = (is_array($courses))? $courses : array();

        return $this;
    }

    public function selected($selected = array())
    {
        $this->selectedChoices = $selected;

        return $this;
    }

    public function description($value = '')
    {
        $this->description = $value;
        return $this;
    }

    public function setBlockID($value)
    {
        $this->blockID = $value;

        $this->addClass('courseBlock'.$this->blockID);
        $this->setAttribute('data-block', $this->blockID);

        return $this;
    }

    public function getReadOnly()
    {
        return $this->getAttribute('readonly');
    }

    public function setReadOnly($value)
    {
        $this->setAttribute('readonly', $value);

        return $this;
    }

    public function canSelectStatus($value = true)
    {
        $this->selectStatus = $value;

        return $this;
    }

    protected function getChoiceStatus($value)
    {
        return (isset($this->selectedChoices[$value]['status']))? $this->selectedChoices[$value]['status'] : '';
    }

    protected function getChoiceApproved($value)
    {
        return (isset($this->selectedChoices[$value]['approved']))? $this->selectedChoices[$value]['approved'] == 'Approved' : false;
    }

    protected function getIsChecked($value)
    {
        if (empty($value) || empty($this->selectedChoices)) {
            return '';
        }

        $status = $this->getChoiceStatus($value);

        return ($status == 'Required' || $status == 'Approved' || $status == 'Requested' || $status == 'Selected')? 'checked' : '';
    }

    protected function getElement()
    {
        $output = '';

        if (!empty($this->courses) && is_array($this->courses)) {
            foreach ($this->courses as $course) {
                $courseID = $course['gibbonCourseID'];
                $label = $course['courseName'];
                $status = $this->getChoiceApproved($courseID)? 'Approved' : $this->getChoiceStatus($courseID);

                $name = 'courseSelection['.$this->blockID.']['.$courseID.']';

                $this->setName($name);
                $this->setID($this->blockID.'-'.$courseID);
                $this->setAttribute('checked', $this->getIsChecked($courseID));
                $this->setAttribute('data-course', $courseID);

                $this->setValue('Selected');

                if ($this->getReadOnly() && $this->getIsChecked($courseID) == false) continue;

                $output .= '<div class="courseChoiceContainer" data-status="'.$status.'">';

                if ($this->getReadOnly() == false) {

                    $locked = ($status == 'Required' || $status == 'Approved' || $status == 'Locked')? 'true' : 'false';

                    $this->setAttribute('disabled', $locked == 'true');
                    $this->setAttribute('data-locked', $locked);

                    $output .= '<input type="checkbox" '.$this->getAttributeString().'> &nbsp;';
                }

                $output .= '<label for="'.$this->getID().'" title="'.$course['courseNameShort'].'">'.$label.'</label>';

                if ($this->selectStatus == true) {
                    if ($status == 'Approved') {
                        $output .= '<input type="hidden" name="courseStatus['.$this->blockID.']['.$courseID.']" value="'.$this->getChoiceStatus($courseID).'">';
                        $output .= '<span class="courseTag pullRight small emphasis">&nbsp; '.__($status).'</span>';
                    } else {
                        $output .= '<select name="courseStatus['.$this->blockID.']['.$courseID.']" class="courseStatusSelect pullRight">';
                        $output .= '<option value="" '.(($status == 'Removed' || $status == '')? 'selected' : '').'> </option>';
                        $output .= '<option value="Required" '.($status == 'Required'? 'selected' : '').'>'.__('Required').'</option>';
                        $output .= '<option value="Recommended" '.($status == 'Recommended'? 'selected' : '').'>'.__('Recommended').'</option>';
                        $output .= '<option value="Requested" '.($status == 'Requested' ? 'selected' : '').'>'.__('Requested').'</option>';

                        $output .= '</select>';
                    }
                } else {
                    if ($status == 'Required' || $status == 'Approved' || $status == 'Recommended') {
                        $output .= '<span class="courseTag pullRight small emphasis">&nbsp; '.__($status).'</span>';
                    }
                }


                $output .= '</div>';
            }
        }

        return $output;
    }
}
