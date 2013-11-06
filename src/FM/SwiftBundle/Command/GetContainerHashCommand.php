<?php

namespace FM\SwiftBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetContainerHashCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('swift:container:hash')
            ->setDescription('Gets the hash for a given container')
            ->addArgument('service', InputArgument::REQUIRED, 'The service id')
            ->addArgument('container', InputArgument::REQUIRED, 'The container to hash')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $factory = $this->getContainer()->get('fm_swift.object_store.factory');
        $service = $this->getContainer()->get('fm_keystone.service_manager')->findServiceById($input->getArgument('service'));
        $container = $input->getArgument('container');

        $path = $factory->getObjectStore($service)->getContainerPath($container);

        $output->writeln(sprintf('Container <info>%s</info> is hashed into <info>%s</info>', $container, $path));
    }
}
