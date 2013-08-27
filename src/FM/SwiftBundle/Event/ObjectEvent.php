<?php

namespace FM\SwiftBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use FM\KeystoneBundle\Entity\Service;

class ObjectEvent extends Event
{
    protected $service;
    protected $container;
    protected $object;

    public function __construct(Service $service, $container, $object)
    {
        $this->service = $service;
        $this->container = $container;
        $this->object = $object;
    }

    public function getService()
    {
        return $this->service;
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function getObject()
    {
        return $this->object;
    }
}
