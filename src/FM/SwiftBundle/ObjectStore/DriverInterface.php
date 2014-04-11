<?php

namespace FM\SwiftBundle\ObjectStore;

use FM\SwiftBundle\Exception\IntegrityException;
use FM\SwiftBundle\Exception\SwiftException;

interface DriverInterface
{
    /**
     * @param \FM\SwiftBundle\ObjectStore\Object $object
     *
     * @return \SplFileInfo
     */
    public function getObjectFile(Object $object);

    /**
     * @param Container $container
     *
     * @return string
     */
    public function getContainerPath(Container $container);

    /**
     * @param \FM\SwiftBundle\ObjectStore\Object $object
     *
     * @return string
     */
    public function getObjectPath(Object $object);

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
     * @param \FM\SwiftBundle\ObjectStore\Object $object
     *
     * @return boolean
     */
    public function objectExists(Object $object);

    /**
     * @param \FM\SwiftBundle\ObjectStore\Object $object   The object to update
     * @param string                             $content  The data to update the object with
     * @param string                             $checksum Checksum to match the updated file against. Useful if you
     *                                                     want to ensure data integrity
     *
     * @throws SwiftException     When the object could not be updated
     * @throws IntegrityException When the given checksum does not match the checksum of the written file
     *
     * @return void
     */
    public function updateObject(Object $object, $content, $checksum = null);

    /**
     * @param \FM\SwiftBundle\ObjectStore\Object $source
     * @param Container                          $destinationContainer
     * @param string                             $name
     *
     * @return \FM\SwiftBundle\ObjectStore\Object
     */
    public function copyObject(Object $source, Container $destinationContainer, $name);

    /**
     * @param \FM\SwiftBundle\ObjectStore\Object $object
     *
     * @return void
     */
    public function removeObject(Object $object);

    /**
     * @param \FM\SwiftBundle\ObjectStore\Object $object
     *
     * @return void
     */
    public function touchObject(Object $object);

    /**
     * @param \FM\SwiftBundle\ObjectStore\Object $object
     *
     * @return string
     */
    public function getObjectChecksum(Object $object);
}
