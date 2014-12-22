<?php

namespace TreeHouse\SwiftBundle\Metadata\Driver;

use TreeHouse\KeystoneBundle\Model\Service;
use TreeHouse\SwiftBundle\Metadata\DriverFactoryInterface;
use TreeHouse\SwiftBundle\ObjectStore\Driver\FilesystemDriverFactory;

class XattrDriverFactory implements DriverFactoryInterface
{
    /**
     * @var FilesystemDriverFactory
     */
    protected $driverFactory;

    /**
     * @param FilesystemDriverFactory $driverFactory
     */
    public function __construct(FilesystemDriverFactory $driverFactory)
    {
        $this->driverFactory = $driverFactory;
    }

    /**
     * @inheritdoc
     */
    public function getDriver(Service $service)
    {
        return new XattrDriver($this->driverFactory->getDriver($service));
    }
}
