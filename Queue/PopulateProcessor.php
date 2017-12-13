<?php
namespace Enqueue\ElasticaBundle\Queue;

use Enqueue\Client\CommandSubscriberInterface;
use Enqueue\Consumption\QueueSubscriberInterface;
use Enqueue\Consumption\Result;
use FOS\ElasticaBundle\Persister\Event\Events;
use FOS\ElasticaBundle\Persister\Event\PostInsertObjectsEvent;
use FOS\ElasticaBundle\Persister\Event\PreInsertObjectsEvent;
use FOS\ElasticaBundle\Persister\InPlacePagerPersister;
use FOS\ElasticaBundle\Persister\PagerPersisterRegistry;
use FOS\ElasticaBundle\Provider\PagerInterface;
use FOS\ElasticaBundle\Provider\PagerProviderRegistry;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrMessage;
use Interop\Queue\PsrProcessor;
use Enqueue\Util\JSON;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PopulateProcessor implements PsrProcessor, CommandSubscriberInterface, QueueSubscriberInterface
{
    /**
     * @var PagerProviderRegistry
     */
    private $pagerProviderRegistry;

    /**
     * @var PagerPersisterRegistry
     */
    private $pagerPersisterRegistry;

    public function __construct(
        PagerProviderRegistry $pagerProviderRegistry,
        PagerPersisterRegistry $pagerPersisterRegistry
    ) {
        $this->pagerPersisterRegistry = $pagerPersisterRegistry;
        $this->pagerProviderRegistry = $pagerProviderRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function process(PsrMessage $message, PsrContext $context)
    {
        if ($message->isRedelivered()) {
            $replyMessage = $this->createReplyMessage($context, $message, 0,'The message was redelivered. Chances are that something has gone wrong.');

            return Result::reply($replyMessage, Result::REJECT);
        }

        $objectsCount = 0;

//        try {
            $data = JSON::decode($message->getBody());

            if (!isset($data['options'])) {
                return Result::reply($this->createReplyMessage($context, $message, 0,'The message is invalid. Missing options.'));
            }
            if (!isset($data['page'])) {
                return Result::reply($this->createReplyMessage($context, $message, 0,'The message is invalid. Missing page.'));
            }
            if (!isset($data['options']['indexName'])) {
                return Result::reply($this->createReplyMessage($context, $message, 0,'The message is invalid. Missing indexName option.'));
            }
            if (!isset($data['options']['typeName'])) {
                return Result::reply($this->createReplyMessage($context, $message, 0,'The message is invalid. Missing typeName option.'));
            }

            $options = $data['options'];
            $options['first_page'] = $data['page'];
            $options['last_page'] = $data['page'];

            $provider = $this->pagerProviderRegistry->getProvider($options['indexName'], $options['typeName']);
            $pager = $provider->provide($options);
            $pager->setMaxPerPage($options['max_per_page']);
            $pager->setCurrentPage($options['first_page']);

            $objectsCount = count($pager->getCurrentPageResults());

            $pagerPersister = $this->pagerPersisterRegistry->getPagerPersister(InPlacePagerPersister::NAME);
            $pagerPersister->insert($pager, $options);

            return Result::reply($this->createReplyMessage($context, $message, $objectsCount));
//        } catch (\Throwable $e) {
//            return Result::reply($this->createExceptionReplyMessage($context, $message, $objectsCount, $e), Result::REJECT);
//        }
    }

    /**
     * @param PsrContext $context
     * @param PsrMessage $message
     * @param int $objectsCount
     * @param \Throwable $e
     * @return PsrMessage
     */
    private function createExceptionReplyMessage(PsrContext $context, PsrMessage $message, $objectsCount, \Throwable $e)
    {
        $errorMessage = sprintf(
            '<error>The queue processor has failed to process the message with exception: </error><comment>%s: %s in file %s at line %s.</comment>',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        return $this->createReplyMessage($context, $message, $errorMessage);
    }

    /**
     * @param PsrContext $context
     * @param PsrMessage $message
     * @param int $objectsCount
     * @param string|null $error
     *
     * @return PsrMessage
     */
    private function createReplyMessage(PsrContext $context, PsrMessage $message, $objectsCount, $error = null)
    {
        $replyMessage = $context->createMessage($message->getBody(), $message->getProperties(), $message->getHeaders());
        $replyMessage->setProperty('fos-populate-objects-count', $objectsCount);

        if ($error) {
            $replyMessage->setProperty('fos-populate-error', $error);
        }

        return $replyMessage;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedCommand()
    {
        return [
            'processorName' => Commands::POPULATE,
            'queueName' => Commands::POPULATE,
            'queueNameHardcoded' => true,
            'exclusive' => true,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedQueues()
    {
        return [Commands::POPULATE];
    }
}
