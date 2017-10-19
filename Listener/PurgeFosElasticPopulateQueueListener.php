<?php
namespace Enqueue\ElasticaBundle\Listener;

use Interop\Queue\PsrContext;
use FOS\ElasticaBundle\Event\IndexPopulateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PurgeFosElasticPopulateQueueListener implements EventSubscriberInterface
{
    /**
     * @var PsrContext
     */
    private $context;

    /**
     * @param PsrContext $context
     */
    public function __construct(PsrContext $context)
    {
        $this->context = $context;
    }

    public function onPreIndexPopulate(IndexPopulateEvent $event)
    {
        if (method_exists($this->context, 'purge')) {
            $queue = $this->context->createQueue('fos_elastica_populate');

            $this->context->purge($queue);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            IndexPopulateEvent::PRE_INDEX_POPULATE => 'onPreIndexPopulate',
        ];
    }
}
