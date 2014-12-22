<?php

namespace TreeHouse\SwiftBundle\ObjectStore\Driver;

use Symfony\Component\Filesystem\Filesystem;
use TreeHouse\KeystoneBundle\Model\Service;
use TreeHouse\SwiftBundle\ObjectStore\DriverFactoryInterface;

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

    /**
     * @inheritdoc
     */
    public function getDriver(Service $service)
    {
        return new FilesystemDriver($this->filesystem, sprintf('%s/%s', $this->storeRoot, $service->getName()));
    }
}
