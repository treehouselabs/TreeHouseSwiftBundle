<?php

namespace FM\SwiftBundle\Controller;

use JMS\SecurityExtraBundle\Annotation\Secure;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use FM\SwiftBundle\Exception\NotFoundException;
use FM\SwiftBundle\Exception\IntegrityException;
use FM\SwiftBundle\Metadata\Metadata;
use FM\SwiftBundle\ObjectStore\Object;

class ObjectController extends Controller
{
    public function getMetaPrefix()
    {
        return 'X-Object-Meta-';
    }

    /**
     * @Route("/{container}/{object}", name="head_object", requirements={"object"=".*"})
     * @Method({"HEAD"})
     */
    public function headAction(Request $request, $container, $object)
    {
        $store = $this->getStore();

        if (null === $object = $store->getObject($container, $object)) {
            return $this->getDefaultResponse(404);
        }

        $file = $store->getObjectFile($object);

        $response = $this->getDefaultResponse(200);
        $response->setLastModified(new \DateTime('@' . $file->getMTime()));
        $response->setEtag($store->getObjectChecksum($object));
        $response->headers->set('Content-Length', $file->getSize());
        $response->headers->set('Content-Type', $file->getMimeType());

        if ($this->get('security.context')->isGranted('ROLE_USER')) {
            foreach ($object->getMetadata() as $name => $value) {
                $response->headers->set($this->getMetaPrefix() . $name, $value);
            }
        }

        return $response;
    }

    /**
     * @Route("/{container}/{object}", name="get_object", requirements={"object"=".*"})
     * @Method({"GET"})
     */
    public function getAction(Request $request, $container, $object)
    {
        $store = $this->getStore();

        if (null === $object = $store->getObject($container, $object)) {
            return $this->getDefaultResponse(404);
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
     * @Route("/{container}/{object}", name="put_object", requirements={"object"=".*"})
     * @Method({"PUT"})
     * @Secure(roles="ROLE_USER")
     */
    public function putAction(Request $request, $container, $object)
    {
        $store = $this->getStore();

        // get the container first, this must exist
        $containerName = $container;
        if (null === $container = $store->getContainer($containerName)) {
            return $this->getDefaultResponse(422, sprintf('Container "%s" does not exist', $containerName));
        }

        // try to get existing object, if not create a new one
        $objectName = $object;
        if (null === $object = $store->getObject($container, $objectName)) {
            $object = new Object($container, $objectName);
        }

        // validate headers
        if (!$request->headers->has('Content-Length')) {
            return $this->getDefaultResponse(411, 'Content-Length header is required');
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

            $response = $this->getDefaultResponse(201);
            $response->setEtag($store->getObjectChecksum($object));
            $response->headers->set('Content-Type', $request->headers->get('Content-Type'));

            return $response;
        } catch (NotFoundException $e) {
            return $this->getDefaultResponse(411, $e->getMessage());
        } catch (IntegrityException $e) {
            return $this->getDefaultResponse(422, $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->getDefaultResponse(411, $e->getMessage());
        }
    }

    /**
     * @Route("/{container}/{object}", name="post_object", requirements={"object"=".*"})
     * @Method({"POST"})
     * @Secure(roles="ROLE_USER")
     */
    public function postAction(Request $request, $container, $object)
    {
        $store = $this->getStore();

        // try to get existing object
        if (null === $object = $store->getObject($container, $object)) {
            return $this->getDefaultResponse(404);
        }

        // overwrite metadata
        $object->setMetadata($this->getMetadataFromRequest($request));

        $store->updateObject($object);

        return $this->getDefaultResponse(202);
    }

    /**
     * @Route("/{container}/{object}", name="delete_object", requirements={"object"=".*"})
     * @Method({"DELETE"})
     * @Secure(roles="ROLE_USER")
     */
    public function deleteAction(Request $request, $container, $object)
    {
        $store = $this->getStore();

        // try to get existing object
        if (null === $object = $store->getObject($container, $object)) {
            return $this->getDefaultResponse(404);
        }

        $store->removeObject($object);

        return $this->getDefaultResponse(204);
    }

    /**
     * @Route("/{container}/{object}", name="copy_object", requirements={"object"=".*"})
     * @Method({"COPY"})
     * @Secure(roles="ROLE_USER")
     */
    public function copyAction(Request $request, $container, $object)
    {
        $store = $this->getStore();

        // check for destination header
        if (!$request->headers->has('Destination')) {
            return $this->getDefaultResponse(411, 'Destination header is required');
        }

        // check if source exists
        if (null === $source = $store->getObject($container, $object)) {
            return $this->getDefaultResponse(404);
        }

        // check for valid destination value
        $destination = $request->headers->get('Destination');
        if (!preg_match('#^/[^/]+/[^/]+#', $destination)) {
            return $this->getDefaultResponse(411, 'Destination header must be in the form of "/container/object"');
        }

        // get the destination container/object
        list($destContainer, $name) = explode('/', ltrim($destination, '/'), 2);

        // destination container must exist
        if (null === $container = $store->getContainer($destContainer)) {
            return $this->getDefaultResponse(411, sprintf('Container "%s" does not exist', $destContainer));
        }

        // overwrite metadata if it's specified
        $metadata = $this->getMetadataFromRequest($request);
        if (!$metadata->isEmpty()) {
            $object->setMetadata($metadata);
        }

        $copy = $store->copyObject($source, $container, $name, true);

        return $this->getDefaultResponse(201);
    }

    /**
     * @param  Request $request
     * @return mixed
     */
    protected function getObjectContentFromRequest(Request $request)
    {
        if (!$request->headers->has('X-Copy-From')) {
            return $request->getContent();
        }

        $store = $this->getStore();

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
