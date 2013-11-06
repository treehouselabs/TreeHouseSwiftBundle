<?php

namespace FM\SwiftBundle\Metadata\Driver;

use FM\SwiftBundle\ObjectStore\Object;
use FM\SwiftBundle\ObjectStore\Driver\FilesystemDriver;
use FM\SwiftBundle\Metadata\DriverInterface;
use FM\SwiftBundle\Metadata\Metadata;

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

    public function get($path)
    {
        $file = $this->getFile($path);

        $meta = new Metadata();
        foreach (xattr_list($file) as $name) {
            $meta->set($name, xattr_get($file, $name));
        }

        return $meta;
    }

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
            xattr_set($object, $name, $value);
        }

        return true;
    }

    public function add($path, Metadata $metadata)
    {
        $file = $this->getFile($path);

        foreach ($metadata as $name => $value) {
            xattr_set($file, $name, $value);
        }

        return true;
    }

    public function remove($path)
    {
        // file is removed so the attributes are already removed with it
        return true;
    }

    protected function getFile($path)
    {
        return $this->driver->getPath($path);
    }
}
