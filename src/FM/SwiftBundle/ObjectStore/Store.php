<?php

namespace FM\SwiftBundle\ObjectStore;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use FM\KeystoneBundle\Entity\Service;

/**
 * Main class to handle containers and objects.
 */
class Store
{
    private $service;
    private $storeRoot;
    private $filesystem;
    private $metadata;

    /**
     * Constructor.
     *
     * @param Service    $service
     * @param Filesystem $filesystem
     * @param string     $storeRoot
     * @param Metadata   $metadata
     */
    public function __construct(Service $service, Filesystem $filesystem, $storeRoot, Metadata $metadata)
    {
        $this->service = $service;
        $this->storeRoot = sprintf('%s/%s', rtrim($storeRoot, '/'), $service->getId());
        $this->filesystem = $filesystem;
        $this->metadata = $metadata;

        if (!is_dir($this->storeRoot)) {
            $this->filesystem->mkdir($this->storeRoot);
        }
    }

    /**
     * Returns the absolute path to the store root, where the service folders
     * are located.
     *
     * @return string
     */
    public function getRoot()
    {
        return $this->storeRoot;
    }

    /**
     * Returns the encoded filename for an object.
     *
     * @param  string $object
     * @return string
     */
    public function getObjectFilename($object)
    {
        return urlencode($object);
    }

    /**
     * Returns the directory path for a container.
     *
     * @param  string  $container
     * @param  boolean $absolute
     * @return string
     */
    public function getContainerPath($container, $absolute = true)
    {
        $hash = md5($container);
        $path = $hash{0} . '/' . $hash{1} . '/' . $hash{2} . '/' . substr($hash, 3);

        return $absolute ? sprintf('%s/%s', $this->getRoot(), $path) : $path;
    }

    /**
     * Returns the path to an object.
     *
     * @param  string  $container
     * @param  string  $object
     * @param  boolean $absolute
     * @return string
     */
    public function getObjectPath($container, $object, $absolute = true)
    {
        return sprintf('%s/%s', $this->getContainerPath($container, $absolute), $this->getObjectFilename($object));
    }

    /**
     * Returns a file instance for an object.
     *
     * @param  string  $container
     * @param  string  $object
     * @param  bool    $absolute
     * @return File
     */
    public function getFile($container, $object, $absolute = true)
    {
        return new File($this->getObjectPath($container, $object, $absolute));
    }

    /**
     * @see \Symfony\Component\Filesystem\Filesystem::mkdir
     */
    public function mkdir($path)
    {
        $this->filesystem->mkdir($path);
    }

    /**
     * @see \Symfony\Component\Filesystem\Filesystem::copy
     */
    public function copy($source, $destination, $overwrite)
    {
        $this->filesystem->copy($source, $destination, $overwrite);
    }

    /**
     * Removes a container or object.
     *
     * @param string $file
     */
    public function remove($file)
    {
        $this->metadata->remove($file);

        $this->filesystem->remove($file);
    }

    /**
     * Returns the metadata for a container or object.
     *
     * @param  string $object
     * @return array
     */
    public function getMetadata($object)
    {
        return $this->metadata->get($object);
    }

    /**
     * Sets metadata for a container or object. Overwrites all previously set
     * metadata.
     *
     * @param  string $object
     * @param  array  $meta
     * @return bool
     */
    public function setMetadata($object, array $meta)
    {
        return $this->metadata->set($object, $meta);
    }

    /**
     * Adds metadata for a container or object. Only appends so previous
     * metadata is kept.
     *
     * @param  string $object
     * @param  array  $meta
     * @return bool
     */
    public function addMetadata($object, array $meta)
    {
        return $this->metadata->add($object, $meta);
    }
}
