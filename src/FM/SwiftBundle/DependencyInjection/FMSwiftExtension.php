<?php

namespace FM\SwiftBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\FileLocator;

class FMSwiftExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $this->setParameters($container, $config);

        $this->setStoreDriver($container, $config);
        $this->setMetadataDriver($container, $config);
    }

    protected function setParameters(ContainerBuilder $container, array $config)
    {
        $container->setParameter('fm_swift.root_dir', $config['root_dir']);
    }

    protected function setStoreDriver(ContainerBuilder $container, array $config)
    {
        if (!$container->hasDefinition('fm_swift.object_store.factory')) {
            return;
        }

        $driverFactory = sprintf('fm_swift.object_store.driver_factory.%s', $config['store']['driver']);
        $container->setAlias('fm_swift.object_store.driver_factory', $driverFactory);
    }

    protected function setMetadataDriver(ContainerBuilder $container, array $config)
    {
        if (!$container->hasDefinition('fm_swift.object_store.factory')) {
            return;
        }

        $metadata = $config['metadata'];
        $type = $metadata['driver'];

        // check if xattr is supported
        if (($type === 'xattr') && !$this->hasXattrSupport()) {
            throw new \LogicException('Xattr is not supported on this filesystem. You can install it by installing "libattr1" and "libattr1-dev" packages on the server, and the xattr pecl module.');
        }

        $driverFactory = sprintf('fm_swift.metadata.driver_factory.%s', $type);
        $container->setAlias('fm_swift.metadata.driver_factory', $driverFactory);
    }

    protected function hasXattrSupport()
    {
        return extension_loaded('xattr') && xattr_supported(__FILE__);
    }
}
