<?php

namespace FM\SwiftBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller as BaseController;
use Symfony\Component\HttpFoundation\Response;
use FM\KeystoneBundle\Entity\Service;
use FM\SwiftBundle\Keystone\ServiceAware;
use FM\SwiftBundle\ObjectStore\Store;

class Controller extends BaseController implements ServiceAware
{
    protected $service;

    public function setService(Service $service)
    {
        $this->service = $service;
    }

    /**
     * @return Service
     * @throws \LogicException
     */
    public function getService()
    {
        if ($this->service === null) {
            throw new \LogicException(
                'No service set on the controller. This is likely due to a mismatch in the request url and the service public-url'
            );
        }

        return $this->service;
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function getDefaultResponse($code, $reason = null)
    {
        return new Response(is_null($reason) ? Response::$statusTexts[$code] : $reason, $code);
    }

    /**
     * @return Store
     */
    public function getStore()
    {
        return $this->get('fm_swift.store_factory')->getStore($this->getService());
    }

    public function getStoreRoot()
    {
        return $this->getStore()->getRoot();
    }

    public function getContainerPath($container, $absolute = true)
    {
        return $this->getStore()->getContainerPath($container, $absolute);
    }

    public function getObjectPath($container, $object, $absolute = true)
    {
        return $this->getStore()->getObjectPath($container, $object, $absolute);
    }

    public function getFile($container, $object)
    {
        return $this->getStore()->getFile($container, $object);
    }

    public function getMetadata($object)
    {
        return $this->getStore()->getMetadata($object);
    }

    public function setMetadata($object, array $metadata)
    {
        return $this->getStore()->setMetadata($object, $metadata);
    }

    protected function dispatchEvent($type, $event)
    {
        $this->get('event_dispatcher')->dispatch($type, $event);
    }
}
