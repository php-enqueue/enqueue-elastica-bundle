<?php
namespace Enqueue\ElasticaBundle\Doctrine\Queue;

use Enqueue\Client\CommandSubscriberInterface;
use Enqueue\Consumption\QueueSubscriberInterface;
use Enqueue\Consumption\Result;
use Enqueue\Util\JSON;
use FOS\ElasticaBundle\Persister\PersisterRegistry;
use FOS\ElasticaBundle\Provider\IndexableInterface;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use Doctrine\Persistence\ManagerRegistry;

final class SyncIndexWithObjectChangeProcessor implements Processor, CommandSubscriberInterface, QueueSubscriberInterface
{
    const INSERT_ACTION = 'insert';

    const UPDATE_ACTION = 'update';

    const REMOVE_ACTION = 'remove';

    private $persisterRegistry;

    private $indexable;

    private $doctrine;

    public function __construct(ManagerRegistry $doctrine, PersisterRegistry $persisterRegistry, IndexableInterface $indexable)
    {
        $this->persisterRegistry = $persisterRegistry;
        $this->indexable = $indexable;
        $this->doctrine = $doctrine;
    }

    public function process(Message $message, Context $context): Result
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

                return Result::ack();
            case self::INSERT_ACTION:
                if (false == $object = $repository->{$repositoryMethod}($id)) {
                    $persister->deleteById($id);

                    return Result::ack(sprintf('The object "%s" with id "%s" could not be found.', $modelClass, $id));
                }

                if ($persister->handlesObject($object) && $this->indexable->isObjectIndexable($index, $type, $object)) {
                    $persister->insertOne($object);
                }

                return Result::ack();
            case self::REMOVE_ACTION:
                $persister->deleteById($id);

                return Result::ack();
            default:
                return Result::reject(sprintf('The action "%s" is not supported', $action));
        }
    }

    public static function getSubscribedCommand(): array
    {
        return [
            'command' => Commands::SYNC_INDEX_WITH_OBJECT_CHANGE,
            'queue' => Commands::SYNC_INDEX_WITH_OBJECT_CHANGE,
            'prefix_queue' => false,
            'exclusive' => true,
        ];
    }

    public static function getSubscribedQueues(): array
    {
        return [Commands::SYNC_INDEX_WITH_OBJECT_CHANGE];
    }
}
