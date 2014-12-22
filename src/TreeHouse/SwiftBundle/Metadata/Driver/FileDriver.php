<?php

namespace TreeHouse\SwiftBundle\Metadata\Driver;

use TreeHouse\SwiftBundle\Metadata\DriverInterface;
use TreeHouse\SwiftBundle\Metadata\Metadata;

class FileDriver implements DriverInterface
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
     * @inheritdoc
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
     * @inheritdoc
     */
    public function set($path, Metadata $metadata)
    {
        $metaFile = $this->getMetaFile($path);

        return false !== file_put_contents($metaFile, json_encode($metadata->all()));
    }

    /**
     * @inheritdoc
     */
    public function add($path, Metadata $metadata)
    {
        $meta = $this->get($path);
        $meta->add($metadata->all());

        return $this->set($path, $meta);
    }

    /**
     * @inheritdoc
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
     * @param string $path
     *
     * @return string
     */
    protected function getMetaFile($path)
    {
        return sprintf('%s/%s/%s.meta', $this->root, dirname($path), basename($path));
    }
}
