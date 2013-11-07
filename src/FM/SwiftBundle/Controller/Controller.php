<?php

namespace FM\SwiftBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller as BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FM\SwiftBundle\ObjectStore\ObjectStore;
use FM\SwiftBundle\Metadata\Metadata;

abstract class Controller extends BaseController
{
    /**
     * @var ObjectStore
     */
    protected $objectStore;

    /**
     * @return string
     */
    abstract public function getMetaPrefix();

    /**
     * @param ObjectStore $objectStore
     */
    public function setObjectStore(ObjectStore $objectStore)
    {
        $this->objectStore = $objectStore;
    }

    /**
     * @return ObjectStore
     * @throws \LogicException
     */
    public function getObjectStore()
    {
        if ($this->objectStore === null) {
            throw new \LogicException(
                'No service set on the controller. This is likely due to a mismatch in the request url and the service public-url'
            );
        }

        return $this->objectStore;
    }

    public function getDefaultResponse($code, $reason = null)
    {
        return new Response(is_null($reason) ? Response::$statusTexts[$code] : $reason, $code);
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
