<?php

namespace FM\SwiftBundle\Controller;

use JMS\SecurityExtraBundle\Annotation\Secure;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FM\SwiftBundle\Exception\DuplicateException;
use FM\SwiftBundle\Exception\NotFoundException;
use FM\SwiftBundle\ObjectStore\Container;

class ContainerController extends Controller
{
    public function getMetaPrefix()
    {
        return 'X-Container-Meta-';
    }

    /**
     * @Route("/{container}", name="head_container")
     * @Method({"HEAD"})
     * @Secure(roles="ROLE_USER")
     */
    public function headAction(Request $request, $container)
    {
        $store = $this->getStore();

        if (null === $container = $store->getContainer($container)) {
            return $this->getDefaultResponse(404);
        }

        $response = $this->getDefaultResponse(204);
        $response->headers->set('Content-type', 'text/html');
        $response->headers->set('X-Container-Read', '.r:*');

        foreach ($container->getMetadata() as $name => $value) {
            $response->headers->set($this->getMetaPrefix() . $name, $value);
        }

        return $response;
    }

    /**
     * @Route("/{container}", name="get_container")
     * @Method({"GET"})
     * @Secure(roles="ROLE_USER")
     */
    public function getAction(Request $request, $container)
    {
        $store = $this->getStore();

        if (null === $container = $store->getContainer($container)) {
            return $this->getDefaultResponse(404);
        }

        $query = $request->query;

        $prefix    = $query->has('prefix')     ? urldecode($query->get('prefix'))     : null;
        $delimiter = $query->has('delimiter')  ? urldecode($query->get('delimiter'))  : null;
        $marker    = $query->has('marker')     ? urldecode($query->get('marker'))     : null;
        $endMarker = $query->has('end_marker') ? urldecode($query->get('end_marker')) : null;
        $limit     = $query->getInt('limit', 10000);

        $list = $store->listContainer($container, $prefix, $delimiter, $marker, $endMarker, $limit);

        $response = $this->getDefaultResponse(200);
        $response->headers->set('X-Container-Read', '.r:*');
        $response->headers->set('X-Container-Object-Count', $list->count());
        $response->headers->set('X-Container-Bytes-Used', $list->getSize());
        $response->setContent(implode("\n", $list->getObjects()));

        return $response;
    }

    /**
     * @Route("/{container}", name="put_container")
     * @Method({"PUT"})
     * @Secure(roles="ROLE_USER")
     */
    public function putAction(Request $request, $container)
    {
        $store = $this->getStore();

        try {
            $store->createContainer(new Container($container));
        } catch (DuplicateException $e) {
            return $this->getDefaultResponse(202);
        }

        return $this->getDefaultResponse(201);
    }

    /**
     * @Route("/{container}", name="post_container")
     * @Method({"POST"})
     * @Secure(roles="ROLE_USER")
     */
    public function postAction(Request $request, $container)
    {
        $store = $this->getStore();

        if (null === $container = $store->getContainer($container)) {
            return $this->getDefaultResponse(404);
        }

        // overwrite metadata
        $container->setMetadata($this->getMetadataFromRequest($request));

        // update container
        $store->updateContainer($container);

        return $this->getDefaultResponse(204);
    }

    /**
     * @Route("/{container}", name="delete_container")
     * @Method({"DELETE"})
     * @Secure(roles="ROLE_USER")
     */
    public function deleteAction(Request $request, $container)
    {
        $store = $this->getStore();

        try {
            $store->removeContainer(new Container($container));
        } catch (NotFoundException $e) {
            return $this->getDefaultResponse(404);
        }

        return $this->getDefaultResponse(204);
    }
}
