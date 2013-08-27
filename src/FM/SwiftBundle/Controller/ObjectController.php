<?php

namespace FM\SwiftBundle\Controller;

use JMS\SecurityExtraBundle\Annotation\Secure;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

use FM\SwiftBundle\SwiftEvents;
use FM\SwiftBundle\Event\ObjectEvent;

class ObjectController extends Controller
{
    protected $metaPrefix = 'X-Object-Meta-';

    /**
     * @Route("/{container}/{object}", name="head_object", requirements={"object"=".*"})
     * @Method({"HEAD"})
     */
    public function headAction(Request $request, $container, $object)
    {
        try {
            $file = $this->getFile($container, $object);

            $response = $this->getDefaultResponse(200);
            $response->setLastModified(new \DateTime('@' . $file->getMTime()));
            $response->setEtag(md5_file($file));
            $response->headers->set('Content-Length', $file->getSize());
            $response->headers->set('Content-Type', $file->getMimeType());

            if ($this->get('security.context')->isGranted('ROLE_USER')) {
                foreach ($this->getMetadata($file->getPathname()) as $name => $value) {
                    $response->headers->set($this->metaPrefix . $name, $value);
                }
            }

            return $response;

        } catch (FileNotFoundException $fnfe) {
            return $this->getDefaultResponse(404);
        }
    }

    /**
     * @Route("/{container}/{object}", name="get_object", requirements={"object"=".*"})
     * @Method({"GET"})
     */
    public function getAction(Request $request, $container, $object)
    {
        try {

            $file = $this->getFile($container, $object);

            $response = $this->getDefaultResponse(200);
            $response->setPublic();
            $response->setLastModified(new \DateTime('@' . $file->getMTime()));

            // TODO it's probably more efficient to store the checksum in the file's metadata
            $response->setETag(md5_file($file));

            if ($response->isNotModified($request)) {
                return $response;
            }

            // read entire file in memory: we could use a streamed response here,
            // however that will not be cacheable (see Symfony\Component\HttpFoundation\StreamedResponse#prepare)
            $response->setContent(file_get_contents($file->getPathname()));
            $response->headers->set('Content-Length', $file->getSize());
            $response->headers->set('Content-Type', $file->getMimeType());

            return $response;

        } catch (FileNotFoundException $fnfe) {
            return $this->getDefaultResponse(404);
        }
    }

    /**
     * @Route("/{container}/{object}", name="put_object", requirements={"object"=".*"})
     * @Method({"PUT"})
     * @Secure(roles="ROLE_USER")
     */
    public function putAction(Request $request, $container, $object)
    {
        $filename = $this->getObjectPath($container, $object);
        $dir = dirname($filename);

        if (!is_dir($dir)) {
            return $this->getDefaultResponse(422, sprintf('Container "%s" does not exist', $container));
        }

        if (!$request->headers->has('Content-Length')) {
            return $this->getDefaultResponse(411, 'Content-Length header is required');
        }

        if (!$request->headers->has('Content-Type')) {
            return $this->getDefaultResponse(411, 'Content-Type header is required');
        }

        if ($request->headers->has('X-Copy-From')) {
            $fromObject = sprintf(
                '%s/%s',
                rtrim($this->getStoreRoot(), '/'),
                $request->headers->get('X-Copy-From')
            );
            if (!is_file($fromObject)) {
                return $this->getDefaultResponse(422, 'X-Copy-From value is not an object');
            }

            $content = file_get_contents($fromObject);

        } else {
            $content = $request->getContent();
        }

        if (file_put_contents($filename, $content) === false) {
            return $this->getDefaultResponse(500, 'Error writing object');
        }

        $md5 = md5_file($filename);
        $filesize = filesize($filename);

        if ((int) $request->headers->get('Content-Length') !== (int) $filesize) {
            return $this->getDefaultResponse(422, sprintf('Content-Length (%s) header does not match entity body length (%s)', $request->headers->get('Content-Length'), $filesize));
        }

        if ($request->headers->has('ETag')) {
            if (trim($request->headers->get('ETag'), '"') !== $md5) {
                return $this->getDefaultResponse(422, 'MD5 checksum does not match supplied ETag value');
            }
        }

        $meta = array();

        $prefix = strtolower($this->metaPrefix);
        foreach ($request->headers->all() as $name => $values) {
            if (preg_match('/^' . preg_quote($this->metaPrefix) . '(.*)$/i', $name, $matches)) {
                $meta[$matches[1]] = is_array($values) ? $values[0] : $values;
            }
        }

        $this->setMetadata($filename, $meta);

        $response = $this->getDefaultResponse(201);
        $response->setEtag($md5);
        $response->headers->set('Content-Type', $request->headers->get('Content-Type'));

        $this->dispatchEvent(SwiftEvents::PUT_OBJECT, new ObjectEvent($this->getService(), $container, $object));

        return $response;
    }

    /**
     * @Route("/{container}/{object}", name="post_object", requirements={"object"=".*"})
     * @Method({"POST"})
     * @Secure(roles="ROLE_USER")
     */
    public function postAction(Request $request, $container, $object)
    {
        try {
            $file = $this->getFile($container, $object);

            $meta = array();

            $prefix = strtolower($this->metaPrefix);
            foreach ($request->headers->all() as $name => $values) {
                if (preg_match('/^' . preg_quote($this->metaPrefix) . '(.*)$/i', $name, $matches)) {
                    $meta[$matches[1]] = is_array($values) ? $values[0] : $values;
                }
            }

            $this->setMetadata($file->getPathname(), $meta);

            $this->dispatchEvent(SwiftEvents::POST_OBJECT, new ObjectEvent($this->getService(), $container, $object));

            return $this->getDefaultResponse(202);

        } catch (FileNotFoundException $fnfe) {
            return $this->getDefaultResponse(404);
        }
    }

    /**
     * @Route("/{container}/{object}", name="delete_object", requirements={"object"=".*"})
     * @Method({"DELETE"})
     * @Secure(roles="ROLE_USER")
     */
    public function deleteAction(Request $request, $container, $object)
    {
        try {
            $file = $this->getFile($container, $object);
            $this->getStore()->remove($file);

            $this->dispatchEvent(SwiftEvents::DELETE_OBJECT, new ObjectEvent($this->getService(), $container, $object));

            return $this->getDefaultResponse(204);

        } catch (FileNotFoundException $fnfe) {
            return $this->getDefaultResponse(404);
        } catch (IOException $ioe) {
            return $this->getDefaultResponse(500, 'Could not delete object');
        }
    }

    /**
     * @Route("/{container}/{object}", name="copy_object", requirements={"object"=".*"})
     * @Method({"COPY"})
     * @Secure(roles="ROLE_USER")
     */
    public function copyAction(Request $request, $container, $object)
    {
        try {

            $file = $this->getFile($container, $object);

            if (!$request->headers->has('Content-Length')) {
                return $this->getDefaultResponse(411, 'Content-Length header is required');
            }

            if (!$request->headers->has('Destination')) {
                return $this->getDefaultResponse(411, 'Destination header is required');
            }

            if (substr($request->headers->get('Destination'), 0, 1) !== '/') {
                return $this->getDefaultResponse(411, 'Destination header must be in the form of "/container/object"');
            }

            $destination = ltrim($request->headers->get('Destination'), '/');

            if (strpos($destination, '/') === false) {
                return $this->getDefaultResponse(411, 'Destination header must be in the form of "/container/object"');
            }

            list($destContainer, $destObject) = explode('/', $destination, 2);

            $destinationContainer = $this->getContainerPath($destContainer);
            $destinationObject = $this->getObjectPath($destContainer, $destObject);

            if (!is_dir($destinationContainer)) {
                return $this->getDefaultResponse(411, sprintf('Container "%s" does not exist', $destContainer));
            }

            // copy the source file
            $this->getStore()->copy($file->getPathname(), $destinationObject, true);

            // use the meta from the source object
            $meta = $this->getMetadata($file->getPathname());

            // add/replace meta headers
            $prefix = strtolower($this->metaPrefix);
            foreach ($request->headers->all() as $name => $values) {
                if (preg_match('/^' . preg_quote($this->metaPrefix) . '(.*)$/i', $name, $matches)) {
                    $meta[$matches[1]] = is_array($values) ? $values[0] : $values;
                }
            }

            // write meta
            $this->setMetadata($destinationObject, $meta);

            return $this->getDefaultResponse(201);

        } catch (FileNotFoundException $fnfe) {
            return $this->getDefaultResponse(404);
        } catch (IOException $fnfe) {
            return $this->getDefaultResponse(500, 'Could not copy object');
        }
    }
}
