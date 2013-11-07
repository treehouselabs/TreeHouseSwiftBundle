<?php

namespace FM\SwiftBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('fm_swift');

        $rootNode
            ->children()
                ->scalarNode('root_dir')
                    ->defaultValue('%kernel.root_dir%/var/data')
                ->end()
                ->arrayNode('stores')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->addDefaultsIfNotSet()
                        ->beforeNormalization()
                            ->ifString()->then(function($v) {
                                return array('service' => $v);
                            })
                        ->end()
                        ->children()
                            ->scalarNode('service')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('driver')
                                ->cannotBeEmpty()
                                ->defaultValue('filesystem')
                            ->end()
                            ->scalarNode('metadata')
                                ->cannotBeEmpty()
                                ->defaultValue(function() {
                                    return (extension_loaded('xattr') && xattr_supported(__FILE__)) ? 'xattr' : 'file';
                                })
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
