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
                ->booleanNode('enabled')->defaultValue(true)->end()
                ->scalarNode('context')->defaultValue('enqueue.transport.context')->cannotBeEmpty()->end()
                ->arrayNode('doctrine')
                    ->children()
                        ->scalarNode('driver')->defaultValue('orm')->cannotBeEmpty()
                            ->validate()->ifNotInArray(['orm', 'mongodb'])->thenInvalid('Invalid driver')
                        ->end()->end()
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
                                    ->integerNode('process_sleep')->defaultValue(0)->end()
                                    ->scalarNode('model_id')->defaultValue('id')->cannotBeEmpty()->end()->end()
        ;

        return $tb;
    }
}
