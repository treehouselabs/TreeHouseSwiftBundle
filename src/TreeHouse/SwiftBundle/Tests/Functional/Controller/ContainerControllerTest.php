<?php

namespace TreeHouse\SwiftBundle\Tests\Functional\Controller;

use Symfony\Component\HttpFoundation\Response;

class ContainerControllerTest extends AbstractControllerTest
{
    /**
     * @var string
     */
    protected $containerName = 'test';

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->deleteContainer();
    }

    public function testAuth()
    {
        $client = static::createClient();

        foreach (['HEAD', 'GET', 'PUT', 'POST', 'DELETE'] as $method) {
            $client->request($method, $this->getContainerRoute());
            $this->assertEquals(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
        }
    }

    public function testHeadContainer()
    {
        // no container: 404
        $response = $this->request('HEAD', $this->getContainerRoute('HEAD'));
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        // create container
        $this->createContainer();

        // valid response now
        $response = $this->request('HEAD', $this->getContainerRoute('HEAD'));
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertEquals(0, $response->headers->get('Content-Length'));
    }

    public function testGetSetContainerMetadata()
    {
        $this->createContainer();

        // add some metadata
        $response = $this->request('POST', $this->getContainerRoute(), [], [], ['HTTP_X-Container-Meta-Foo' => 'Bar']);
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $response = $this->request('HEAD', $this->getContainerRoute());
        $this->assertEquals('Bar', $response->headers->get('X-Container-Meta-Foo'));
    }

    public function testGetContainer()
    {
        // no container: 404
        $response = $this->request('GET', $this->getContainerRoute('GET'));
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $this->createContainer();

        $response = $this->request('GET', $this->getContainerRoute());
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testPutContainer()
    {
        // no container: 201
        $response = $this->request('PUT', $this->getContainerRoute('PUT'));
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        // existing container: 202
        $response = $this->request('PUT', $this->getContainerRoute('PUT'));
        $this->assertEquals(Response::HTTP_ACCEPTED, $response->getStatusCode());
    }

    public function testPostContainer()
    {
        // no container: 404
        $response = $this->request('POST', $this->getContainerRoute('POST'));
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        // create container
        $this->createContainer();

        // valid response now
        $response = $this->request('POST', $this->getContainerRoute());
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testGetObjects()
    {
        $this->createContainer();

        $sizes = [
            '100/100',
            '100/150',
            '100/200',
            '200/120',
            '200/175',
            '200/295',
            'g/400/150',
            'g/400/200',
            'g/700/500',
            'g/700/600',
            'g/700/700',
        ];

        foreach ($sizes as $size) {
            $objectRoute = static::getRoute('get_object', ['container' => $this->containerName, 'object' => $size]);
            $this->request('PUT', $objectRoute, [], [], ['HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => strlen($size)], $size);
        }

        // all objects
        $this->assertObjects($sizes, array_keys($sizes));

        // prefix
        $this->assertObjects($sizes, [0, 1, 2], ['prefix' => '100']);
        $this->assertObjects($sizes, [6, 7, 8, 9, 10], ['prefix' => 'g']);
        $this->assertObjects($sizes, [8, 9, 10], ['prefix' => 'g/700']);

        // delimiter, both encoded and decoded
        $this->assertObjects($sizes, ['100/', '200/', 'g/'], ['delimiter' => '/']);
        $this->assertObjects($sizes, ['100/', '200/', 'g/'], ['delimiter' => '%2F']);

        // delimiter with prefix, ending with or without delimiter
        $this->assertObjects($sizes, ['200/'], ['delimiter' => '/', 'prefix' => '200']);
        $this->assertObjects($sizes, ['200/120', '200/175', '200/295'], ['delimiter' => '/', 'prefix' => '200/']);
        $this->assertObjects($sizes, ['g/400/', 'g/700/'], ['delimiter' => '/', 'prefix' => 'g/']);

        // limit
        $this->assertObjects($sizes, array_slice($sizes, 0, 4), ['limit' => '4']);

        // marker
        $this->assertObjects($sizes, array_slice($sizes, 3), ['marker' => '200']);
        $this->assertObjects($sizes, array_slice($sizes, 0, 5), ['end_marker' => '200/295']);
        $this->assertObjects($sizes, array_slice($sizes, 6, 3), ['end_marker' => 'g/700/600', 'prefix' => 'g']);
        $this->assertObjects($sizes, array_slice($sizes, 0, 6), ['marker' => '100', 'end_marker' => 'g']);
    }

    public function testDeleteContainer()
    {
        $this->createContainer();

        $response = $this->request('DELETE', $this->getContainerRoute());
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());
    }

    /**
     * @param string $method
     *
     * @return string
     */
    protected function getContainerRoute($method = 'get')
    {
        return static::getRoute(sprintf('%s_container', strtolower($method)), ['container' => $this->containerName]);
    }

    protected function createContainer()
    {
        $this->request('PUT', $this->getContainerRoute('put'));
    }

    protected function deleteContainer()
    {
        $this->request('DELETE', $this->getContainerRoute('delete'));
    }

    protected function assertObjects($sizes, $indexes, array $query = [])
    {
        $expectedList = [];
        foreach ($indexes as $index) {
            $expectedList[] = is_integer($index) ? $sizes[$index] : $index;
        }

        $response = $this->request('GET', $this->getContainerRoute(), $query);
        $list = explode("\n", trim($response->getContent()));
        $this->assertSame($expectedList, $list, json_encode($query));
    }
}
