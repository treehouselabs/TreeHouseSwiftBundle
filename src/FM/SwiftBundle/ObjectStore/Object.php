<?php

namespace FM\SwiftBundle\ObjectStore;

use FM\SwiftBundle\Metadata\Metadata;

class Object
{
    /**
     * @var Metadata
     */
    protected $metadata;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var string
     */
    protected $name;

    /**
     * @param Container $container
     * @param string    $name
     * @param Metadata  $metadata
     */
    public function __construct(Container &$container, $name, Metadata $metadata = null)
    {
        $this->container = &$container;
        $this->metadata  = $metadata ?: new Metadata();
        $this->name      = $name;
    }

    /**
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
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
    public function getPath()
    {
        return sprintf('%s/%s', $this->container->getPath(), $this->getFilename());
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return urlencode($this->name);
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
}
