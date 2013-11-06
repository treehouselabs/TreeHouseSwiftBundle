<?php

namespace FM\SwiftBundle\ObjectStore;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use FM\SwiftBundle\SwiftEvents;
use FM\SwiftBundle\Event\ContainerEvent;
use FM\SwiftBundle\Event\ObjectEvent;
use FM\SwiftBundle\Exception\DuplicateException;
use FM\SwiftBundle\Exception\NotFoundException;
use FM\SwiftBundle\ObjectStore\DriverInterface as StoreDriverInterface;
use FM\SwiftBundle\Metadata\DriverInterface as MetadataDriverInterface;

/**
 * Main class to handle containers and objects.
 */
class ObjectStore
{
    /**
     * @var StoreDriverInterface
     */
    protected $storeDriver;

    /**
     * @var MetadataDriverInterface
     */
    protected $metadataDriver;

    /**
     * @var EventDispatcher
     */
    protected $eventDispatcher;

    /**
     * Constructor.
     *
     * @param StoreDriverInterface    $storeDriver
     * @param MetadataDriverInterface $metadataDriver
     */
    public function __construct(StoreDriverInterface $storeDriver, MetadataDriverInterface $metadataDriver)
    {
        $this->storeDriver     = $storeDriver;
        $this->metadataDriver  = $metadataDriver;
        $this->eventDispatcher = new EventDispatcher();
    }

    /**
     * @return EventDispatcher
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * @param  string    $name
     * @return Container
     */
    public function getContainer($name)
    {
        if (!$this->containerExists($name)) {
            return null;
        }

        $container = new Container($name);
        $container->setMetadata($this->metadataDriver->get($container->getPath()));

        return $container;
    }

    /**
     * @param  string  $container
     * @return boolean
     */
    public function containerExists($container)
    {
        if (is_string($container)) {
            $container = new Container($container);
        }

        if (!$container instanceof Container) {
            throw new \InvalidArgumentException('containerExists expects a Container instance or a string');
        }

        return $this->storeDriver->containerExists($container);
    }

    /**
     * @param string $container
     */
    public function createContainer(Container $container)
    {
        if ($this->storeDriver->containerExists($container)) {
            throw new DuplicateException(sprintf('Container "%s" already exists', $container->getPath()));
        }

        $this->storeDriver->createContainer($container);
        $this->metadataDriver->set($container->getPath(), $container->getMetadata());

        $this->dispatchEvent(SwiftEvents::CREATE_CONTAINER, new ContainerEvent($container));
    }

    /**
     * @param Container $container
     */
    public function updateContainer(Container $container)
    {
        if (!$this->storeDriver->containerExists($container)) {
            throw new NotFoundException(sprintf('Container "%s" does not exist', $container->getPath()));
        }

        $this->metadataDriver->set($container->getPath(), $container->getMetadata());

        $this->dispatchEvent(SwiftEvents::UPDATE_CONTAINER, new ContainerEvent($container));
    }

    /**
     * @param string $container
     */
    public function removeContainer(Container $container)
    {
        if (!$this->storeDriver->containerExists($container)) {
            throw new NotFoundException(sprintf('Container "%s" does not exist', $container->getPath()));
        }

        $this->storeDriver->removeContainer($container);
        $this->metadataDriver->remove($container->getPath());

        $this->dispatchEvent(SwiftEvents::REMOVE_CONTAINER, new ContainerEvent($container));
    }

    /**
     * @param  string        $container
     * @param  string        $prefix
     * @param  string        $delimiter
     * @param  string        $marker
     * @param  string        $endMarker
     * @return ContainerList
     */
    public function listContainer(Container $container, $prefix = null, $delimiter = null, $marker = null, $endMarker = null, $limit = 10000)
    {
        if (!$this->storeDriver->containerExists($container)) {
            throw new NotFoundException(sprintf('Container "%s" does not exist', $container->getPath()));
        }

        return $this->storeDriver->listContainer($container, $prefix, $delimiter, $marker, $endMarker, $limit);
    }

    /**
     * @param  string  $container
     * @param  string  $object
     * @return boolean
     */
    public function objectExists($container, $name)
    {
        if (is_string($container)) {
            $container = new Container($container);
        }

        if (!$container instanceof Container) {
            throw new \InvalidArgumentException('objectExists expects a Container instance or a string');
        }

        return $this->storeDriver->objectExists(new Object($container, $name));
    }

    /**
     * @param string $container
     * @param string $object
     * @param string $content
     */
    public function getObject($container, $name)
    {
        if (is_string($container)) {
            $container = new Container($container);
        }

        if (!$this->objectExists($container, $name)) {
            return null;
        }

        $object = new Object($container, $name);
        $object->setMetadata($this->metadataDriver->get($object->getPath()));

        return $object;
    }

    /**
     * @param string $container
     * @param string $object
     * @param string $content
     */
    public function updateObject(Object $object, $content = null, $checksum = null)
    {
        // update content if given
        if ($content) {
            $this->storeDriver->updateObject($object, $content, $checksum);
        }

        $this->metadataDriver->set($object->getPath(), $object->getMetadata());

        $this->dispatchEvent(SwiftEvents::UPDATE_OBJECT, new ObjectEvent($object));
    }

    /**
     * @param  string $container
     * @param  string $object
     * @param  string $destination
     * @param  string $name
     * @param  string $overwrite
     * @return string
     */
    public function copyObject(Object $source, Container $destination, $name, $overwrite = true)
    {
        if (!$this->storeDriver->objectExists($source)) {
            throw new NotFoundException(sprintf('Object "%s" does not exist', $source->getPath()));
        }

        $object = $this->storeDriver->copyObject($source, $destination, $name, $overwrite);

        $meta = $this->metadataDriver->get($source->getPath());
        $this->metadataDriver->set($object->getPath(), $meta);

        $this->dispatchEvent(SwiftEvents::COPY_OBJECT, new ObjectEvent($object));

        return $object;
    }

    /**
     * @param string $container
     * @param string $object
     */
    public function removeObject(Object $object)
    {
        if (!$this->storeDriver->objectExists($object)) {
            throw new NotFoundException(sprintf('Object "%s" does not exist', $object->getPath()));
        }

        $this->storeDriver->removeObject($object);
        $this->metadataDriver->remove($object->getPath());

        $this->dispatchEvent(SwiftEvents::REMOVE_OBJECT, new ObjectEvent($object));
    }

    /**
     * @param  Object $object
     * @return string
     */
    public function getObjectChecksum(Object $object)
    {
        return $this->storeDriver->getObjectChecksum($object);
    }

    /**
     * @param  Object $object
     * @return \Symfony\Component\HttpFoundation\File\File
     */
    public function getObjectFile(Object $object)
    {
        return $this->storeDriver->getObjectFile($object);
    }

    /**
     * @param  string $name
     * @param  Event  $event
     */
    protected function dispatchEvent($name, Event $event)
    {
        $this->eventDispatcher->dispatch($name, $event);
    }
}
