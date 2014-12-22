<?php

namespace TreeHouse\SwiftBundle\ObjectStore;

use TreeHouse\SwiftBundle\Exception\IntegrityException;
use TreeHouse\SwiftBundle\Exception\SwiftException;
use TreeHouse\SwiftBundle\ObjectStore\Object as StoreObject;

interface DriverInterface
{
    /**
     * @param StoreObject $object
     *
     * @return \SplFileInfo
     */
    public function getObjectFile(StoreObject $object);

    /**
     * @param Container $container
     *
     * @return string
     */
    public function getContainerPath(Container $container);

    /**
     * @param StoreObject $object
     *
     * @return string
     */
    public function getObjectPath(StoreObject $object);

    /**
     * @param Container $container
     *
     * @return boolean
     */
    public function containerExists(Container $container);

    /**
     * @param Container $container
     *
     * @return void
     */
    public function createContainer(Container $container);

    /**
     * @param Container $container
     * @param string    $prefix
     * @param string    $delimiter
     * @param integer   $marker
     * @param integer   $endMarker
     * @param integer   $limit
     *
     * @return string[] A list of filenames
     */
    public function listContainer(Container $container, $prefix = null, $delimiter = null, $marker = null, $endMarker = null, $limit = 10000);

    /**
     * @param Container $container
     *
     * @return void
     */
    public function removeContainer(Container $container);

    /**
     * @param StoreObject $object
     *
     * @return boolean
     */
    public function objectExists(StoreObject $object);

    /**
     * @param StoreObject $object   The object to update
     * @param string      $content  The data to update the object with
     * @param string      $checksum Checksum to match the updated file against. Useful if you
     *                              want to ensure data integrity
     *
     * @throws SwiftException     When the object could not be updated
     * @throws IntegrityException When the given checksum does not match the checksum of the written file
     *
     * @return void
     */
    public function updateObject(StoreObject $object, $content, $checksum = null);

    /**
     * @param StoreObject $source
     * @param Container   $destinationContainer
     * @param string      $name
     *
     * @return StoreObject
     */
    public function copyObject(StoreObject $source, Container $destinationContainer, $name);

    /**
     * @param StoreObject $object
     *
     * @return void
     */
    public function removeObject(StoreObject $object);

    /**
     * @param StoreObject $object
     *
     * @return void
     */
    public function touchObject(StoreObject $object);

    /**
     * @param StoreObject $object
     *
     * @return string
     */
    public function getObjectChecksum(StoreObject $object);
}
