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
class CourseProgressByOffering extends Element
{
    protected $offering;

    protected $messages;

    public function __construct($offeringData)
    {
        $this->setClass('right');
        $this->offering = $offeringData;
    }

    public function setMessage($key, $value = '')
    {
        $this->messages[$key] = $value;

        return $this;
    }

    public function getMessage($key)
    {
        return (isset($this->messages[$key]))? $this->messages[$key] : '';
    }

    public function getOutput()
    {
        $output = '';

        $this->setAttribute('title', 'Min '.$this->offering['minSelect'].' Max '.$this->offering['maxSelect']);
        $this->setAttribute('data-min', $this->offering['minSelect']);
        $this->setAttribute('data-max', $this->offering['maxSelect']);

        $output .= '<div class="courseProgressByOffering" '.$this->getAttributeString().'>';

            $output .= '<div class="progressBar" style="width:100%"><div class="complete" style="width:0%;"></div></div>';

            $output .= '<div class="valid success hidden">';
                $output .= $this->getMessage('complete');
            $output .= '</div>';

            $output .= '<div class="invalid warning hidden">';
                $output .= $this->getMessage('invalid');
            $output .= '</div>';

            $output .= '<div class="continue information hidden">';
                $output .= $this->getMessage('continue');
            $output .= '</div>';

        $output .= '</div>';

        return $output;
    }
}
