<?php

namespace Enqueue\Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $tb = new TreeBuilder();
        $rootNode = $tb->root('enqueue_elastica');
        $rootNode
            ->children()
                ->arrayNode('doctrine_queue_listeners')
                    ->prototype('array')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->booleanNode('on_insert')->defaultTrue()->end()
                            ->booleanNode('on_update')->defaultTrue()->end()
                            ->booleanNode('on_remove')->defaultTrue()->end()
                            ->scalarNode('index_name')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('type_name')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('model_class')->isRequired()->cannotBeEmpty()->end()
        ;
    }
}
