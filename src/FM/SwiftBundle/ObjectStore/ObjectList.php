<?php

namespace FM\SwiftBundle\ObjectStore;

class ObjectList implements \Countable
{
    /**
     * @var array
     */
    protected $objects = array();

    /**
     * @var array
     */
    protected $sizes   = array();

    /**
     * @return Object[]
     */
    public function getObjects()
    {
        return $this->objects;
    }

    public function addObject($object, $size)
    {
        $this->objects[]      = $object;
        $this->sizes[$object] = intval($size);
    }

    public function hasObject($object)
    {
        return in_array($object, $this->objects);
    }

    public function count()
    {
        return sizeof($this->objects);
    }

    public function getSize()
    {
        return array_sum($this->sizes);
    }
}
