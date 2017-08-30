<?php
namespace Enqueue\ElasticaBundle\Async;

use Enqueue\Client\CommandSubscriberInterface;
use Enqueue\Consumption\QueueSubscriberInterface;
use Enqueue\Consumption\Result;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrMessage;
use Interop\Queue\PsrProcessor;
use Enqueue\Util\JSON;
use FOS\ElasticaBundle\Provider\ProviderRegistry;

class ElasticaPopulateProcessor implements PsrProcessor, CommandSubscriberInterface, QueueSubscriberInterface
{
    /**
     * @var ProviderRegistry
     */
    private $providerRegistry;

    /**
     * @param ProviderRegistry $providerRegistry
     */
    public function __construct(ProviderRegistry $providerRegistry)
    {
        $this->providerRegistry = $providerRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function process(PsrMessage $message, PsrContext $context)
    {
        if ($message->isRedelivered()) {
            $this->createReplyMessage($context, $message, false);

            return Result::reply($this->createReplyMessage($context, $message, false), Result::REJECT);
        }

        try {
            $options = JSON::decode($message->getBody());

            $provider = $this->providerRegistry->getProvider($options['indexName'], $options['typeName']);
            $provider->populate(null, $options);

            return Result::reply($this->createReplyMessage($context, $message, true));
        } catch (\Exception $e) {
            return Result::reply($this->createReplyMessage($context, $message, false), Result::REJECT);
        }
    }

    /**
     * @param PsrContext $context
     * @param PsrMessage $message
     * @param bool $successful
     *
     * @return PsrMessage
     */
    private function createReplyMessage(PsrContext $context, PsrMessage $message, $successful)
    {
        $replyMessage = $context->createMessage($message->getBody(), $message->getProperties(), $message->getHeaders());
        $replyMessage->setProperty('fos-populate-successful', (int) $successful);

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
