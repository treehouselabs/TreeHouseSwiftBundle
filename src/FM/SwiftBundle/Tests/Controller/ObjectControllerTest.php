<?php

namespace FM\SwiftBundle\Tests\Controller;

use FM\SwiftBundle\ObjectStore\Metadata;

class ObjectControllerTest extends AbstractControllerTest
{
    protected $containerName = 'test';
    protected $objectName1 = 'foo';
    protected $objectName2 = 'bar';
    protected $objectName3 = 'foobar';

    protected $path1;
    protected $path2;
    protected $path3;

    protected $file;
    protected $fileContents;
    protected $fileSize;
    protected $fileHash;

    public function setUp()
    {
        parent::setUp();

        $this->path1 = sprintf('%s/%s', $this->containerName, $this->objectName1);
        $this->path2 = sprintf('%s/%s', $this->containerName, $this->objectName2);
        $this->path3 = sprintf('%s/%s', $this->containerName, $this->objectName3);

        $this->fileContents = 'foobar';
        $this->file = static::$kernel->getContainer()->getParameter('kernel.cache_dir') . '/swift_object';

        file_put_contents($this->file, $this->fileContents);

        $this->fileHash = md5_file($this->file);
        $this->fileSize = filesize($this->file);
    }

    public function getContainerRoute()
    {
        return $this->getRoute('get_container', array('container' => $this->containerName));
    }

    public function getObjectRoute($num = 1)
    {
        return $this->getRoute('get_object', array('container' => $this->containerName, 'object' => $this->{'objectName' . $num}));
    }

    public function deleteContainer()
    {
        $this->deleteObjects();
        $this->request('DELETE', $this->getContainerRoute());
    }

    public function deleteObjects()
    {
        foreach (range(1, 3) as $i) {
            $this->request('DELETE', $this->getObjectRoute($i));
        }
    }

    public function createContainer()
    {
        $this->request('PUT', $this->getContainerRoute());
    }

    public function createObject()
    {
        $this->request('PUT', $this->getObjectRoute(), array(), array(), array('HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => $this->fileSize), $this->fileContents);
    }

    public function createObjects()
    {
        $this->request('PUT', $this->getObjectRoute(1), array(), array(), array('HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => $this->fileSize), $this->fileContents);
        $this->request('PUT', $this->getObjectRoute(2), array(), array(), array('HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => $this->fileSize), $this->fileContents);
        $this->request('PUT', $this->getObjectRoute(3), array(), array(), array('HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => $this->fileSize), $this->fileContents);
    }

    public function testNoAuth()
    {
        $client = static::createClient();

        $client->request('PUT', $this->getObjectRoute());
        $this->assertEquals(500, $client->getResponse()->getStatusCode());

        $client->request('POST', $this->getObjectRoute());
        $this->assertEquals(500, $client->getResponse()->getStatusCode());

        $client->request('COPY', $this->getObjectRoute());
        $this->assertEquals(500, $client->getResponse()->getStatusCode());

        $client->request('DELETE', $this->getObjectRoute());
        $this->assertEquals(500, $client->getResponse()->getStatusCode());
    }

    public function testObjectNotFound()
    {
        $this->deleteObjects();

        $this->assertEquals(404, $this->request('GET', $this->getObjectRoute())->getStatusCode());
        $this->assertEquals(404, $this->request('POST', $this->getObjectRoute())->getStatusCode());
        $this->assertEquals(404, $this->request('HEAD', $this->getObjectRoute())->getStatusCode());
        $this->assertEquals(404, $this->request('DELETE', $this->getObjectRoute())->getStatusCode());
    }

    public function testPutObject()
    {
        $this->deleteObjects();
        $this->createContainer();

        $response = $this->request('PUT', $this->getObjectRoute(), array(), array(), array('HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => $this->fileSize), $this->fileContents);
        $this->assertEquals(201, $response->getStatusCode(), $response->getContent());
    }

    public function testPutObjectNoContentLengthOrContentType()
    {
        $this->markTestIncomplete('Kernel automatically adds content length/type headers. Find a way to bypass this');

        $this->deleteObjects();
        $this->createContainer();

        $response = $this->request('PUT', $this->getObjectRoute(), array(), array(), array('HTTP_Content-Length' => $this->fileSize));
        $this->assertEquals(411, $response->getStatusCode());

        $response = $this->request('PUT', $this->getObjectRoute(), array(), array(), array('HTTP_Content-Type' => 'text/plain'));
        $this->assertEquals(411, $response->getStatusCode());
    }

    public function testPutObjectWithIntegrity()
    {
        $this->deleteObjects();
        $this->createContainer();

        $response = $this->request('PUT', $this->getObjectRoute(), array(), array(), array('HTTP_ETag' => 'foo', 'HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => $this->fileSize), $this->fileContents);
        $this->assertEquals(422, $response->getStatusCode());

        $response = $this->request('PUT', $this->getObjectRoute(), array(), array(), array('HTTP_ETag' => $this->fileHash, 'HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => $this->fileSize), $this->fileContents);
        $this->assertEquals(201, $response->getStatusCode());

        $response = $this->request('PUT', $this->getObjectRoute(), array(), array(), array('HTTP_ETag' => sprintf('"%s"', $this->fileHash), 'HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => $this->fileSize), $this->fileContents);
        $this->assertEquals(201, $response->getStatusCode(), 'Should also work with a double quoted Etag header');
    }

    public function testHeadObject()
    {
        $this->deleteObjects();
        $this->createContainer();
        $this->createObject();

        $response = $this->request('HEAD', $this->getObjectRoute());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertGreaterThan(0, $response->headers->get('Content-Length'));
        $this->assertTrue($response->headers->has('ETag'));
        $this->assertTrue($response->headers->has('Content-Type'));
        $this->assertEquals(sprintf('"%s"', $this->fileHash), $response->getEtag(), "ETag should be a double quoted string");
    }

    public function testPostObject()
    {
        $this->deleteObjects();
        $this->createContainer();
        $this->createObject();

        $response = $this->request('POST', $this->getObjectRoute());
        $this->assertEquals(202, $response->getStatusCode());
    }

    public function testGetObject()
    {
        $this->deleteObjects();
        $this->createContainer();
        $this->createObject();

        $response = $this->request('GET', $this->getObjectRoute());
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetSetObjectMetadata()
    {
        $this->deleteObjects();
        $this->createContainer();
        $this->createObject();

        $response = $this->request('POST', $this->getObjectRoute(), array(), array(), array('HTTP_X-Object-Meta-Foo' => 'Bar'));
        $this->assertEquals(202, $response->getStatusCode());

        $response = $this->request('HEAD', $this->getObjectRoute());
        $this->assertEquals('Bar', $response->headers->get('X-Object-Meta-Foo'));
    }

    public function testConditionalGetObject()
    {
        $this->markTestIncomplete('See http://docs.openstack.org/api/openstack-object-storage/1.0/content/retrieve-object.html');
    }

    public function testCopyObject()
    {
        $this->deleteObjects();
        $this->createContainer();
        $this->createObject();

        // copy it to new destination
        $response = $this->request('COPY', $this->getObjectRoute(), array(), array(), array('HTTP_Destination' => '/' . $this->path2, 'HTTP_Content-Length' => 0));
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testCopyObjectWithMetadata()
    {
        $this->deleteObjects();
        $this->createContainer();
        $this->createObject();

        // set metadata
        $response = $this->request('POST', $this->getObjectRoute(), array(), array(), array('HTTP_X-Object-Meta-Foo' => 'Bar'));

        // copy it to new destination
        $response = $this->request('COPY', $this->getObjectRoute(), array(), array(), array('HTTP_Destination' => '/' . $this->path2, 'HTTP_X-Object-Meta-Bar' => 'Baz', 'HTTP_X-Object-Meta-FooBar' => 'FooBaz', 'HTTP_Content-Length' => 0));
        $this->assertEquals(201, $response->getStatusCode());

        $response = $this->request('HEAD', $this->getObjectRoute(2));
        $this->assertEquals('Bar', $response->headers->get('X-Object-Meta-Foo'));
        $this->assertEquals('Baz', $response->headers->get('X-Object-Meta-Bar'));
        $this->assertEquals('FooBaz', $response->headers->get('X-Object-Meta-FooBar'));

        // overwrite previous metadata
        $this->request('COPY', $this->getObjectRoute(), array(), array(), array('HTTP_Destination' => '/' . $this->path3, 'HTTP_X-Object-Meta-Foo' => 'Baz', 'HTTP_Content-Length' => 0));
        $response = $this->request('HEAD', $this->getObjectRoute(3));
        $this->assertEquals('Baz', $response->headers->get('X-Object-Meta-Foo'));
    }

    public function testDeleteObject()
    {
        $this->deleteObjects();
        $this->createContainer();
        $this->createObject();

        $response = $this->request('DELETE', $this->getObjectRoute());
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testDeleteObjectRemovesMetadata()
    {
        $this->markTestIncomplete('Mock a keystone service first');

        $this->deleteObjects();
        $this->createContainer();
        $this->createObject();

        $container = static::$kernel->getContainer();
        $store = $c->get('fm_swift.object_store.factory')->getObjectStore($this->service);
        $metadata = new Metadata;

        $object = $store->getObjectPath($this->containerName, $this->objectName1);
        $metaFile = $metadata->getFilepath($object);

        $this->assertTrue(file_exists($metaFile));
        $this->request('DELETE', $this->getObjectRoute());
        $this->assertFalse(file_exists($metaFile));
    }

    public function testDeleteObjectWithoutMetadata()
    {
        $this->deleteObjects();
        $this->createContainer();
        $this->createObject();

        // should not trigger exception
        $this->request('DELETE', $this->getObjectRoute());
    }
}
