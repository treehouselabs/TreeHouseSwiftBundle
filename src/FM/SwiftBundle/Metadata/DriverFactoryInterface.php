<?php

namespace FM\SwiftBundle\Metadata;

use FM\KeystoneBundle\Model\Service;

interface DriverFactoryInterface
{
    public function getDriver(Service $service);
}
