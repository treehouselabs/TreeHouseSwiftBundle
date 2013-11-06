<?php

namespace FM\SwiftBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller as BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FM\KeystoneBundle\Entity\Service;
use FM\SwiftBundle\Keystone\ServiceAware;
use FM\SwiftBundle\ObjectStore\Store;
use FM\SwiftBundle\Metadata\Metadata;

abstract class Controller extends BaseController implements ServiceAware
{
    /**
     * @var Service
     */
    protected $service;

    /**
     * @return string
     */
    abstract public function getMetaPrefix();

    /**
     * @param Service $service
     */
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

    public function getDefaultResponse($code, $reason = null)
    {
        return new Response(is_null($reason) ? Response::$statusTexts[$code] : $reason, $code);
    }

    /**
     * @return Store
     */
    public function getStore()
    {
        return $this->get('fm_swift.object_store.factory')->getObjectStore($this->getService());
    }

    /**
     * @param  Request  $request
     * @return Metadata
     */
    protected function getMetadataFromRequest(Request $request)
    {
        $metadata = new Metadata();

        $regex = '/^' . preg_quote($this->getMetaPrefix()) . '(.*)$/i';
        foreach ($request->headers->all() as $name => $values) {
            if (preg_match($regex, $name, $matches)) {
                $metadata->set($matches[1], is_array($values) ? $values[0] : $values);
            }
        }

        return $metadata;
    }
}
