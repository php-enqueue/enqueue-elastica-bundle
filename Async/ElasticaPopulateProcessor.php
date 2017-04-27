<?php
namespace Enqueue\ElasticaBundle\Async;

use Enqueue\Consumption\QueueSubscriberInterface;
use Enqueue\Psr\PsrContext;
use Enqueue\Psr\PsrMessage;
use Enqueue\Psr\PsrProcessor;
use Enqueue\Util\JSON;
use FOS\ElasticaBundle\Provider\ProviderRegistry;

class ElasticaPopulateProcessor implements PsrProcessor, QueueSubscriberInterface
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
        if (false == $message->getReplyTo()) {
            return self::REJECT;
        }

        if ($message->isRedelivered()) {
            $this->sendReply($context, $message, false);

            return self::REJECT;
        }

        try {
            $options = JSON::decode($message->getBody());

            $provider = $this->providerRegistry->getProvider($options['indexName'], $options['typeName']);
            $provider->populate(null, $options);

            $this->sendReply($context, $message, true);

            return self::ACK;
        } catch (\Exception $e) {
            $this->sendReply($context, $message, false);

            return self::REJECT;
        }
    }

    /**
     * @param PsrContext $context
     * @param PsrMessage $message
     * @param bool $successful
     */
    private function sendReply(PsrContext $context, PsrMessage $message, $successful)
    {
        $replyMessage = $context->createMessage($message->getBody(), $message->getProperties(), $message->getHeaders());
        $replyMessage->setProperty('fos-populate-successful', (int) $successful);

        $replyQueue = $context->createQueue($message->getReplyTo());

        $context->createProducer()->send($replyQueue, $replyMessage);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedQueues()
    {
        return ['fos_elastica_populate'];
    }
}
