<?php

namespace FM\SwiftBundle\ObjectStore\Driver;

use Symfony\Component\Filesystem\Filesystem;
use FM\KeystoneBundle\Model\Service;
use FM\SwiftBundle\ObjectStore\Driver\FilesystemDriver;
use FM\SwiftBundle\ObjectStore\DriverFactoryInterface;

class FilesystemDriverFactory implements DriverFactoryInterface
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var string
     */
    protected $storeRoot;

    /**
     * @param Filesystem $filesystem
     * @param string     $storeRoot
     */
    public function __construct(Filesystem $filesystem, $storeRoot)
    {
        $this->filesystem = $filesystem;
        $this->storeRoot  = rtrim($storeRoot, '/');
    }

    public function getDriver(Service $service)
    {
        return new FilesystemDriver($this->filesystem, sprintf('%s/%s', $this->storeRoot, $service->getId()));
    }
}
