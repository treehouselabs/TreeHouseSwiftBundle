<?php

namespace TreeHouse\SwiftBundle\Tests\Functional\Controller;

use Symfony\Component\HttpFoundation\Response;

class ObjectControllerTest extends AbstractControllerTest
{
    protected $containerName = 'test';
    protected $objectName1   = 'foo';
    protected $objectName2   = 'bar';
    protected $objectName3   = 'foobar';

    protected $path1;
    protected $path2;
    protected $path3;

    protected $file;
    protected $fileContents;
    protected $fileSize;
    protected $fileHash;

    protected function setUp()
    {
        parent::setUp();

        $this->deleteContainer();

        $this->path1 = sprintf('%s/%s', $this->containerName, $this->objectName1);
        $this->path2 = sprintf('%s/%s', $this->containerName, $this->objectName2);
        $this->path3 = sprintf('%s/%s', $this->containerName, $this->objectName3);

        $this->fileContents = 'foobar';
        $this->file = static::$kernel->getContainer()->getParameter('kernel.cache_dir').'/swift_object';

        file_put_contents($this->file, $this->fileContents);

        $this->fileHash = md5_file($this->file);
        $this->fileSize = filesize($this->file);
    }

    public function testAuth()
    {
        $client = static::createClient();

        foreach (['HEAD', 'GET', 'PUT', 'POST', 'COPY', 'DELETE'] as $method) {
            $client->request($method, $this->getObjectRoute());
            $this->assertEquals(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
        }
    }

    public function testObjectNotFound()
    {
        foreach (['HEAD', 'GET', 'POST', 'COPY', 'DELETE'] as $method) {
            $this->assertEquals(Response::HTTP_NOT_FOUND, $this->request($method, $this->getObjectRoute())->getStatusCode());
        }
    }

    public function testPutObject()
    {
        $this->createContainer();

        $response = $this->request('PUT', $this->getObjectRoute(), [], [], ['HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => $this->fileSize], $this->fileContents);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode(), $response->getContent());
    }

    public function testPutObjectNoContentLengthOrContentType()
    {
        $this->markTestIncomplete('Kernel automatically adds content length/type headers. Find a way to bypass this');

        $this->createContainer();

        $response = $this->request('PUT', $this->getObjectRoute(), [], [], ['HTTP_Content-Length' => $this->fileSize]);
        $this->assertEquals(Response::HTTP_LENGTH_REQUIRED, $response->getStatusCode());

        $response = $this->request('PUT', $this->getObjectRoute(), [], [], ['HTTP_Content-Type' => 'text/plain']);
        $this->assertEquals(Response::HTTP_LENGTH_REQUIRED, $response->getStatusCode());
    }

    public function testPutObjectWithIntegrity()
    {
        $this->createContainer();

        $response = $this->request('PUT', $this->getObjectRoute(), [], [], ['HTTP_ETag' => 'foo', 'HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => $this->fileSize], $this->fileContents);
        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), 'Mismatch between ETag and content expected');

        $response = $this->request('PUT', $this->getObjectRoute(), [], [], ['HTTP_ETag' => $this->fileHash, 'HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => $this->fileSize], $this->fileContents);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode(), 'Integrity check should be ok');

        $response = $this->request('PUT', $this->getObjectRoute(), [], [], ['HTTP_ETag' => sprintf('"%s"', $this->fileHash), 'HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => $this->fileSize], $this->fileContents);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode(), 'Should also work with a double quoted Etag header');
    }

    public function testHeadObject()
    {
        $this->createContainer();
        $this->createObject();

        $response = $this->request('HEAD', $this->getObjectRoute());

        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertEquals(0, $response->headers->get('Content-Length'));
        $this->assertTrue($response->headers->has('ETag'));
        $this->assertEquals(sprintf('"%s"', $this->fileHash), $response->getEtag(), "ETag should be a double quoted string");
    }

    public function testPostObject()
    {
        $this->createContainer();
        $this->createObject();

        $response = $this->request('POST', $this->getObjectRoute());
        $this->assertEquals(Response::HTTP_ACCEPTED, $response->getStatusCode());
    }

    public function testGetObject()
    {
        $this->createContainer();
        $this->createObject();

        $response = $this->request('GET', $this->getObjectRoute());

        $this->assertEquals(
            Response::HTTP_OK,
            $response->getStatusCode(),
            'Object GET should return a 200 response'
        );
        $this->assertEquals(
            $this->fileSize,
            $response->headers->get('Content-Length'),
            'Object GET should have a Content-Length header'
        );
        $this->assertTrue(
            $response->headers->has('ETag'),
            'Object GET should have an ETag header'
        );
        $this->assertEquals(
            sprintf('"%s"', $this->fileHash),
            $response->getEtag(),
            "ETag should be a double quoted string"
        );
        $this->assertTrue(
            $response->headers->has('Content-Type'),
            'Object GET should have a Content-Type header'
        );

        $this->expectOutputString($this->fileContents);
        $response->sendContent();
    }

    public function testGetSetObjectMetadata()
    {
        $this->createContainer();
        $this->createObject();

        $response = $this->request('POST', $this->getObjectRoute(), [], [], ['HTTP_X-Object-Meta-Foo' => 'Bar']);
        $this->assertEquals(Response::HTTP_ACCEPTED, $response->getStatusCode());

        $response = $this->request('HEAD', $this->getObjectRoute());
        $this->assertEquals('Bar', $response->headers->get('X-Object-Meta-Foo'));
    }

    public function testGetObjectWithLastModified()
    {
        $this->createContainer();
        $this->createObject();

        // wait 1 second
        sleep(1);

        $modified = gmdate('D, d M Y H:i:s T');
        $response = $this->request('GET', $this->getObjectRoute(), [], [], ['HTTP_If-Modified-Since' => $modified]);
        $this->assertEquals(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
    }

    public function testGetObjectWithETag()
    {
        $this->createContainer();
        $this->createObject();

        $etag     = $this->fileHash;
        $response = $this->request('GET', $this->getObjectRoute(), [], [], ['HTTP_If-None-Match' => sprintf('"%s"', $etag)]);
        $this->assertEquals(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
    }

    public function testGetObjectWithRange()
    {
        $this->createContainer();
        $this->createObject();

        $response = $this->request('GET', $this->getObjectRoute(), [], [], ['HTTP_Range' => 'bytes=-3']);
        $this->assertEquals(Response::HTTP_PARTIAL_CONTENT, $response->getStatusCode());

        $this->expectOutputString(substr($this->fileContents, -3));
        $response->sendContent();
    }

    public function testCopyObject()
    {
        $this->createContainer();
        $this->createObject();

        // copy it to new destination
        $response = $this->request('COPY', $this->getObjectRoute(), [], [], ['HTTP_Destination' => '/'.$this->path2, 'HTTP_Content-Length' => 0]);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
    }

    public function testCopyObjectWithMetadata()
    {
        $this->createContainer();
        $this->createObject();

        // set metadata
        $this->request('POST', $this->getObjectRoute(), [], [], ['HTTP_X-Object-Meta-Foo' => 'Bar']);

        // copy it to new destination
        $response = $this->request('COPY', $this->getObjectRoute(), [], [], ['HTTP_Destination' => '/'.$this->path2, 'HTTP_X-Object-Meta-Bar' => 'Baz', 'HTTP_X-Object-Meta-FooBar' => 'FooBaz', 'HTTP_Content-Length' => 0]);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        $response = $this->request('HEAD', $this->getObjectRoute(2));
        $this->assertEquals('Bar', $response->headers->get('X-Object-Meta-Foo'));
        $this->assertEquals('Baz', $response->headers->get('X-Object-Meta-Bar'));
        $this->assertEquals('FooBaz', $response->headers->get('X-Object-Meta-FooBar'));

        // overwrite previous metadata
        $this->request('COPY', $this->getObjectRoute(), [], [], ['HTTP_Destination' => '/'.$this->path3, 'HTTP_X-Object-Meta-Foo' => 'Baz', 'HTTP_Content-Length' => 0]);
        $response = $this->request('HEAD', $this->getObjectRoute(3));
        $this->assertEquals('Baz', $response->headers->get('X-Object-Meta-Foo'));
    }

    public function testDeleteObject()
    {
        $this->createContainer();
        $this->createObject();

        $response = $this->request('DELETE', $this->getObjectRoute());
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testDeleteObjectWithoutMetadata()
    {
        $this->deleteObjects();
        $this->createContainer();
        $this->createObject();

        // should not trigger exception
        $this->request('DELETE', $this->getObjectRoute());
    }

    protected function getContainerRoute()
    {
        return $this->getRoute('get_container', ['container' => $this->containerName]);
    }

    protected function getObjectRoute($num = 1)
    {
        return $this->getRoute('get_object', ['container' => $this->containerName, 'object' => $this->{'objectName'.$num}]);
    }

    protected function deleteContainer()
    {
        $this->deleteObjects();
        $this->request('DELETE', $this->getContainerRoute());
    }

    protected function deleteObjects()
    {
        foreach (range(1, 3) as $i) {
            $this->request('DELETE', $this->getObjectRoute($i));
        }
    }

    protected function createContainer()
    {
        $this->request('PUT', $this->getContainerRoute());
    }

    protected function createObject()
    {
        $this->request('PUT', $this->getObjectRoute(), [], [], ['HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => $this->fileSize], $this->fileContents);
    }

    protected function createObjects()
    {
        $this->request('PUT', $this->getObjectRoute(1), [], [], ['HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => $this->fileSize], $this->fileContents);
        $this->request('PUT', $this->getObjectRoute(2), [], [], ['HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => $this->fileSize], $this->fileContents);
        $this->request('PUT', $this->getObjectRoute(3), [], [], ['HTTP_Content-Type' => 'text/plain', 'HTTP_Content-Length' => $this->fileSize], $this->fileContents);
    }
}
