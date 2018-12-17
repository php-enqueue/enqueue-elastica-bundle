<?php
namespace Enqueue\ElasticaBundle\Persister\Listener;

use FOS\ElasticaBundle\Persister\Event\PrePersistEvent;
use Interop\Queue\Context;
use FOS\ElasticaBundle\Persister\Event\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PurgePopulateQueueListener implements EventSubscriberInterface
{
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function purgePopulateQueue(PrePersistEvent $event)
    {
        $options = $event->getOptions();
        if (empty($options['purge_populate_queue'])) {
            return;
        }
        if (empty($options['populate_queue'])) {
            return;
        }

        if (method_exists($this->context, 'purge')) {
            $queue = $this->context->createQueue($options['populate_queue']);

            $this->context->purge($queue);
        }

        if (method_exists($this->context, 'purgeQueue')) {
            $queue = $this->context->createQueue($options['populate_queue']);

            $this->context->purgeQueue($queue);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            Events::PRE_PERSIST => 'purgePopulateQueue',
        ];
    }
}
