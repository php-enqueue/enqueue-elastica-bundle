<?php
namespace Enqueue\ElasticaBundle\Doctrine;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Enqueue\ElasticaBundle\Doctrine\Queue\Commands;
use Enqueue\ElasticaBundle\Doctrine\Queue\SyncIndexWithObjectChangeProcessor as SyncProcessor;
use Enqueue\Util\JSON;
use Interop\Queue\Context;
use Doctrine\Common\EventSubscriber;

final class SyncIndexWithObjectChangeListener implements EventSubscriber
{
    private $context;

    /**
     * @var string
     */
    private $modelClass;

    /**
     * @var array
     */
    private $config;

    public function __construct(Context $context, $modelClass, array $config)
    {
        $this->context = $context;
        $this->modelClass = $modelClass;
        $this->config = $config;
    }

    public function postUpdate(LifecycleEventArgs $args)
    {

        if ($args->getObject() instanceof $this->modelClass) {
            $this->sendUpdateIndexMessage(SyncProcessor::UPDATE_ACTION, $args);
        }
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        if ($args->getObject() instanceof $this->modelClass) {
            $this->sendUpdateIndexMessage(SyncProcessor::INSERT_ACTION, $args);
        }
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        if ($args->getObject() instanceof $this->modelClass) {
            $this->sendUpdateIndexMessage(SyncProcessor::REMOVE_ACTION, $args);
        }
    }

    public function getSubscribedEvents()
    {
        return [
            'postPersist',
            'postUpdate',
            'preRemove',
        ];
    }

    /**
     * @param string $action
     * @param LifecycleEventArgs $args
     */
    private function sendUpdateIndexMessage($action, LifecycleEventArgs $args)
    {
        $object = $args->getObject();

        $rp = new \ReflectionProperty($object, $this->config['model_id']);
        $rp->setAccessible(true);
        $id = $rp->getValue($object);
        $rp->setAccessible(false);

        $queue = $this->context->createQueue(Commands::SYNC_INDEX_WITH_OBJECT_CHANGE);

        $message = $this->context->createMessage(JSON::encode([
            'action' => $action,
            'model_class' => $this->modelClass,
            'model_id' => $this->config['model_id'],
            'id' => $id,
            'index_name' => $this->config['index_name'],
            'type_name' => $this->config['type_name'],
            'repository_method' => $this->config['repository_method'],
        ]));

        $this->context->createProducer()->setDeliveryDelay($this->config['delivery_delay'])->send($queue, $message);
    }
}
