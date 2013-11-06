<?php

namespace FM\SwiftBundle\ObjectStore;

use FM\KeystoneBundle\Model\Service;

interface DriverFactoryInterface
{
    public function getDriver(Service $service);
}
