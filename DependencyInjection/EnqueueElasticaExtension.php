<?php

namespace Enqueue\ElasticaBundle\DependencyInjection;

use Enqueue\ElasticaBundle\Doctrine\Queue\SyncIndexWithObjectChangeProcessor;
use Enqueue\ElasticaBundle\Doctrine\SyncIndexWithObjectChangeListener;
use Enqueue\ElasticaBundle\Persister\Listener\PurgePopulateQueueListener;
use Enqueue\ElasticaBundle\Persister\QueuePagerPersister;
use Enqueue\ElasticaBundle\Queue\PopulateProcessor;
use Enqueue\Symfony\DependencyInjection\TransportFactory;
use Enqueue\Symfony\DiUtils;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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

        if (!$config['enabled']) {
            return;
        }

        $transport = $container->getParameterBag()->resolveValue($config['transport']);

        $diUtils = new DiUtils(TransportFactory::MODULE, $transport);
        $container->setAlias('enqueue_elastica.context', $diUtils->format('context'));

        $container->register('enqueue_elastica.populate_processor', PopulateProcessor::class)
            ->addArgument(new Reference('fos_elastica.pager_provider_registry'))
            ->addArgument(new Reference('fos_elastica.pager_persister_registry'))

            ->addTag('enqueue.command_subscriber', ['client' => $transport])
            ->addTag('enqueue.transport.processor', ['transport' => $transport])
        ;

        $container->register('enqueue_elastica.purge_populate_queue_listener', PurgePopulateQueueListener::class)
            ->addArgument(new Reference('enqueue_elastica.context'))

            ->addTag('kernel.event_subscriber')
        ;

        $container->register('enqueue_elastica.queue_pager_perister', QueuePagerPersister::class)
            ->addArgument(new Reference('enqueue_elastica.context'))
            ->addArgument(new Reference('fos_elastica.persister_registry'))
            ->addArgument(new Reference('event_dispatcher'))

            ->addTag('fos_elastica.pager_persister', ['persisterName' => 'queue'])
        ;

        if (false == empty($config['doctrine']['queue_listeners'])) {
            $doctrineDriver = $config['doctrine']['driver'];

            $container->register('enqueue_elastica.doctrine.sync_index_with_object_change_processor', SyncIndexWithObjectChangeProcessor::class)
                ->addArgument(new Reference($this->getManagerRegistry($doctrineDriver)))
                ->addArgument(new Reference('fos_elastica.persister_registry'))
                ->addArgument(new Reference('fos_elastica.indexable'))
                ->addTag('enqueue.command_subscriber', ['client' => $transport])
                ->addTag('enqueue.transport.processor', ['transport' => $transport])
            ;

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
    }

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
