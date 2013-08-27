<?php

namespace FM\SwiftBundle\EventListener;

use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use FM\KeystoneBundle\Manager\ServiceManager;

class ServiceListener
{
    protected $serviceManager;

    public function __construct(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $controller = $event->getController();
        $request = $event->getRequest();

        /*
         * $controller passed can be either a class or a Closure. This is not usual in Symfony2 but it may happen.
         * If it is a class, it comes in array format
         */
        if (!is_array($controller) || (!$controller[0] instanceof \FM\SwiftBundle\Controller\Controller)) {
            return;
        }

        // get request schema/host
        $url = $request->getSchemeAndHttpHost();

        foreach ($this->serviceManager->findAll() as $service) {
            foreach ($service->getEndpoints() as $endpoint) {
                if (rtrim($endpoint->getPublicUrl(), '/') === $url) {
                    $controller[0]->setService($service);
                    break;
                }
            }
        }
    }
}
