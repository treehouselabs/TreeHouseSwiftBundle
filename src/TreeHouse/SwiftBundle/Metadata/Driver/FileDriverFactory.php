<?php

namespace TreeHouse\SwiftBundle\Metadata\Driver;

use TreeHouse\KeystoneBundle\Model\Service;
use TreeHouse\SwiftBundle\Metadata\DriverFactoryInterface;

class FileDriverFactory implements DriverFactoryInterface
{
    /**
     * @var string
     */
    protected $root;

    /**
     * @param string $root
     */
    public function __construct($root)
    {
        $this->root = $root;
    }

    /**
     * @param Service $service
     *
     * @return FileDriver
     */
    public function getDriver(Service $service)
    {
        return new FileDriver(sprintf('%s/%s', $this->root, $service->getName()));
    }
}
