<?php

namespace FM\SwiftBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;
use FM\SwiftBundle\ObjectStore\DriverFactoryInterface as StoreDriverFactoryInterface;
use FM\SwiftBundle\Metadata\DriverFactoryInterface as MetadataDriverFactoryInterface;

class FMSwiftExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $this->setParameters($container, $config);

        $this->loadObjectStores($container, $config);
    }

    protected function setParameters(ContainerBuilder $container, array $config)
    {
        $container->setParameter('fm_swift.root_dir', $config['root_dir']);
    }

    protected function loadObjectStores(ContainerBuilder $container, array $config)
    {
        $registry = $container->getDefinition('fm_swift.object_store.registry');

        foreach ($config['stores'] as $name => $storeConfig) {
            if ($storeConfig['metadata'] === 'xattr') {
                // check if xattr is supported
                if (!$this->hasXattrSupport()) {
                    throw new \LogicException('Xattr is not supported on this filesystem. You can install it by installing "libattr1" and "libattr1-dev" packages on the server, and the xattr pecl module.');
                }

                // xattr is only allowed when store driver is filesystem
                // (because we need to set the attributes in the files)
                if ($storeConfig['driver'] !== 'filesystem') {
                    throw new \LogicException('The xattr metadata driver can only be used in conjunction with the filesystem store driver');
                }
            }

            $storeId = sprintf('fm_swift.object_store.%s', $name);
            $storeDriverId = sprintf('%s.store_driver', $storeId);
            $metadataDriverId = sprintf('%s.metadata_driver', $storeId);

            $service = new Reference($storeConfig['service']);

            // create drivers
            $this->createStoreDriver($container, $service, $storeDriverId, $storeConfig['driver'], $config);
            $this->createMetadataDriver($container, $service, $metadataDriverId, $storeConfig['metadata'], $config);

            $store = $container->setDefinition($storeId, new DefinitionDecorator('fm_swift.object_store'));
            $store->replaceArgument(0, new Reference($storeDriverId));
            $store->replaceArgument(1, new Reference($metadataDriverId));

            $registry->addMethodCall('addObjectStore', array($service, new Reference($storeId)));
        }
    }

    protected function createStoreDriver(ContainerBuilder $container, Reference $service, $id, $type, array $config)
    {
        $serviceId = sprintf('fm_swift.object_store.driver.%s', $type);
        if ($container->hasDefinition($serviceId)) {
            // create based on abstract service
            $driver = $container->setDefinition($id, new DefinitionDecorator($serviceId));
            $driver->replaceArgument(0, $service);

            return;
        }

        // it must be a service then
        $container->setAlias($id, $type);
    }

    protected function createMetadataDriver(ContainerBuilder $container, Reference $service, $id, $type, array $config)
    {
        $serviceId = sprintf('fm_swift.metadata.driver.%s', $type);
        if ($container->hasDefinition($serviceId)) {
            // create based on abstract service
            $driver = $container->setDefinition($id, new DefinitionDecorator($serviceId));
            $driver->replaceArgument(0, $service);

            return;
        }

        // it must be a service then
        $container->setAlias($id, $type);
    }

    protected function hasXattrSupport()
    {
        return extension_loaded('xattr') && xattr_supported(__FILE__);
    }
}
