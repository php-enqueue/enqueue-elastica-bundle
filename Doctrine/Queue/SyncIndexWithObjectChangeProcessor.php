<?php
namespace Enqueue\ElasticaBundle\Doctrine\Queue;

use Enqueue\Client\CommandSubscriberInterface;
use Enqueue\Consumption\QueueSubscriberInterface;
use Enqueue\Consumption\Result;
use Enqueue\Util\JSON;
use FOS\ElasticaBundle\Persister\PersisterRegistry;
use FOS\ElasticaBundle\Provider\IndexableInterface;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrMessage;
use Interop\Queue\PsrProcessor;
use Doctrine\Common\Persistence\ManagerRegistry;

final class SyncIndexWithObjectChangeProcessor implements PsrProcessor, CommandSubscriberInterface, QueueSubscriberInterface
{
    const INSERT_ACTION = 'insert';

    const UPDATE_ACTION = 'update';

    const REMOVE_ACTION = 'remove';

    /**
     * @var PersisterRegistry
     */
    private $persisterRegistry;

    /**
     * @var IndexableInterface
     */
    private $indexable;

    /**
     * @var ManagerRegistry
     */
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine, PersisterRegistry $persisterRegistry, IndexableInterface $indexable)
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

        if (false == isset($data['action'])) {
            return Result::reject('The message data misses action');
        }
        if (false == isset($data['model_class'])) {
            return Result::reject('The message data misses model_class');
        }
        if (false == isset($data['id'])) {
            return Result::reject('The message data misses id');
        }
        if (false == isset($data['index_name'])) {
            return Result::reject('The message data misses index_name');
        }
        if (false == isset($data['type_name'])) {
            return Result::reject('The message data misses type_name');
        }
        if (false == isset($data['repository_method'])) {
            return Result::reject('The message data misses repository_method');
        }

        $action = $data['action'];
        $modelClass = $data['model_class'];
        $id = $data['id'];
        $index = $data['index_name'];
        $type = $data['type_name'];
        $repositoryMethod = $data['repository_method'];

        $repository = $this->doctrine->getManagerForClass($modelClass)->getRepository($modelClass);
        $persister = $this->persisterRegistry->getPersister($index, $type);

        switch ($action) {
            case self::UPDATE_ACTION:
                if (false == $object = $repository->{$repositoryMethod}($id)) {
                    $persister->deleteById($id);

                    return Result::ack(sprintf('The object "%s" with id "%s" could not be found.', $modelClass, $id));
                }

                if ($persister->handlesObject($object)) {
                    if ($this->indexable->isObjectIndexable($index, $type, $object)) {
                        $persister->replaceOne($object);
                    } else {
                        $persister->deleteOne($object);
                    }
                }

                return self::ACK;
            case self::INSERT_ACTION:
                if (false == $object = $repository->{$repositoryMethod}($id)) {
                    $persister->deleteById($id);

                    return Result::ack(sprintf('The object "%s" with id "%s" could not be found.', $modelClass, $id));
                }

                if ($persister->handlesObject($object) && $this->indexable->isObjectIndexable($index, $type, $object)) {
                    $persister->insertOne($object);
                }

                return self::ACK;
            case self::REMOVE_ACTION:
                $persister->deleteById($id);

                return self::ACK;
            default:
                return Result::reject(sprintf('The action "%s" is not supported', $action));
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedCommand()
    {
        return [
            'processorName' => Commands::SYNC_INDEX_WITH_OBJECT_CHANGE,
            'queueName' => Commands::SYNC_INDEX_WITH_OBJECT_CHANGE,
            'queueNameHardcoded' => true,
            'exclusive' => true,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedQueues()
    {
        return [Commands::SYNC_INDEX_WITH_OBJECT_CHANGE];
    }
}
