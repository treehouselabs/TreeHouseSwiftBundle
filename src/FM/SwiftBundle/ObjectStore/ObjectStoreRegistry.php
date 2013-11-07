<?php

namespace FM\SwiftBundle\ObjectStore;

use FM\KeystoneBundle\Model\Service;
use FM\SwiftBundle\ObjectStore\ObjectStore;

/**
 * Registry class to manage object stores for services.
 */
class ObjectStoreRegistry
{
    /**
     * @var array
     */
    protected $stores = array();

    /**
     * @param Service     $service
     * @param ObjectStore $store
     */
    public function addObjectStore(Service $service, ObjectStore $store)
    {
        if (!$this->supports($service)) {
            throw new \InvalidArgumentException(sprintf('Service of type "%s" is not supported for the object-store', $service->getType()));
        }

        $this->stores[$service->getName()] = $store;
    }

    /**
     * Returns cached instance of a store for a given service
     *
     * @param  Service $service
     * @return ObjectStore
     */
    public function getObjectStore(Service $service)
    {
        if (!array_key_exists($service->getName(), $this->stores)) {
            throw new \InvalidArgumentException(sprintf('Service "%s" does not have an object-store configured', $service->getName()));
        }

        return $this->stores[$service->getName()];
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
