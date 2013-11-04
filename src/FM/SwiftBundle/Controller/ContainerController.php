<?php

namespace FM\SwiftBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use JMS\SecurityExtraBundle\Annotation\Secure;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Finder\Finder;

use FM\SwiftBundle\SwiftEvents;
use FM\SwiftBundle\Event\ContainerEvent;

class ContainerController extends Controller
{
    protected $metaPrefix = 'X-Container-Meta-';

    /**
     * @Route("/{container}", name="head_container")
     * @Method({"HEAD"})
     * @Secure(roles="ROLE_USER")
     */
    public function headAction(Request $request, $container)
    {
        $dir = $this->getContainerPath($container);

        if (is_dir($dir)) {

            $response = $this->getDefaultResponse(204);
            $response->headers->set('Content-type', 'text/html');
            $response->headers->set('X-Container-Read', '.r:*');

            $meta = $this->getMetadata($dir);
            foreach ($meta as $name => $value) {
                $response->headers->set($this->metaPrefix . $name, $value);
            }

            return $response;
        }

        return $this->getDefaultResponse(404);
    }

    /**
     * @Route("/{container}", name="get_container")
     * @Method({"GET"})
     * @Secure(roles="ROLE_USER")
     */
    public function getAction(Request $request, $container)
    {
        $dir = $this->getContainerPath($container);

        if (is_dir($dir)) {
            $query = $request->query;

            $prefix    = $query->has('prefix') ? urldecode($query->get('prefix')) : null;
            $delimiter = $query->has('delimiter') ? urldecode($query->get('delimiter')) : null;
            $marker    = $query->has('marker') ? urldecode($query->get('marker')) : null;
            $endMarker = $query->has('end_marker') ? urldecode($query->get('end_marker')) : null;

            $files = new Finder();
            $files->in($dir);
            $files->sortByName();
            $files->notName('/\.meta$/');

            if ($prefix) {
                $files->name('/^' . preg_quote(urlencode($prefix)) . '/');
            }

            if ($marker) {
                $encodedMarker = urlencode($marker);
                $files->filter(function (\SplFileInfo $file) use ($encodedMarker) {
                    return strcmp($file->getBasename(), $encodedMarker) >= 0;
                });
            }

            if ($endMarker) {
                $encodedMarker = urlencode($endMarker);
                $files->filter(function (\SplFileInfo $file) use ($encodedMarker) {
                    return strcmp($file->getBasename(), $encodedMarker) < 0;
                });
            }

            $count = 0;
            $bytes = 0;
            $limit = (int) $query->get('limit', 10000);

            $filelist = array();

            $lookahead = false;

            if ($delimiter && $prefix && (substr($prefix, -1) === $delimiter)) {
                $lookahead = true;
            }

            foreach ($files as $file) {
                $count++;
                $bytes += $file->getSize();
                $basename = urldecode($file->getBasename());

                if ($delimiter) {
                    $baseparts = array();

                    // explode on delimiter
                    $parts = explode($delimiter, $basename);

                    // first part is the part behind the delimiter
                    $baseparts[] = array_shift($parts);

                    if ($lookahead) {
                        // we want the part after the delimiter, but there are no more parts
                        if (empty($parts)) {
                            continue;
                        }

                        // get the first part after the first delimiter
                        $baseparts[] = array_shift($parts);
                    }

                    // if there are more parts after this, we have a directory.
                    // add an empty part to enforce delimiter ending
                    if (!empty($parts)) {
                        $baseparts[] = '';
                    }

                    $basename = implode($delimiter, $baseparts);

                    if (in_array($basename, $filelist)) {
                        continue;
                    }
                }

                $filelist[] = $basename;

                if ($count >= $limit) {
                    break;
                }
            }

            $response = $this->getDefaultResponse(200);
            $response->headers->set('X-Container-Read', '.r:*');
            $response->headers->set('X-Container-Object-Count', $count);
            $response->headers->set('X-Container-Bytes-Used', $bytes);
            $response->setContent(implode("\n", $filelist));

            return $response;
        }

        return $this->getDefaultResponse(404);
    }

    /**
     * @Route("/{container}", name="put_container")
     * @Method({"PUT"})
     * @Secure(roles="ROLE_USER")
     */
    public function putAction(Request $request, $container)
    {
        $dir = $this->getContainerPath($container);

        if (is_dir($dir)) {
            $this->dispatchEvent(SwiftEvents::PUT_CONTAINER, new ContainerEvent($container));

            return $this->getDefaultResponse(202);
        }

        try {
            $this->getStore()->mkdir($dir);
            $this->dispatchEvent(SwiftEvents::PUT_CONTAINER, new ContainerEvent($container));

            return $this->getDefaultResponse(201);

        } catch (IOException $ioe) {
            return $this->getDefaultResponse(500, 'Error creating container');
        }
    }

    /**
     * @Route("/{container}", name="post_container")
     * @Method({"POST"})
     * @Secure(roles="ROLE_USER")
     */
    public function postAction(Request $request, $container)
    {
        $dir = $this->getContainerPath($container);

        if (!is_dir($dir)) {
            $this->dispatchEvent(SwiftEvents::POST_CONTAINER, new ContainerEvent($container));

            return $this->getDefaultResponse(404);
        }

        $meta = array();

        $prefix = strtolower($this->metaPrefix);

        foreach ($request->headers->all() as $name => $values) {
            if (preg_match('/^' . preg_quote($this->metaPrefix) . '(.*)$/i', $name, $matches)) {
                $meta[$matches[1]] = is_array($values) ? $values[0] : $values;
            }
        }

        $this->setMetadata($dir, $meta);

        $this->dispatchEvent(SwiftEvents::POST_CONTAINER, new ContainerEvent($container));

        return $this->getDefaultResponse(204);
    }

    /**
     * @Route("/{container}", name="delete_container")
     * @Method({"DELETE"})
     * @Secure(roles="ROLE_USER")
     */
    public function deleteAction(Request $request, $container)
    {
        $dir = $this->getContainerPath($container);

        if (!is_dir($dir)) {
            return $this->getDefaultResponse(404);
        }

        try {
            $this->getStore()->remove($dir);
            $this->dispatchEvent(SwiftEvents::DELETE_CONTAINER, new ContainerEvent($container));

            return $this->getDefaultResponse(204);

        } catch (IOException $ioe) {
            return $this->getDefaultResponse(500, 'Error removing container');
        }
    }
}
