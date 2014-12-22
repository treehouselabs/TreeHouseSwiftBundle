<?php

namespace TreeHouse\SwiftBundle\Metadata;

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
