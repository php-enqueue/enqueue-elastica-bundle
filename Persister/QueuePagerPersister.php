<?php
namespace Enqueue\ElasticaBundle\Persister;

use Enqueue\ElasticaBundle\Queue\Commands;
use Enqueue\Util\JSON;
use FOS\ElasticaBundle\Index\IndexManager;
use FOS\ElasticaBundle\Persister\Event\PostAsyncInsertObjectsEvent;
use FOS\ElasticaBundle\Persister\Event\PostPersistEvent;
use FOS\ElasticaBundle\Persister\Event\PrePersistEvent;
use FOS\ElasticaBundle\Persister\PagerPersisterInterface;
use FOS\ElasticaBundle\Persister\PersisterRegistry;
use FOS\ElasticaBundle\Provider\PagerInterface;
use Interop\Queue\Context;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class QueuePagerPersister implements PagerPersisterInterface
{
    const NAME = 'queue';

    private $context;

    /**
     * @var PersisterRegistry
     */
    private $registry;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var IndexManager
     */
    private $indexManager;

    public function __construct(
        Context $context,
        PersisterRegistry $registry,
        EventDispatcherInterface $dispatcher,
        IndexManager $indexManager
    ) {
        $this->context = $context;
        $this->dispatcher = $dispatcher;
        $this->registry = $registry;
        $this->indexManager = $indexManager;
    }

    /**
     * {@inheritdoc}
     */
    public function insert(PagerInterface $pager, array $options = array())
    {
        $pager->setMaxPerPage(empty($options['max_per_page']) ? 100 : $options['max_per_page']);

        $defaultOptions = [
            'max_per_page' => $pager->getMaxPerPage(),
            'first_page' => $pager->getCurrentPage(),
            'last_page' => $pager->getNbPages(),
            'populate_queue' => Commands::POPULATE,
            'populate_reply_queue' => null,
            'reply_receive_timeout' => 5000, // ms
            'limit_overall_reply_time' => 180 // sec
        ];
        $index = $this->indexManager->getIndex($options['indexName']);
        if ($index->getName() !== $index->getOriginalName()) {
            $defaultOptions['realIndexName'] = $index->getName();
        }

        $options = array_replace($defaultOptions, $options);

        $pager->setCurrentPage($options['first_page']);

        $objectPersister = $this->registry->getPersister($options['indexName']);

        $event = new PrePersistEvent($pager, $objectPersister, $options);
        $this->dispatcher->dispatch($event);
        $pager = $event->getPager();
        $options = $event->getOptions();

        $queue = $this->context->createQueue($options['populate_queue']);
        $replyQueue = $options['populate_reply_queue'] ?
            $this->context->createQueue($options['populate_reply_queue']) :
            $this->context->createTemporaryQueue()
        ;
        $options['populate_reply_queue'] = $replyQueue->getQueueName();

        $producer = $this->context->createProducer();

        $lastPage = min($options['last_page'], $pager->getNbPages());
        $page = $pager->getCurrentPage();
        $sentCount = 0;
        do {
            $pager->setCurrentPage($page);

            $filteredOptions = $options;
            unset(
                $filteredOptions['first_page'],
                $filteredOptions['last_page'],
                $filteredOptions['populate_queue'],
                $filteredOptions['populate_reply_queue'],
                $filteredOptions['reply_receive_timeout'],
                $filteredOptions['limit_overall_reply_time']
            );

            $message = $this->context->createMessage(JSON::encode([
                'options' => $filteredOptions,
                'page' => $page,
            ]));
            $message->setReplyTo($replyQueue->getQueueName());

            // Because of https://github.com/php-enqueue/enqueue-dev/issues/907
            \usleep(10);

            $producer->send($queue, $message);

            $page++;
            $sentCount++;
        } while ($page <= $lastPage);

        $consumer = $this->context->createConsumer($replyQueue);
        // Treat limit_overall_reply_time as an idle timeout rather than a hard overall cap: as long as
        // replies keep arriving the consumers are making progress, so the deadline is pushed forward on
        // each reply. This lets large indexes (whose population takes far longer than the timeout) finish
        // while still aborting if the consumers go silent (e.g. all workers died).
        $limitTime = microtime(true) + $options['limit_overall_reply_time'];
        while ($sentCount) {
            if ($message = $consumer->receive($options['reply_receive_timeout'])) {
                $sentCount--;
                $limitTime = microtime(true) + $options['limit_overall_reply_time'];

                $data = JSON::decode($message->getBody());

                $errorMessage = $message->getProperty('fos-populate-error', false);
                $objectsCount = (int) $message->getProperty('fos-populate-objects-count', false);

                $pager->setCurrentPage($data['page']);

                $event = new PostAsyncInsertObjectsEvent(
                    $pager,
                    $objectPersister,
                    $objectsCount,
                    $errorMessage,
                    $data['options']
                );
                $this->dispatcher->dispatch($event);
            } elseif (microtime(true) > $limitTime) {
                throw new \LogicException(sprintf('No populate reply received within %s seconds; the consumers appear to be stuck.', $options['limit_overall_reply_time']));
            }
        }

        $event = new PostPersistEvent($pager, $objectPersister, $options);
        $this->dispatcher->dispatch($event);
    }
}
