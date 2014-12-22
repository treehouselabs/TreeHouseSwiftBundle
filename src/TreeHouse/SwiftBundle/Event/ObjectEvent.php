<?php

namespace TreeHouse\SwiftBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use TreeHouse\SwiftBundle\ObjectStore\Object as StoreObject;

class ObjectEvent extends Event
{
    /**
     * @var StoreObject
     */
    protected $object;

    /**
     * @param StoreObject $object
     */
    public function __construct(StoreObject $object)
    {
        $this->object = $object;
    }

    /**
     * @return StoreObject
     */
    public function getObject()
    {
        return $this->object;
    }
}
