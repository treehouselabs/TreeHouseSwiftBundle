<?php

namespace TreeHouse\SwiftBundle\Tests\Functional\Controller;

use Symfony\Component\HttpFoundation\Response;
use TreeHouse\KeystoneBundle\Test\WebTestCase;

abstract class AbstractControllerTest extends WebTestCase
{
    /**
     * @param string $method
     * @param string $uri
     * @param array  $parameters
     * @param array  $files
     * @param array  $server
     * @param null   $content
     *
     * @return Response
     */
    public function request($method, $uri, array $parameters = [], array $files = [], array $server = [], $content = null)
    {
        $this->requestWithValidToken($method, $uri, $parameters, $files, $server, $content);

        return $this->client->getResponse();
    }
}
