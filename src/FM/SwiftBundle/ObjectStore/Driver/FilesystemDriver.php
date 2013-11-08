<?php

namespace FM\SwiftBundle\ObjectStore\Driver;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use FM\SwiftBundle\Exception\IntegrityException;
use FM\SwiftBundle\Exception\SwiftException;
use FM\SwiftBundle\ObjectStore\DriverInterface;
use FM\SwiftBundle\ObjectStore\Container;
use FM\SwiftBundle\ObjectStore\Object;
use FM\SwiftBundle\ObjectStore\ObjectList;

class FilesystemDriver implements DriverInterface
{
    protected $storeRoot;
    protected $filesystem;

    public function __construct(Filesystem $filesystem, $storeRoot)
    {
        $this->filesystem = $filesystem;
        $this->storeRoot  = rtrim($storeRoot, '/');

        if (!is_dir($this->storeRoot)) {
            $this->filesystem->mkdir($this->storeRoot);
        }
    }

    /**
     * Returns path.
     *
     * @param  string $path
     * @return string
     */
    public function getPath($path)
    {
        return sprintf('%s/%s', $this->storeRoot, $path);
    }

    /**
     * Returns the path to a container.
     *
     * @param  Container $container
     * @return string
     */
    public function getContainerPath(Container $container)
    {
        return $this->getPath($container->getPath());
    }

    /**
     * Returns the path to an object.
     *
     * @param  Object $object
     * @return string
     */
    public function getObjectPath(Object $object)
    {
        return $this->getPath($object->getPath());
    }

    /**
     * Returns a file instance for an object.
     *
     * @param  string $container
     * @param  string $object
     * @return File
     */
    public function getObjectFile(Object $object)
    {
        return new File($this->getObjectPath($object));
    }

    /**
     * @param  string  $container
     * @return boolean
     */
    public function containerExists(Container $container)
    {
        return is_dir($this->getContainerPath($container));
    }

    /**
     * @param string $container
     */
    public function createContainer(Container $container)
    {
        $this->filesystem->mkdir($this->getContainerPath($container));
    }

    /**
     * @param  string        $container
     * @param  string        $prefix
     * @param  string        $delimiter
     * @param  string        $marker
     * @param  string        $endMarker
     * @return string[]
     */
    public function listContainer(Container $container, $prefix = null, $delimiter = null, $marker = null, $endMarker = null, $limit = 10000)
    {
        $list = array();

        $files = new Finder();
        $files->in($this->getContainerPath($container));
        $files->sortByName();
        $files->notName('/\.meta$/');

        if ($prefix) {
            $files->name('/^' . preg_quote(urlencode($prefix)) . '/');
        }

        if ($marker) {
            $encodedMarker = urlencode($marker);
            $files->filter(function (\SplFileInfo $file) use ($encodedMarker) {
                return strcmp($file->getBasename(), $encodedMarker) >= 0;
            });
        }

        if ($endMarker) {
            $encodedMarker = urlencode($endMarker);
            $files->filter(function (\SplFileInfo $file) use ($encodedMarker) {
                return strcmp($file->getBasename(), $encodedMarker) < 0;
            });
        }

        $lookahead = false;
        if ($delimiter && $prefix && (substr($prefix, -1) === $delimiter)) {
            $lookahead = true;
        }

        foreach ($files as $file) {
            $basename = urldecode($file->getBasename());
            if ($delimiter) {
                $baseparts = array();

                // explode on delimiter
                $parts = explode($delimiter, $basename);

                // first part is the part behind the delimiter
                $baseparts[] = array_shift($parts);

                if ($lookahead) {
                    // we want the part after the delimiter, but there are no more parts
                    if (empty($parts)) {
                        continue;
                    }

                    // get the first part after the first delimiter
                    $baseparts[] = array_shift($parts);
                }

                // if there are more parts after this, we have a directory.
                // add an empty part to enforce delimiter ending
                if (!empty($parts)) {
                    $baseparts[] = '';
                }

                $basename = implode($delimiter, $baseparts);

                if (in_array($basename, $list)) {
                    continue;
                }
            }

            $list[] = $basename;

            if (sizeof($list) >= $limit) {
                break;
            }
        }

        return $list;
    }

    /**
     * @param string $container
     */
    public function removeContainer(Container $container)
    {
        $this->remove($this->getContainerPath($container));
    }

    /**
     * @param  string  $container
     * @param  string  $object
     * @return boolean
     */
    public function objectExists(Object $object)
    {
        return is_file($this->getObjectPath($object));
    }

    /**
     * @param string $container
     * @param string $object
     * @param string $content
     */
    public function updateObject(Object $object, $content, $checksum = null)
    {
        $filename = $this->getObjectPath($object);

        if (!$checksum) {
            // do a regular file dump
            $this->filesystem->dumpFile($filename, $content);
        } else {
            // write with end-to-end integrity
            $dir = dirname($filename);
            $tmpFile = tempnam(sys_get_temp_dir(), basename($filename));

            if (false === @file_put_contents($tmpFile, $content)) {
                throw new SwiftException(sprintf('Failed to write file "%s".', $filename));
            }

            if ($checksum !== $this->getChecksum($tmpFile)) {
                throw new IntegrityException('Checksum does not match supplied checksum');
            }

            $this->filesystem->rename($tmpFile, $filename, true);
        }
    }

    /**
     * @param  string $container
     * @param  string $object
     * @param  string $destination
     * @param  string $name
     * @return string
     */
    public function copyObject(Object $source, Container $destinationContainer, $name)
    {
        $sourcePath = $this->getObjectPath($source);
        $destinationObject = new Object($destinationContainer, $name);
        $destinationPath = $this->getObjectPath($destinationObject);

        // copy file including metadata
        $this->filesystem->copy($sourcePath, $destinationPath, true);
        $destinationObject->getMetadata()->add($source->getMetadata()->all());

        return $destinationObject;
    }

    /**
     * @param string $container
     * @param string $object
     */
    public function removeObject(Object $object)
    {
        $this->remove($this->getObjectFile($object));
    }

    /**
     * @param  Object $object
     * @return string
     */
    public function getObjectChecksum(Object $object)
    {
        return $this->getChecksum($this->getObjectPath($object));
    }

    /**
     * @param  string $path
     * @return string
     */
    protected function getChecksum($path)
    {
        return md5_file($path);
    }

    /**
     * Removes a container or object.
     *
     * @param string $file
     */
    protected function remove($path)
    {
        if (substr($path, 0, strlen($this->storeRoot)) !== $this->storeRoot) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Path "%s" is outside the store root ("%s")',
                    $path,
                    $this->storeRoot
                )
            );
        }

        $this->filesystem->remove($path);
    }
}
