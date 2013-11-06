<?php

namespace FM\SwiftBundle\Metadata\Driver;

use FM\KeystoneBundle\Model\Service;
use FM\SwiftBundle\Metadata\Driver\XattrDriver;
use FM\SwiftBundle\Metadata\DriverFactoryInterface;
use FM\SwiftBundle\ObjectStore\Driver\FilesystemDriverFactory;

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

    public function getDriver(Service $service)
    {
        return new XattrDriver($this->driverFactory->getDriver($service));
    }
}
