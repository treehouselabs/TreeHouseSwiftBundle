<?php

namespace TreeHouse\SwiftBundle\Metadata\Driver;

use TreeHouse\SwiftBundle\ObjectStore\Driver\FilesystemDriver;
use TreeHouse\SwiftBundle\Metadata\DriverInterface;
use TreeHouse\SwiftBundle\Metadata\Metadata;

class XattrDriver implements DriverInterface
{
    /**
     * @var FilesystemDriver
     */
    protected $driver;

    /**
     * @param FilesystemDriver $driver
     */
    public function __construct(FilesystemDriver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * @inheritdoc
     */
    public function get($path)
    {
        $file = $this->getFile($path);

        $meta = new Metadata();
        foreach (xattr_list($file) as $name) {
            $meta->set($name, xattr_get($file, $name));
        }

        return $meta;
    }

    /**
     * @inheritdoc
     */
    public function set($path, Metadata $metadata)
    {
        $file = $this->getFile($path);

        // remove attrs that are not in supplied metadata
        foreach (xattr_list($file) as $name) {
            if (!$metadata->has($name)) {
                xattr_remove($file, $name);
            }
        }

        // set new metadata
        foreach ($metadata as $name => $value) {
            xattr_set($file, $name, $value);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function add($path, Metadata $metadata)
    {
        $file = $this->getFile($path);

        foreach ($metadata as $name => $value) {
            xattr_set($file, $name, $value);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function remove($path)
    {
        // file is removed so the attributes are already removed with it
        return true;
    }

    /**
     * @param string $path
     *
     * @return string
     */
    protected function getFile($path)
    {
        return $this->driver->getPath($path);
    }
}
