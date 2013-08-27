<?php

namespace FM\SwiftBundle\ObjectStore;

class Metadata
{
    private $xattrSupport;

    public function hasXattrSupport()
    {
        if ($this->xattrSupport === null) {
            $this->xattrSupport = extension_loaded('xattr') && xattr_supported(__FILE__);
        }

        return $this->xattrSupport;
    }

    public function getFilepath($fileOrDir)
    {
        if (is_file($fileOrDir)) {
            $metaFile = sprintf('%s.meta', $fileOrDir);
        } elseif (is_dir($fileOrDir)) {
            $metaFile = sprintf('%s/%s.meta', dirname($fileOrDir), basename($fileOrDir));
        } else {
            throw new \InvalidArgumentException(sprintf('Expecting a valid file or dir to get metadata for. Got "%s"', $fileOrDir));
        }

        return $metaFile;
    }

    public function get($object)
    {
        if ($this->hasXattrSupport()) {
            $meta = array();
            foreach (xattr_list($object) as $name) {
                $meta[$name] = xattr_get($object, $name);
            }

            return $meta;
        }

        $metaFile = $this->getFilepath($object);

        if (!file_exists($metaFile)) {
            touch($metaFile);
        }

        return (array) json_decode(file_get_contents($metaFile), true);
    }

    public function set($object, array $metadata)
    {
        if ($this->hasXattrSupport()) {
            // remove attrs that are not in supplied metadata
            foreach ($this->get($object) as $name => $value) {
                if (!array_key_exists($name, $metadata)) {
                    xattr_remove($object, $name);
                }
            }

            // set new metadata
            foreach ($metadata as $name => $value) {
                xattr_set($object, $name, $value);
            }

            return true;
        }

        $metaFile = $this->getFilepath($object);

        file_put_contents($metaFile, json_encode($metadata));

        return true;
    }

    public function add($object, array $metadata)
    {
        if ($this->hasXattrSupport()) {
            foreach ($metadata as $name => $value) {
                xattr_set($object, $name, $value);
            }

            return true;
        }

        $currentMeta = $this->get($object);

        return $this->set($object, array_merge($currentMeta, $metadata));
    }

    public function remove($object)
    {
        if ($this->hasXattrSupport()) {
            foreach (xattr_list($object) as $name) {
                xattr_remove($object, $name);
            }

            return true;
        }

        $metaFile = $this->getFilepath($object);

        if (file_exists($metaFile)) {
            return unlink($metaFile);
        }

        return false;
    }
}
