<?php

namespace FM\SwiftBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use FM\SwiftBundle\ObjectStore\Container;

class ContainerEvent extends Event
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }
}
