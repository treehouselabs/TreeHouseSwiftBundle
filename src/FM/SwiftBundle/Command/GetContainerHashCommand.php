<?php

namespace FM\SwiftBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use FM\KeystoneBundle\Model\Service;
use FM\SwiftBundle\ObjectStore\Container;

class GetContainerHashCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('swift:container:hash')
            ->setDescription('Gets the hash for a given container')
            ->addArgument('service', InputArgument::REQUIRED, 'The service name')
            ->addArgument('container', InputArgument::REQUIRED, 'The container to hash')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $service = $this->getService($input->getArgument('service'));
        $store = $this->getObjectStore($service);

        $name = $input->getArgument('container');
        $container = new Container($name);
        $path = $container->getPath();

        $output->writeln(sprintf('Container <info>%s</info> is hashed into <info>%s</info>', $name, $path));
    }

    protected function getService($name)
    {
        $serviceManager = $this->getContainer()->get('fm_keystone.service_manager');
        foreach ($serviceManager->getServices() as $service) {
            if ($service->getName() === $name) {
                return $service;
            }
        }

        throw new \OutOfBoundsException(sprintf('No service named "%s" was found', $name));
    }

    protected function getObjectStore(Service $service)
    {
        $registry = $this->getContainer()->get('fm_swift.object_store.registry');
        if (null === $store = $registry->getObjectStore($service)) {
            throw new \OutOfBoundsException(sprintf('No object-store for service "%s" was found', $service->getName()));
        }

        return $store;
    }
}
