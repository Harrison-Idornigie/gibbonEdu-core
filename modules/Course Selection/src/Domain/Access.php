<?php

namespace CourseSelection\Domain;

/**
 * Access
 */
class Access extends DatabaseObject
{
    const CLOSED = 0;
    const VIEW_ONLY = 1;
    const OPEN = 2;

    protected $accessStart;
    protected $accessEnd;

    public function __construct($data = array())
    {
        parent::__construct($data);

        $this->accessStart = new \DateTime($data['dateStart']);
        $this->accessEnd = new \DateTime($data['dateEnd']);
    }

    public function getAccessLevel()
    {
        $today = new \DateTime(date('Y-m-d'));

        if ($this->accessStart > $today  || $this->accessEnd < $today ) {
            return self::CLOSED;
        }

        if ($this->accessType == 'Request' || $this->accessType == 'Select') {
            return self::OPEN;
        }

        if ($this->accessType == 'View') {
            return self::VIEW_ONLY;
        }

        return self::CLOSED;
    }
}
