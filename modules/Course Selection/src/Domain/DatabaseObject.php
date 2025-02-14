<?php

namespace CourseSelection\Domain;

/**
 * DatabaseObject
 */
abstract class DatabaseObject implements \ArrayAccess
{
    protected $data;
    protected $fields;

    public function __construct($data = array())
    {
        $this->data = $data;
    }

    public function __get($name)
    {
        return $this->offsetGet($name);
    }

    public function __set($name, $value)
    {
        $this->offsetSet($name, $value);
    }

    public function offsetGet($offset)
    {
        return $this->data[$offset] ?? '';
    }

    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($this->data, $offset);
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }
}
