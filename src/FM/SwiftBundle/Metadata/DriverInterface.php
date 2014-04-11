<?php

namespace FM\SwiftBundle\Metadata;

interface DriverInterface
{
    /**
     * @param string $path
     *
     * @return Metadata
     */
    public function get($path);

    /**
     * @param string   $path
     * @param Metadata $metadata
     *
     * @return boolean
     */
    public function set($path, Metadata $metadata);

    /**
     * @param string   $path
     * @param Metadata $metadata
     *
     * @return boolean
     */
    public function add($path, Metadata $metadata);

    /**
     * @param string $path
     *
     * @return boolean
     */
    public function remove($path);
}
