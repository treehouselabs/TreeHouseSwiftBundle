<?php

namespace FM\SwiftBundle\Metadata\Driver;

use FM\SwiftBundle\Metadata\DriverInterface;
use FM\SwiftBundle\Metadata\Metadata;

class FileDriver implements DriverInterface
{
    protected $root;

    public function __construct($root)
    {
        $this->root = $root;
    }

    /**
     * @param  string $path
     * @return array
     */
    public function get($path)
    {
        $metaFile = $this->getMetaFile($path);

        if (!file_exists($metaFile)) {
            touch($metaFile);
        }

        return new Metadata((array) json_decode(file_get_contents($metaFile), true));
    }

    /**
     * @param  string  $path
     * @param  array   $metadata
     * @return boolean
     */
    public function set($path, Metadata $metadata)
    {
        $metaFile = $this->getMetaFile($path);

        return file_put_contents($metaFile, json_encode($metadata->all()));
    }

    /**
     * @param  string  $path
     * @param  array   $metadata
     * @return boolean
     */
    public function add($path, Metadata $metadata)
    {
        $meta = $this->get($path);
        $meta->add($metadata->all());

        return $this->set($path, $meta);
    }

    /**
     * @param  string  $path
     * @return boolean
     */
    public function remove($path)
    {
        $metaFile = $this->getMetaFile($path);

        if (file_exists($metaFile)) {
            return unlink($metaFile);
        }

        return false;
    }

    /**
     * @param  string                    $file
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function getMetaFile($path)
    {
        return sprintf('%s/%s/%s.meta', $this->root, dirname($path), basename($path));
    }
}
