<?php

namespace FM\SwiftBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use FM\SwiftBundle\ObjectStore\Object;

class ObjectEvent extends Event
{
    protected $object;

    public function __construct(Object $object)
    {
        $this->object = $object;
    }

    public function getObject()
    {
        return $this->object;
    }
}
