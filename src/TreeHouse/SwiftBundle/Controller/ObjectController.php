<?php

namespace TreeHouse\SwiftBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use TreeHouse\SwiftBundle\Exception\NotFoundException;
use TreeHouse\SwiftBundle\Exception\IntegrityException;
use TreeHouse\SwiftBundle\Exception\SwiftException;
use TreeHouse\SwiftBundle\ObjectStore\Object;

class ObjectController extends AbstractController
{
    /**
     * @inheritdoc
     */
    public function getMetaPrefix()
    {
        return 'X-Object-Meta-';
    }

    /**
     * Gets the metadata for an object.
     *
     * Possible results:
     *   * 204: when successful
     *   * 404: when the object does not exist
     *
     * @param Request $request
     * @param string  $container
     * @param string  $object
     *
     * @return Response
     */
    public function headAction(Request $request, $container, $object)
    {
        $store = $this->getObjectStore($request);

        if (null === $object = $store->getObject($container, $object)) {
            return $this->getDefaultResponse(Response::HTTP_NOT_FOUND);
        }

        $file = $store->getObjectFile($object);

        $response = $this->getDefaultResponse(Response::HTTP_NO_CONTENT);
        $response->setLastModified(new \DateTime('@'.$file->getMTime()));
        $response->setEtag($store->getObjectChecksum($object));
        $response->headers->set('Content-Length', $file->getSize());
        $response->headers->set('Content-Type', $file->getMimeType());

        foreach ($object->getMetadata() as $name => $value) {
            $response->headers->set($this->getMetaPrefix().$name, $value);
        }

        return $response;
    }

    /**
     * Returns the object contents.
     *
     * Possible results:
     *   * 200: when successful
     *   * 404: when the object does not exist
     *
     * @param Request $request
     * @param string  $container
     * @param string  $object
     *
     * @return BinaryFileResponse
     */
    public function getAction(Request $request, $container, $object)
    {
        $store = $this->getObjectStore($request);

        if (null === $object = $store->getObject($container, $object)) {
            return $this->getDefaultResponse(Response::HTTP_NOT_FOUND);
        }

        $file = $store->getObjectFile($object);

        $response = new BinaryFileResponse($file);
        $response->trustXSendfileTypeHeader();
        $response->setEtag($store->getObjectChecksum($object));

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    /**
     * Creates an object and optionally its metadata.
     *
     * Possible results:
     *   * 201: when successful
     *   * 404: when the container does not exist
     *   * 411: when the Content-Length header is missing
     *   * 422: when there is an integrity violation (most likely a mismatch between checksum and content)
     *   * 500: when the object could not be stored on the server
     *
     * @param Request $request
     * @param string  $container
     * @param string  $object
     *
     * @return Response
     */
    public function putAction(Request $request, $container, $object)
    {
        $store = $this->getObjectStore($request);

        // get the container first, this must exist
        $containerName = $container;
        if (null === $container = $store->getContainer($containerName)) {
            return $this->getDefaultResponse(Response::HTTP_NOT_FOUND, sprintf('Container "%s" does not exist', $containerName));
        }

        // try to get existing object, if not create a new one
        $objectName = $object;
        if (null === $object = $store->getObject($container, $objectName)) {
            $object = new Object($container, $objectName);
        }

        // validate headers
        foreach (['Content-Length', 'Content-Type'] as $header) {
            if (!$request->headers->has($header)) {
                return $this->getDefaultResponse(Response::HTTP_LENGTH_REQUIRED, sprintf('%s header is required', $header));
            }
        }

        try {
            // get the content
            $content = $this->getObjectContentFromRequest($request);

            // overwrite metadata if it's specified
            $metadata = $this->getMetadataFromRequest($request);
            if (!$metadata->isEmpty()) {
                $object->setMetadata($metadata);
            }

            // update the object
            $checksum = $request->headers->has('ETag') ? trim($request->headers->get('ETag'), '"') : null;
            $store->updateObject($object, $content, $checksum);

            $response = $this->getDefaultResponse(Response::HTTP_CREATED);
            $response->setEtag($store->getObjectChecksum($object));
            $response->headers->set('Content-Type', $request->headers->get('Content-Type'));

            return $response;
        } catch (NotFoundException $e) {
            return $this->getDefaultResponse(Response::HTTP_NOT_FOUND, $e->getMessage());
        } catch (IntegrityException $e) {
            return $this->getDefaultResponse(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage());
        } catch (SwiftException $e) {
            return $this->getDefaultResponse(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
        }
    }

    /**
     * Updates an object's metadata
     *
     * Possible results:
     *   * 202: when successful
     *   * 404: when the object does not exist
     *
     * @param Request $request
     * @param string  $container
     * @param string  $object
     *
     * @return Response
     */
    public function postAction(Request $request, $container, $object)
    {
        $store = $this->getObjectStore($request);

        // try to get existing object
        if (null === $object = $store->getObject($container, $object)) {
            return $this->getDefaultResponse(Response::HTTP_NOT_FOUND);
        }

        // overwrite metadata
        $object->setMetadata($this->getMetadataFromRequest($request));

        $store->updateObject($object);

        return $this->getDefaultResponse(Response::HTTP_ACCEPTED);
    }

    /**
     * Deletes an object
     *
     * Possible results:
     *   * 204: when successful
     *   * 404: when the object does not exist
     *
     * @param Request $request
     * @param string  $container
     * @param string  $object
     *
     * @return Response
     */
    public function deleteAction(Request $request, $container, $object)
    {
        $store = $this->getObjectStore($request);

        // try to get existing object
        if (null === $object = $store->getObject($container, $object)) {
            return $this->getDefaultResponse(Response::HTTP_NOT_FOUND);
        }

        $store->removeObject($object);

        return $this->getDefaultResponse(Response::HTTP_NO_CONTENT);
    }

    /**
     * Copies an object to a different container
     *
     * Possible results:
     *   * 201: when successful
     *   * 400: when the destination header is missing or malformed
     *   * 404: when the object or destination container does not exist
     *
     * @param Request $request
     * @param string  $container
     * @param string  $object
     *
     * @return Response
     */
    public function copyAction(Request $request, $container, $object)
    {
        $store = $this->getObjectStore($request);

        // check if source exists
        if (null === $source = $store->getObject($container, $object)) {
            return $this->getDefaultResponse(Response::HTTP_NOT_FOUND);
        }

        // check for destination header
        if (!$request->headers->has('Destination')) {
            return $this->getDefaultResponse(Response::HTTP_BAD_REQUEST, 'Destination header is required');
        }

        // check for valid destination value
        $destination = $request->headers->get('Destination');
        if (!preg_match('#^/[^/]+/[^/]+#', $destination)) {
            return $this->getDefaultResponse(Response::HTTP_BAD_REQUEST, 'Destination header must be in the form of "/container/object"');
        }

        // get the destination container/object
        list($destContainer, $name) = explode('/', ltrim($destination, '/'), 2);

        // destination container must exist
        if (null === $container = $store->getContainer($destContainer)) {
            return $this->getDefaultResponse(Response::HTTP_NOT_FOUND, sprintf('Container "%s" does not exist', $destContainer));
        }

        // add metadata if it's specified
        $metadata = $this->getMetadataFromRequest($request);

        $store->copyObject($source, $container, $name, $metadata);

        return $this->getDefaultResponse(Response::HTTP_CREATED);
    }

    /**
     * @param Request $request
     *
     * @throws NotFoundException
     *
     * @return mixed
     */
    protected function getObjectContentFromRequest(Request $request)
    {
        if (!$request->headers->has('X-Copy-From')) {
            return $request->getContent();
        }

        $store = $this->getObjectStore($request);

        // check for valid path value
        $path = $request->headers->get('X-Copy-From');
        if (!preg_match('#^/[^/]+/[^/]+#', $path)) {
            throw new \InvalidArgumentException('X-Copy-From header must be in the form of "/container/object"');
        }

        // get the source container/object
        list($containerName, $objectName) = explode('/', ltrim($path, '/'), 2);

        // source container must exist
        if (null === $container = $store->getContainer($containerName)) {
            throw new NotFoundException(sprintf('Source container "%s" does not exist', $containerName));
        }

        // source object must exist
        if (null === $object = $store->getObject($container, $objectName)) {
            throw new NotFoundException(sprintf('Source object "%s" does not exist', $path));
        }

        return file_get_contents($store->getObjectFile($object)->getPathname());
    }
}
