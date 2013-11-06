<?php

namespace FM\SwiftBundle\Tests\Controller;

use FM\KeystoneBundle\Test\WebTestCase;

abstract class AbstractControllerTest extends WebTestCase
{
    public function request($method, $uri, array $parameters = array(), array $files = array(), array $server = array(), $content = null)
    {
        return $this->requestWithValidToken($method, $uri, $parameters, $files, $server, $content);
    }
}
