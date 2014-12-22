<?php

namespace TreeHouse\SwiftBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TreeHouse\KeystoneBundle\Manager\ServiceManager;
use TreeHouse\SwiftBundle\ObjectStore\Container;
use TreeHouse\SwiftBundle\ObjectStore\ObjectStoreRegistry;

class GetContainerHashCommand extends ContainerAwareCommand
{
    /**
     * @var ServiceManager
     */
    protected $serviceManager;

    /**
     * @var ObjectStoreRegistry
     */
    protected $storeRegistry;

    /**
     * @param ServiceManager      $serviceManager
     * @param ObjectStoreRegistry $storeRegistry
     */
    public function __construct(ServiceManager $serviceManager, ObjectStoreRegistry $storeRegistry)
    {
        parent::__construct();

        $this->serviceManager = $serviceManager;
        $this->storeRegistry  = $storeRegistry;
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('swift:container:hash')
            ->setDescription('Gets the hash for a given container')
            ->addArgument('service', InputArgument::REQUIRED, 'The service name')
            ->addArgument('container', InputArgument::REQUIRED, 'The container to hash')
            ->addOption('absolute', 'a', InputOption::VALUE_NONE, 'Return the absolute path instead of just the container', false)
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name      = $input->getArgument('container');
        $container = new Container($name);
        $path      = $container->getPath();

        if ($input->getOption('absolute')) {
            $service = $this->serviceManager->findServiceByName($input->getArgument('service'));
            $store   = $this->storeRegistry->getObjectStore($service);

            $path = $store->getStoreDriver()->getContainerPath($container);
        }

        $output->writeln(sprintf('Container <info>%s</info> is hashed into <info>%s</info>', $name, $path));
    }
}
