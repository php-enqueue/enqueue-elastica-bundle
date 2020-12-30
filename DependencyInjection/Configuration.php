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
        
        $tb = new TreeBuilder('enqueue_elastica');
        if (method_exists($tb, 'getRootNode')) {
            $rootNode = $tb->getRootNode();
        } else {
            // BC layer for symfony/config 4.1 and older
            $rootNode = $tb->root('enqueue_elastica');
        }
        $rootNode
            ->children()
                ->booleanNode('enabled')->defaultValue(true)->end()
                ->scalarNode('transport')->defaultValue('%enqueue.default_transport%')->cannotBeEmpty()->isRequired()->end()
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
                                    ->scalarNode('model_id')->defaultValue('id')->cannotBeEmpty()->end()
                                    ->scalarNode('repository_method')->defaultValue('find')->cannotBeEmpty()->end()
                                    ->scalarNode('delivery_delay')->defaultNull()->end()
                    ->end()
        ;

        return $tb;
    }
}
