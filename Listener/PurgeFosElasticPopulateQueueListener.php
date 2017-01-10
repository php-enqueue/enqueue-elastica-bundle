<?php
namespace Enqueue\ElasticaBundle\Listener;

use Enqueue\Psr\Context;
use FOS\ElasticaBundle\Event\IndexPopulateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PurgeFosElasticPopulateQueueListener implements EventSubscriberInterface
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function onPreIndexPopulate(IndexPopulateEvent $event)
    {
        if (method_exists($this->context, 'purge')) {
            $queue = $this->context->createQueue('fos_elastica.populate');

            $this->context->purge($queue);

            sleep(20);
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
