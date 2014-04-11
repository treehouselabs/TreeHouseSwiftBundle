<?php

namespace FM\SwiftBundle\ObjectStore\Driver;

use FM\SwiftBundle\Exception\IntegrityException;
use FM\SwiftBundle\Exception\SwiftException;
use FM\SwiftBundle\ObjectStore\Container;
use FM\SwiftBundle\ObjectStore\DriverInterface;
use FM\SwiftBundle\ObjectStore\Object;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File;

class FilesystemDriver implements DriverInterface
{
    /**
     * @var string
     */
    protected $storeRoot;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @param Filesystem $filesystem
     * @param string     $storeRoot
     */
    public function __construct(Filesystem $filesystem, $storeRoot)
    {
        $this->filesystem = $filesystem;
        $this->storeRoot  = rtrim($storeRoot, '/');

        if (!is_dir($this->storeRoot)) {
            $this->filesystem->mkdir($this->storeRoot);
        }
    }

    /**
     * Returns full path.
     *
     * @param string $path
     *
     * @return string
     */
    public function getPath($path)
    {
        return sprintf('%s/%s', $this->storeRoot, $path);
    }

    /**
     * @inheritdoc
     */
    public function getContainerPath(Container $container)
    {
        return $this->getPath($container->getPath());
    }

    /**
     * @inheritdoc
     */
    public function getObjectPath(Object $object)
    {
        return $this->getPath($object->getPath());
    }

    /**
     * @inheritdoc
     */
    public function getObjectFile(Object $object)
    {
        return new File($this->getObjectPath($object));
    }

    /**
     * @inheritdoc
     */
    public function containerExists(Container $container)
    {
        return is_dir($this->getContainerPath($container));
    }

    /**
     * @inheritdoc
     */
    public function createContainer(Container $container)
    {
        $this->filesystem->mkdir($this->getContainerPath($container));
    }

    /**
     * @inheritdoc
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

        /** @var File $file */
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
     * @inheritdoc
     */
    public function removeContainer(Container $container)
    {
        $this->remove($this->getContainerPath($container));
    }

    /**
     * @inheritdoc
     */
    public function objectExists(Object $object)
    {
        return is_file($this->getObjectPath($object));
    }

    /**
     * @inheritdoc
     */
    public function updateObject(Object $object, $content, $checksum = null)
    {
        $filename = $this->getObjectPath($object);

        if (!$checksum) {
            // do a regular file dump
            $this->filesystem->dumpFile($filename, $content);
        } else {
            // write with end-to-end integrity
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
     * @inheritdoc
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
     * @inheritdoc
     */
    public function removeObject(Object $object)
    {
        $this->remove($this->getObjectFile($object));
    }

    /**
     * @inheritdoc
     */
    public function touchObject(Object $object)
    {
        touch($this->getObjectPath($object));
    }

    /**
     * @inheritdoc
     */
    public function getObjectChecksum(Object $object)
    {
        return $this->getChecksum($this->getObjectPath($object));
    }

    /**
     * @param string $path
     *
     * @return string
     */
    protected function getChecksum($path)
    {
        return md5_file($path);
    }

    /**
     * Removes a container or object.
     *
     * @throws \InvalidArgumentException When $path is outside the store root
     *
     * @param string $path
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
