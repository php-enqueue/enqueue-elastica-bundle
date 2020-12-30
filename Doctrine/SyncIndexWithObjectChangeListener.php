<?php
namespace Enqueue\ElasticaBundle\Doctrine;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
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
    private $scheduledForUpdateIndex = [];

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
            $this->scheduledForUpdateIndex[] = [
                'action' => SyncProcessor::UPDATE_ACTION,
                'id'     => $this->extractId($args->getObject())
            ];
        }
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        if ($args->getObject() instanceof $this->modelClass) {
            $this->scheduledForUpdateIndex[] = [
                'action' => SyncProcessor::INSERT_ACTION,
                'id'     => $this->extractId($args->getObject())
            ];
        }
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        if ($args->getObject() instanceof $this->modelClass) {
            $this->scheduledForUpdateIndex[] = [
                'action' => SyncProcessor::REMOVE_ACTION,
                'id'     => $this->extractId($args->getObject())
            ];
        }
    }

    public function postFlush(PostFlushEventArgs $event)
    {
        if (count($this->scheduledForUpdateIndex)) {
            foreach ($this->scheduledForUpdateIndex as $updateIndex) {
                $this->sendUpdateIndexMessage($updateIndex['action'], $updateIndex['id']);
            }

            $this->scheduledForUpdateIndex = [];
        }
    }

    public function getSubscribedEvents()
    {
        return [
            'postPersist',
            'postUpdate',
            'preRemove',
            'postFlush'
        ];
    }

    /**
     * @param string $action
     * @param $id
     */
    private function sendUpdateIndexMessage($action, $id)
    {
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

    /**
     * @param $object
     * @return mixed
     * @throws \ReflectionException
     */
    private function extractId($object)
    {
        $rp = (new \ReflectionClass($this->modelClass))->getProperty($this->config['model_id']);
        $rp->setAccessible(true);
        $id = $rp->getValue($object);
        $rp->setAccessible(false);

        return $id;
    }
}
