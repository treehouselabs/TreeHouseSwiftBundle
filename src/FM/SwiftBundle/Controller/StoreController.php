<?php

namespace FM\SwiftBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

class StoreController extends Controller
{
    /**
     * @Route("/{container}/{path}", name="swift_object", requirements={"path"=".*"})
     * @Method({"GET"})
     */
    public function storeAction(Request $request, $container, $path)
    {
        try {

            $file = $this->getFile($container, $path);

            $response = new StreamedResponse();
            $response->setPublic();
            $response->setLastModified(new \DateTime('@' . $file->getMTime()));

            if ($response->isNotModified($request)) {
                return $response;
            }

            $response->setETag(md5_file($file));
            $response->headers->set('Content-Length', $file->getSize());
            $response->headers->set('Content-Type', $file->getMimeType());

            $fp = fopen($file->getPathname(), 'rb');
            $response->setCallback(function() use ($fp) {
                while (!feof($fp)) {
                    echo fread($fp, 8192);
                }
                fclose($fp);
            });

            return $response;

        } catch (FileNotFoundException $fnfe) {
            return $this->getDefaultResponse(404);
        }
    }
}
