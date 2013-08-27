<?php

namespace FM\SwiftBundle\Event;

use Symfony\Component\EventDispatcher\Event;

class ContainerEvent extends Event
{
    private $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }
}
