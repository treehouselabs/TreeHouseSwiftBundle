<?php

namespace TreeHouse\SwiftBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TreeHouse\SwiftBundle\Exception\DuplicateException;
use TreeHouse\SwiftBundle\Exception\NotFoundException;
use TreeHouse\SwiftBundle\ObjectStore\Container;
use TreeHouse\SwiftBundle\ObjectStore\Object;

class ContainerController extends AbstractController
{
    /**
     * @inheritdoc
     */
    public function getMetaPrefix()
    {
        return 'X-Container-Meta-';
    }

    /**
     * Gets the metadata for a container.
     *
     * Possible results:
     *   * 204: when successful
     *   * 404: when the container does not exist
     *
     * @param Request $request
     * @param string  $container
     *
     * @return Response
     */
    public function headAction(Request $request, $container)
    {
        $store = $this->getObjectStore($request);

        if (null === $container = $store->getContainer($container)) {
            return $this->getDefaultResponse(Response::HTTP_NOT_FOUND);
        }

        $response = $this->getDefaultResponse(Response::HTTP_NO_CONTENT);
        $response->headers->set('Content-type', 'text/html');
        $response->headers->set('X-Container-Read', '.r:*');

        foreach ($container->getMetadata() as $name => $value) {
            $response->headers->set($this->getMetaPrefix().$name, $value);
        }

        return $response;
    }

    /**
     * Lists all the objects in the container.
     * Optional query parameters:
     *   * prefix:     to filter by object prefix
     *   * delimiter:  delimiter to use when you want list a hierarchy
     *   * marker:     start offset
     *   * end_marker: end offset
     *   * limit:      the number of objects to return (default 10000)
     *
     * Possible results:
     *   * 200: when successful
     *   * 404: when the container does not exist
     *
     * @param Request $request
     * @param string  $container
     *
     * @return Response
     */
    public function getAction(Request $request, $container)
    {
        $store = $this->getObjectStore($request);

        if (null === $container = $store->getContainer($container)) {
            return $this->getDefaultResponse(Response::HTTP_NOT_FOUND);
        }

        $query = $request->query;

        $prefix    = $query->has('prefix')     ? urldecode($query->get('prefix'))     : null;
        $delimiter = $query->has('delimiter')  ? urldecode($query->get('delimiter'))  : null;
        $marker    = $query->has('marker')     ? urldecode($query->get('marker'))     : null;
        $endMarker = $query->has('end_marker') ? urldecode($query->get('end_marker')) : null;
        $limit     = $query->getInt('limit', 10000);

        $objects = $store->listContainer($container, $prefix, $delimiter, $marker, $endMarker, $limit);

        $list = implode("\n", array_map(function (Object $object) {
            return $object->getName();
        }, $objects));

        $response = $this->getDefaultResponse(Response::HTTP_OK);
        $response->headers->set('X-Container-Read', '.r:*');
        $response->setContent($list);

        return $response;
    }

    /**
     * Creates a container.
     *
     * Possible results:
     *   * 201: when the container is created
     *   * 202: when the container already exists
     *
     * @param Request $request
     * @param string  $container
     *
     * @return Response
     */
    public function putAction(Request $request, $container)
    {
        $store = $this->getObjectStore($request);

        try {
            $store->createContainer(new Container($container));
        } catch (DuplicateException $e) {
            return $this->getDefaultResponse(Response::HTTP_ACCEPTED);
        }

        return $this->getDefaultResponse(Response::HTTP_CREATED);
    }

    /**
     * Updates the container's metadata.
     *
     * Possible results:
     *   * 204: when successful
     *   * 404: when the container does not exist
     *
     * @param Request $request
     * @param string  $container
     *
     * @return Response
     */
    public function postAction(Request $request, $container)
    {
        $store = $this->getObjectStore($request);

        if (null === $container = $store->getContainer($container)) {
            return $this->getDefaultResponse(Response::HTTP_NOT_FOUND);
        }

        // overwrite metadata
        $container->setMetadata($this->getMetadataFromRequest($request));

        // update container
        $store->updateContainer($container);

        return $this->getDefaultResponse(Response::HTTP_NO_CONTENT);
    }

    /**
     * Deletes a container.
     *
     * Possible results:
     *   * 204: when successful
     *   * 404: when the container does not exist
     *
     * @param Request $request
     * @param string  $container
     *
     * @return Response
     */
    public function deleteAction(Request $request, $container)
    {
        $store = $this->getObjectStore($request);

        try {
            $store->removeContainer(new Container($container));
        } catch (NotFoundException $e) {
            return $this->getDefaultResponse(Response::HTTP_NOT_FOUND);
        }

        return $this->getDefaultResponse(Response::HTTP_NO_CONTENT);
    }
}
