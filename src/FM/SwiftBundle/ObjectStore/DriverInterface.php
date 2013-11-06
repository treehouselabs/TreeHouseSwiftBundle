<?php

namespace FM\SwiftBundle\ObjectStore;

interface DriverInterface
{
    public function getObjectFile(Object $object);
    public function getContainerPath(Container $container);
    public function getObjectPath(Object $object);
    public function containerExists(Container $container);
    public function createContainer(Container $container);
    public function listContainer(Container $container, $prefix = null, $delimiter = null, $marker = null, $endMarker = null, $limit = 10000);
    public function removeContainer(Container $container);
    public function objectExists(Object $object);
    public function updateObject(Object $object, $content);
    public function copyObject(Object $source, Container $destinationContainer, $name, $overwrite = true);
    public function removeObject(Object $object);
}
