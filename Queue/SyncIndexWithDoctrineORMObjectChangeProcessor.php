<?php
namespace Enqueue\ElasticaBundle\Queue;

use Enqueue\Client\CommandSubscriberInterface;
use Enqueue\Consumption\QueueSubscriberInterface;
use Enqueue\Util\JSON;
use FOS\ElasticaBundle\Persister\PersisterRegistry;
use FOS\ElasticaBundle\Provider\IndexableInterface;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrMessage;
use Interop\Queue\PsrProcessor;
use Symfony\Bridge\Doctrine\RegistryInterface;

final class SyncIndexWithDoctrineORMObjectChangeProcessor implements PsrProcessor, CommandSubscriberInterface, QueueSubscriberInterface
{
    /**
     * @var PersisterRegistry
     */
    private $persisterRegistry;

    /**
     * @var IndexableInterface
     */
    private $indexable;

    /**
     * @var RegistryInterface
     */
    private $doctrine;

    public function __construct(RegistryInterface $doctrine, PersisterRegistry $persisterRegistry, IndexableInterface $indexable)
    {
        $this->persisterRegistry = $persisterRegistry;
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

        $index = $data['indexName'];
        $type = $data['typeName'];

        $repository = $this->doctrine->getManagerForClass($data['modelClass'])->getRepository($data['modelClass']);
        $persister = $this->persisterRegistry->getPersister($index, $type);

        switch ($data['action']) {
            case 'update':
                if (false == $object = $repository->find($data['id'])) {
                    $persister->deleteById($data['id']);

                    return self::REJECT;
                }

                if ($persister->handlesObject($object)) {
                    if ($this->indexable->isObjectIndexable($index, $type, $object)) {
                        $persister->replaceOne($object);
                    } else {
                        $persister->deleteOne($object);
                    }
                }

                break;
            case 'insert':
                if (false == $object = $repository->find($data['id'])) {
                    $persister->deleteById($data['id']);

                    return self::REJECT;
                }

                if ($persister->handlesObject($object) && $this->indexable->isObjectIndexable($index, $type, $object)) {
                    $persister->insertOne($object);
                }

                break;
            case 'delete':
                $persister->deleteById($data['id']);

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
