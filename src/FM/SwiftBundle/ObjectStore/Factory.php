<?php

namespace FM\SwiftBundle\ObjectStore;

use Symfony\Component\Filesystem\Filesystem;
use FM\KeystoneBundle\Entity\Service;

/**
 * Factory class to load object stores for services.
 */
class Factory
{
    private $storeRoot;
    private $filesystem;
    private $stores = array();

    /**
     * Constructor.
     *
     * @param Filesystem $filesystem
     * @param string     $storeRoot
     */
    public function __construct(Filesystem $filesystem, $storeRoot)
    {
        $this->storeRoot = rtrim($storeRoot, '/');
        $this->filesystem = $filesystem;

        if (!is_dir($this->storeRoot)) {
            $this->filesystem->mkdir($this->storeRoot);
        }
    }

    /**
     * Returns cached instance of a store for a given service
     *
     * @param  Service $service
     * @return Store
     */
    public function getStore(Service $service)
    {
        if (!$this->supports($service)) {
            throw new \InvalidArgumentException(sprintf('Service of type "%s" is not supported for the object-store', $service->getType()));
        }

        if (!array_key_exists($service->getId(), $this->stores)) {
            $this->stores[$service->getId()] = new Store($service, $this->filesystem, $this->storeRoot, new Metadata);
        }

        return $this->stores[$service->getId()];
    }

    /**
     * Checks if the object store supports a given service.
     *
     * @param  Service $service
     * @return boolean
     */
    public function supports(Service $service)
    {
        return $service->getType() === 'object-store';
    }
}
