<?php

namespace FM\SwiftBundle\Metadata;

interface DriverInterface
{
    /**
     * @param  Container $container
     * @return array
     */
    public function get($path);

    /**
     * @param  Container $container
     * @param  array     $metadata
     * @return boolean
     */
    public function set($path, Metadata $metadata);

    /**
     * @param  Container $container
     * @param  array     $metadata
     * @return boolean
     */
    public function add($path, Metadata $metadata);

    /**
     * @param  Container $container
     * @return boolean
     */
    public function remove($path);
}
