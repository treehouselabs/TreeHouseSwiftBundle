<?php

namespace FM\SwiftBundle\Metadata;

class Metadata implements \IteratorAggregate
{
    protected $data = array();

    public function __construct(array $data = array())
    {
        $this->data = $data;
    }

    public function all()
    {
        return $this->data;
    }

    public function get($name)
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }
    }

    public function set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function add(array $data)
    {
        $this->data = array_replace($this->data, $data);
    }

    public function has($name)
    {
        return array_key_exists($name, $this->data);
    }

    public function remove($name)
    {
        if (array_key_exists($name, $this->data)) {
            unset($this->data[$name]);
        }
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }

    public function isEmpty()
    {
        return sizeof($this->data) === 0;
    }
}
