<?php

namespace TreeHouse\SwiftBundle\ObjectStore;

use TreeHouse\KeystoneBundle\Model\Service;

interface DriverFactoryInterface
{
    /**
     * @param Service $service
     *
     * @return DriverInterface
     */
    public function getDriver(Service $service);
}
