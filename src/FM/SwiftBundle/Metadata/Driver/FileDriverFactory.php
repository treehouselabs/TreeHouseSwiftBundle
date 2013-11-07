<?php

namespace FM\SwiftBundle\Metadata\Driver;

use FM\KeystoneBundle\Model\Service;
use FM\SwiftBundle\Metadata\Driver\FileDriver;
use FM\SwiftBundle\Metadata\DriverFactoryInterface;

class FileDriverFactory implements DriverFactoryInterface
{
    protected $root;

    public function __construct($root)
    {
        $this->root = $root;
    }

    public function getDriver(Service $service)
    {
        return new FileDriver(sprintf('%s/%s', $this->root, $service->getName()));
    }
}
