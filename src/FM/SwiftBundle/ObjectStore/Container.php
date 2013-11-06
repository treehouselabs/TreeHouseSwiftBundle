<?php

namespace FM\SwiftBundle\ObjectStore;

use FM\SwiftBundle\Metadata\Metadata;
use FM\SwiftBundle\Exception\InvalidNameException;

class Container
{
    /**
     * @var Metadata
     */
    protected $metadata;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $hash;

    /**
     * @var string
     */
    protected $path;

    /**
     * @param string   $name
     * @param Metadata $metadata
     */
    public function __construct($name, Metadata $metadata = null)
    {
        $this->metadata = $metadata ?: new Metadata();
        $this->setName($name);
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        // check for valid name
        $this->validateName($name);

        $this->name = $name;
        $this->hash = md5($name);
        $this->path = $this->hash{0} . '/' . $this->hash{1} . '/' . $this->hash{2} . '/' . substr($this->hash, 3);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param Metadata $metadata
     */
    public function setMetadata(Metadata $metadata)
    {
        $this->metadata = $metadata;
    }

    /**
     * @return Metadata
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    protected function validateName($name)
    {
        // TODO length must not exceed 256 bytes, which is not the same as the
        // mb_strlen function returns here.
        if ((false !== strpos($name, '/')) || (mb_strlen($name) > 256)) {
            throw new InvalidNameException($name);
        }
    }
}
