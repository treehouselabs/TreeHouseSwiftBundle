<?php

namespace FM\SwiftBundle\Tests\Controller;

use FM\SwiftBundle\ObjectStore\Metadata;

class ContainerControllerTest extends AbstractControllerTest
{
    protected $containerName = 'test';

    public function getContainerRoute()
    {
        return $this->getRoute('get_container', array('container' => $this->containerName));
    }

    public function createContainer()
    {
        $this->request('PUT', $this->getContainerRoute());
    }

    public function deleteContainer()
    {
        $this->request('DELETE', $this->getContainerRoute());
    }

    public function testNoAuth()
    {
        $client = static::createClient();

        $client->request('GET', $this->getContainerRoute());
        $this->assertEquals(500, $client->getResponse()->getStatusCode());
    }

    public function testContainerNotFound()
    {
        $this->deleteContainer();

        $this->assertEquals(404, $this->request('GET', $this->getContainerRoute())->getStatusCode());
        $this->assertEquals(404, $this->request('POST', $this->getContainerRoute())->getStatusCode());
        $this->assertEquals(404, $this->request('HEAD', $this->getContainerRoute())->getStatusCode());
        $this->assertEquals(404, $this->request('DELETE', $this->getContainerRoute())->getStatusCode());
    }

    public function testPutContainer()
    {
        $this->deleteContainer();

        $response = $this->request('PUT', $this->getContainerRoute());
        $this->assertEquals(201, $response->getStatusCode());

        $response = $this->request('PUT', $this->getContainerRoute());
        $this->assertEquals(202, $response->getStatusCode());
    }

    public function testHeadContainer()
    {
        $this->createContainer();

        $response = $this->request('HEAD', $this->getContainerRoute());
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals(0, $response->headers->get('Content-Length'));
    }

    public function testPostContainer()
    {
        $this->createContainer();

        $response = $this->request('POST', $this->getContainerRoute());
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testGetContainer()
    {
        $this->createContainer();

        $response = $this->request('GET', $this->getContainerRoute());
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetObjects()
    {
        $this->createContainer();

        $sizes = array(
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
        );

        foreach ($sizes as $size) {
            $objectRoute = $this->getRoute('get_object', array('container' => $this->containerName, 'object' => $size));
            $this->request('PUT', $objectRoute, array(), array(), array('HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => strlen($size)), $size);
        }

        // all objects
        $this->assertObjects($sizes, array_keys($sizes));

        // prefix
        $this->assertObjects($sizes, array(0, 1, 2), array('prefix' => '100'));
        $this->assertObjects($sizes, array(6, 7, 8, 9, 10), array('prefix' => 'g'));
        $this->assertObjects($sizes, array(8, 9, 10), array('prefix' => 'g/700'));

        // delimiter, both encoded and decoded
        $this->assertObjects($sizes, array('100/', '200/', 'g/'), array('delimiter' => '/'));
        $this->assertObjects($sizes, array('100/', '200/', 'g/'), array('delimiter' => '%2F'));

        // delimiter with prefix, ending with or without delimiter
        $this->assertObjects($sizes, array('200/'), array('delimiter' => '/', 'prefix' => '200'));
        $this->assertObjects($sizes, array('200/120', '200/175', '200/295'), array('delimiter' => '/', 'prefix' => '200/'));
        $this->assertObjects($sizes, array('g/400/', 'g/700/'), array('delimiter' => '/', 'prefix' => 'g/'));

        // limit
        $this->assertObjects($sizes, array_slice($sizes, 0, 4), array('limit' => '4'));

        // marker
        $this->assertObjects($sizes, array_slice($sizes, 3), array('marker' => '200'));
        $this->assertObjects($sizes, array_slice($sizes, 0, 5), array('end_marker' => '200/295'));
        $this->assertObjects($sizes, array_slice($sizes, 6, 3), array('end_marker' => 'g/700/600', 'prefix' => 'g'));
        $this->assertObjects($sizes, array_slice($sizes, 0, 6), array('marker' => '100', 'end_marker' => 'g'));

        $this->deleteContainer();
    }

    public function assertObjects($sizes, $indexes, array $query = array())
    {
        $expectedList = array();
        foreach ($indexes as $index) {
            $expectedList[] = is_integer($index) ? $sizes[$index] : $index;
        }

        $response = $this->request('GET', $this->getContainerRoute(), $query);
        $list = explode("\n", trim($response->getContent()));
        $this->assertSame($expectedList, $list, json_encode($query));
    }

    public function testGetContainerLeavesMetadata()
    {
        $metadata = new Metadata();
        if ($metadata->hasXattrSupport()) {
            $this->markTestSkipped('Metadata files are not used when xattr support is enabled');
        }

        $this->deleteContainer();
        $this->createContainer();

        $objectRoute = $this->getRoute('get_object', array('container' => $this->containerName, 'object' => 'foo'));
        $this->request('PUT', $objectRoute, array(), array(), array('HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => strlen('foo')), 'foo');

        // get metadata
        $this->request('HEAD', $objectRoute);

        // we should have only 1 file when listing the container's objects
        $response = $this->request('GET', $this->getContainerRoute());
        $contents = explode("\n", trim($response->getContent()));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, sizeof($contents), json_encode($contents));
    }

    public function testGetSetContainerMetadata()
    {
        $this->createContainer();

        $response = $this->request('POST', $this->getContainerRoute(), array(), array(), array('HTTP_X-Container-Meta-Foo' => 'Bar'));
        $this->assertEquals(204, $response->getStatusCode());

        $response = $this->request('HEAD', $this->getContainerRoute());
        $this->assertEquals('Bar', $response->headers->get('X-Container-Meta-Foo'));
    }

    public function testDeleteContainer()
    {
        $this->createContainer();

        $response = $this->request('DELETE', $this->getContainerRoute());
        $this->assertEquals(204, $response->getStatusCode(), $response->getContent());
    }

    public function testDeleteContainerRemovesMetadata()
    {
        $this->markTestIncomplete('Mock a keystone service first');

        $this->createContainer();

        $this->request('POST', $this->getContainerRoute(), array(), array(), array('HTTP_X-Container-Meta-Foo' => 'Bar'));

        $c = static::$kernel->getContainer();
        $store = $c->get('fm_swift.store_factory')->getStore($this->service);
        $metadata = new Metadata;

        $container = $store->getContainerPath($this->service, $this->containerName);
        $metaFile = $metadata->getFilepath($container);

        $this->assertTrue(file_exists($metaFile));
        $this->request('DELETE', $this->getContainerRoute());
        $this->assertFalse(file_exists($metaFile));
    }
}
