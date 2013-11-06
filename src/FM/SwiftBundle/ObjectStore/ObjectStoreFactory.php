<?php

namespace FM\SwiftBundle\ObjectStore;

use FM\KeystoneBundle\Entity\Service;
use FM\SwiftBundle\ObjectStore\DriverFactoryInterface as StoreDriverFactory;
use FM\SwiftBundle\Metadata\DriverFactoryInterface as MetadataDriverFactory;

/**
 * Factory class to load object stores for services.
 */
class ObjectStoreFactory
{
    /**
     * @var StoreDriverFactory
     */
    protected $storeDriverFactory;

    /**
     * @var MetadataDriverFactory
     */
    protected $metadataDriverFactory;

    /**
     * @var array
     */
    protected $stores = array();

    /**
     * @param StoreDriverInterface    $storeDriver
     * @param MetadataDriverInterface $metadataDriver
     */
    public function __construct(StoreDriverFactory $storeDriverFactory, MetadataDriverFactory $metadataDriverFactory)
    {
        $this->storeDriverFactory    = $storeDriverFactory;
        $this->metadataDriverFactory = $metadataDriverFactory;
    }

    /**
     * Returns cached instance of a store for a given service
     *
     * @param  Service $service
     * @return Store
     */
    public function getObjectStore(Service $service)
    {
        if (!$this->supports($service)) {
            throw new \InvalidArgumentException(sprintf('Service of type "%s" is not supported for the object-store', $service->getType()));
        }

        if (!array_key_exists($service->getId(), $this->stores)) {
            $storeDriver = $this->storeDriverFactory->getDriver($service);
            $metadataDriver = $this->metadataDriverFactory->getDriver($service);

            $this->stores[$service->getId()] = new ObjectStore($storeDriver, $metadataDriver);
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
