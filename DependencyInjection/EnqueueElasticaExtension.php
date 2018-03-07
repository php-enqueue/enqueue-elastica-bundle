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

        $container->setAlias('enqueue_elastica.context', $config['context']);

        $doctrineDriver = $config['doctrine']['driver'];
        if (false == empty($config['doctrine']['queue_listeners'])) {
            foreach ($config['doctrine']['queue_listeners'] as $listenerConfig) {
                $listenerId = sprintf(
                    'enqueue_elastica.doctrine_queue_listener.%s.%s',
                    $listenerConfig['index_name'],
                    $listenerConfig['type_name']
                );

                $container->register($listenerId, SyncIndexWithObjectChangeListener::class)
                    ->setPublic(true)
                    ->addArgument(new Reference('enqueue_elastica.context'))
                    ->addArgument($listenerConfig['model_class'])
                    ->addArgument($listenerConfig)
                    ->addTag($this->getEventSubscriber($doctrineDriver), ['connection' => $listenerConfig['connection']])
                ;
            }
        }

        $serviceId       = 'enqueue_elastica.doctrine.sync_index_with_object_change_processor';
        $managerRegistry = $this->getManagerRegistry($doctrineDriver);
        $container
            ->getDefinition($serviceId)
            ->replaceArgument(0, new Reference($managerRegistry));
    }

    /**
     * @param string $driver
     *
     * @return string
     */
    private function getManagerRegistry(string $driver): string
    {
        switch ($driver) {
            case 'mongodb':
                return 'doctrine_mongodb';
                break;
            case 'orm':
            default:
                return 'doctrine';
        }
    }

    /**
     * @param string $driver
     *
     * @return string
     */
    private function getEventSubscriber(string $driver): string
    {
        switch ($driver) {
            case 'mongodb':
                return 'doctrine_mongodb.odm.event_subscriber';
                break;
            case 'orm':
            default:
                return 'doctrine.event_subscriber';
        }
    }
}
