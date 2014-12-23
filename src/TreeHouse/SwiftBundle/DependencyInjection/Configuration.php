<?php

namespace TreeHouse\SwiftBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('tree_house_swift');

        $rootNode
            ->children()
                ->scalarNode('root_dir')
                    ->defaultValue('%kernel.root_dir%/var/data')
                ->end()
                ->scalarNode('expression')
                    ->info('The default expression to use for authorization on the routes')
                    ->defaultValue('ROLE_USER')
                ->end()
                ->arrayNode('stores')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->addDefaultsIfNotSet()
                        ->beforeNormalization()
                            ->ifString()->then(function($v) {
                                return ['service' => $v];
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
