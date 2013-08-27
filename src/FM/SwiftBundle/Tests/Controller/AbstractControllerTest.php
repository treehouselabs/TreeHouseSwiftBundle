<?php

namespace FM\SwiftBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractControllerTest extends WebTestCase
{
    protected $user;
    protected $password;

    protected $client;
    protected $token;

    public function setUp()
    {
        static::$kernel = static::createKernel();
        static::$kernel->boot();

        if (!$this->user) {

            $this->password = uniqid();

            $manager = static::$kernel->getContainer()->get('fm_keystone.user_manager');
            $manipulator = static::$kernel->getContainer()->get('fm_keystone.user_manipulator');

            if ($user = $manager->findUserByUsername('test')) {
                $manager->deleteUser($user);
            }

            $user = $manipulator->create('test', $this->password, 'test@example.org', true);

            $manipulator->addRole('test', 'ROLE_USER');
            $manipulator->changePassword('test', $this->password);

            $this->user = $user;
        }
    }

    public function getToken()
    {
        if (!$this->token) {
            $data = array(
                'auth' => array(
                    'passwordCredentials' => array(
                        'username' => $this->user->getUsername(),
                        'password' => $this->password,
                    )
                ),
            );

            $client = static::createClient();
            $client->request('POST', $this->getRoute('get_token'), array(), array(), array('Content-Type' => 'application/json'), json_encode($data));
            $response = json_decode($client->getResponse()->getContent(), true);

            $this->token = $response['access']['token']['id'];
        }

        return $this->token;
    }

    /**
     * @param $method
     * @param $uri
     * @param array $parameters
     * @param array $files
     * @param array $server
     * @param null $content
     * @return Response
     */
    public function request($method, $uri, array $parameters = array(), array $files = array(), array $server = array(), $content = null)
    {
        if (!$this->client) {
            $factory = static::$kernel->getContainer()->get('fm_swift.store_factory');
            $service = null;
            foreach (static::$kernel->getContainer()->get('fm_keystone.service_manager')->findAll() as $srv) {
                if ($factory->supports($srv)) {
                    $service = $srv;
                    break;
                }
            }

            if (null === $service) {
                throw new \RuntimeException('No supported Keystone service found');
            }

            $url = $service->getEndpoints()->first()->getAdminUrl();
            $this->client = static::createClient(array(), array('HTTP_HOST' => parse_url($url, PHP_URL_HOST)));
        }

        $server = array_merge(array('HTTP_X-Auth-Token' => $this->getToken()), $server);

        $this->client->request($method, $uri, $parameters, $files, $server, $content, false);

        return $this->client->getResponse();
    }

    public function getRoute($name, array $parameters = array())
    {
        return static::$kernel->getContainer()->get('router')->generate($name, $parameters);
    }
}
