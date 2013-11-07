<?php

namespace FM\SwiftBundle\EventListener;

use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpFoundation\Request;
use FM\KeystoneBundle\Manager\ServiceManager;
use FM\SwiftBundle\Controller\Controller;
use FM\SwiftBundle\ObjectStore\ObjectStoreRegistry;

class ServiceListener
{
    /**
     * @var ServiceManager
     */
    protected $serviceManager;

    /**
     * @var ObjectStoreRegistry
     */
    protected $storeRegistry;

    /**
     * @param ServiceManager      $serviceManager
     * @param ObjectStoreRegistry $storeRegistry
     */
    public function __construct(ServiceManager $serviceManager, ObjectStoreRegistry $storeRegistry)
    {
        $this->serviceManager = $serviceManager;
        $this->storeRegistry  = $storeRegistry;
    }

    /**
     * @param FilterControllerEvent $event
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        $controller = $event->getController();
        $request = $event->getRequest();

        /*
         * $controller passed can be either a class or a Closure. This is not usual in Symfony2 but it may happen.
         * If it is a class, it comes in array format
         */
        if (!is_array($controller) || (!$controller[0] instanceof Controller)) {
            return;
        }

        $this->setObjectStoreForRequest($controller[0], $request);
    }

    /**
     * @param Controller $controller
     * @param Request    $request
     */
    protected function setObjectStoreForRequest(Controller $controller, Request $request)
    {
        // service based on current url
        $url = $request->getUri();
        if (null === $service = $this->serviceManager->findServiceByEndpoint($url)) {
            return;
        }

        // get object store for this service, inject it in the controller
        $store = $this->storeRegistry->getObjectStore($service);
        $controller->setObjectStore($store);
    }
}
