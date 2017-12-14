<?php
namespace Enqueue\ElasticaBundle\Doctrine;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Enqueue\ElasticaBundle\Queue\Commands;
use Enqueue\Util\JSON;
use Interop\Queue\PsrContext;
use Doctrine\Common\EventSubscriber;

final class SyncIndexWithObjectChangeListener implements EventSubscriber
{
    /**
     * @var PsrContext
     */
    private $context;

    /**
     * @var string
     */
    private $modelClass;

    /**
     * @var array
     */
    private $config;

    public function __construct(PsrContext $context, $modelClass, array $config)
    {
        $this->context = $context;
        $this->modelClass = $modelClass;
        $this->config = $config;
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        if ($args->getObject() instanceof $this->modelClass) {
            $this->sendUpdateIndexMessage('update', $args);
        }
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        if ($args->getObject() instanceof $this->modelClass) {
            $this->sendUpdateIndexMessage('insert', $args);
        }
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        if ($args->getObject() instanceof $this->modelClass) {
            $this->sendUpdateIndexMessage('remove', $args);
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

        $rp = new \ReflectionProperty($object, $this->config['identifier']);
        $rp->setAccessible(true);
        $id = $rp->getValue($object);
        $rp->setAccessible(false);

        $queue = $this->context->createQueue(Commands::SYNC_INDEX_WITH_DOCTRINE_ORM_OBJECT_CHANGE);

        $message = $this->context->createMessage(JSON::encode([
            'action' => $action,
            'modelClass' => $this->modelClass,
            'id' => $id,
            'indexName' => $this->config['indexName'],
            'typeName' => $this->config['typeName'],
        ]));

        $this->context->createProducer()->send($queue, $message);
    }
}
