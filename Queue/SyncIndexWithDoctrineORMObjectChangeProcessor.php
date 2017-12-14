<?php
namespace Enqueue\ElasticaBundle\Queue;

use Enqueue\Client\CommandSubscriberInterface;
use Enqueue\Consumption\QueueSubscriberInterface;
use Enqueue\Util\JSON;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use FOS\ElasticaBundle\Provider\IndexableInterface;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrMessage;
use Interop\Queue\PsrProcessor;
use Symfony\Bridge\Doctrine\RegistryInterface;

final class SyncIndexWithDoctrineORMObjectChangeProcessor implements PsrProcessor, CommandSubscriberInterface, QueueSubscriberInterface
{
    /**
     * @var ObjectPersisterInterface
     */
    private $objectPersister;

    /**
     * @var IndexableInterface
     */
    private $indexable;

    /**
     * @var RegistryInterface
     */
    private $doctrine;

    public function __construct(RegistryInterface $doctrine, ObjectPersisterInterface $objectPersister, IndexableInterface $indexable)
    {
        $this->objectPersister = $objectPersister;
        $this->indexable = $indexable;
        $this->doctrine = $doctrine;
    }

    /**
     * {@inheritdoc}
     */
    public function process(PsrMessage $message, PsrContext $context)
    {
        $data = JSON::decode($message->getBody());

        if (false == isset($data['action'], $data['modelClass'], $data['id'], $data['indexName'], $data['typeName'])) {
            return self::REJECT;
        }

        $indexName = $data['indexName'];
        $typeName = $data['typeName'];

        $objectRepository = $this->doctrine->getManagerForClass($data['modelClass'])->getRepository($data['modelClass']);

        switch ($data['action']) {
            case 'update':
                if (false == $object = $objectRepository->find($data['id'])) {
                    $this->objectPersister->deleteById($data['id']);

                    return self::REJECT;
                }

                if ($this->objectPersister->handlesObject($object)) {
                    if ($this->indexable->isObjectIndexable($indexName, $typeName, $object)) {
                        $this->objectPersister->replaceOne($object);
                    } else {
                        $this->objectPersister->deleteOne($object);
                    }
                }

                break;
            case 'insert':
                if (false == $object = $objectRepository->find($data['id'])) {
                    $this->objectPersister->deleteById($data['id']);

                    return self::REJECT;
                }

                if ($this->objectPersister->handlesObject($object) && $this->indexable->isObjectIndexable($indexName, $typeName, $object)) {
                    $this->objectPersister->insertOne($object);
                }

                break;
            case 'delete':
                $this->objectPersister->deleteById($data['id']);

                break;
            default:
                return self::REJECT;
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedCommand()
    {
        return [
            'processorName' => Commands::SYNC_INDEX_WITH_DOCTRINE_ORM_OBJECT_CHANGE,
            'queueName' => Commands::SYNC_INDEX_WITH_DOCTRINE_ORM_OBJECT_CHANGE,
            'queueNameHardcoded' => true,
            'exclusive' => true,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedQueues()
    {
        return [Commands::SYNC_INDEX_WITH_DOCTRINE_ORM_OBJECT_CHANGE];
    }
}
