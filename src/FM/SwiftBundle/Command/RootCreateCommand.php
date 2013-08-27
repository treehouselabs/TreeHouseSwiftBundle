<?php

namespace FM\SwiftBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RootCreateCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('swift:root:create')
            ->setDescription('Creates the root directory (if it doesn\'t exist already')
            ->setDefinition(array())
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rootDir = $this->getContainer()->getParameter('fm_swift.root_dir');
        if (!is_dir($rootDir)) {
            $this->getContainer()->get('filesystem')->mkdir($rootDir);
        }

        $output->writeln(sprintf('Root dir <info>%s</info> has been created.', $rootDir));
        $output->writeln('Creating folders for all services');

        $serviceManager = $this->getContainer()->get('fm_keystone.service_manager');
        foreach ($serviceManager->findAll() as $service) {
            $this->getContainer()->get('filesystem')->mkdir($rootDir . '/' . $service->getId());
            $output->writeln(sprintf('  <comment>%d: %s</comment>', $service->getId(), $service->getName()));
        }
    }
}
