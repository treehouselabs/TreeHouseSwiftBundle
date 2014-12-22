<?php

namespace TreeHouse\SwiftBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TreeHouse\KeystoneBundle\Manager\ServiceManager;
use TreeHouse\SwiftBundle\ObjectStore\ObjectStore;
use TreeHouse\SwiftBundle\Metadata\Metadata;
use TreeHouse\SwiftBundle\ObjectStore\ObjectStoreRegistry;

abstract class AbstractController
{
    /**
     * @var ObjectStoreRegistry
     */
    protected $storeRegistry;

    /**
     * @var ServiceManager
     */
    protected $serviceManager;

    /**
     * @param ObjectStoreRegistry $storeRegistry
     * @param ServiceManager      $serviceManager
     */
    public function __construct(ObjectStoreRegistry $storeRegistry, ServiceManager $serviceManager)
    {
        $this->storeRegistry  = $storeRegistry;
        $this->serviceManager = $serviceManager;
    }

    /**
     * @return string
     */
    abstract public function getMetaPrefix();

    /**
     * Finds the object store based on current request
     *
     * @param Request $request
     *
     * @return ObjectStore
     */
    public function getObjectStore(Request $request)
    {
        $service = $this->serviceManager->findServiceByEndpoint($request->getUri());

        return $this->storeRegistry->getObjectStore($service);
    }

    /**
     * @param integer $code
     * @param string  $reason
     *
     * @return Response
     */
    public function getDefaultResponse($code, $reason = null)
    {
        return new Response(is_null($reason) ? Response::$statusTexts[$code] : $reason, $code);
    }

    /**
     * @param Request $request
     *
     * @return Metadata
     */
    protected function getMetadataFromRequest(Request $request)
    {
        $metadata = new Metadata();

        $regex = '/^'.preg_quote($this->getMetaPrefix()).'(.*)$/i';
        foreach ($request->headers->all() as $name => $values) {
            if (preg_match($regex, $name, $matches)) {
                $metadata->set($matches[1], is_array($values) ? $values[0] : $values);
            }
        }

        return $metadata;
    }
}
