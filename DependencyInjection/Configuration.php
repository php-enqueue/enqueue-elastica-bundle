<?php

namespace Enqueue\ElasticaBundle\DependencyInjection;

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
                ->arrayNode('doctrine')
                    ->children()
                        ->arrayNode('queue_listeners')
                            ->prototype('array')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->booleanNode('insert')->defaultTrue()->end()
                                    ->booleanNode('update')->defaultTrue()->end()
                                    ->booleanNode('remove')->defaultTrue()->end()
                                    ->scalarNode('connection')->defaultValue('default')->cannotBeEmpty()->end()
                                    ->scalarNode('index_name')->isRequired()->cannotBeEmpty()->end()
                                    ->scalarNode('type_name')->isRequired()->cannotBeEmpty()->end()
                                    ->scalarNode('model_class')->isRequired()->cannotBeEmpty()->end()
                                    ->scalarNode('model_id')->defaultValue('id')->cannotBeEmpty()->end()->end()
        ;

        return $tb;
    }
}
