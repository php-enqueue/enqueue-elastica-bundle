<?php

namespace Enqueue\ElasticaBundle\DependencyInjection;

use Enqueue\ElasticaBundle\Doctrine\SyncIndexWithObjectChangeListener;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class EnqueueElasticaExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        if (false == empty($config['doctrine']['queue_listeners'])) {
            foreach ($config['doctrine']['queue_listeners'] as $listenerConfig) {
                $listenerId = sprintf(
                    'enqueue_elastica.doctrine_queue_listener.%s.%s',
                    $listenerConfig['index_name'],
                    $listenerConfig['type_name']
                );

                $container->register($listenerId, SyncIndexWithObjectChangeListener::class)
                    ->addArgument(new Reference('enqueue.transport.context'))
                    ->addArgument($listenerConfig['model_class'])
                    ->addArgument($listenerConfig)
                    ->addTag('doctrine.event_subscriber', ['connection' => $listenerConfig['connection']])
                ;
            }
        }
    }
}
