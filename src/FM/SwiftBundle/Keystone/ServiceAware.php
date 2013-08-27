<?php

namespace FM\SwiftBundle\Keystone;

use FM\KeystoneBundle\Entity\Service;

interface ServiceAware
{
    public function setService(Service $service);
    public function getService();
}
