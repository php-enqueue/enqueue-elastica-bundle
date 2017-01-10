<?php
namespace Enqueue\ElasticaBundle\Async;

use Enqueue\Psr\Context;
use Enqueue\Psr\Message;
use Enqueue\Psr\Processor;
use Enqueue\Util\JSON;
use FOS\ElasticaBundle\Provider\ProviderRegistry;

class ElasticaPopulateProcessor implements Processor
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
    public function process(Message $message, Context $context)
    {
        if (false == $message->getReplyTo()) {
            return self::REJECT;
        }

        if ($message->isRedelivered()) {
            $replyMessage = $context->createMessage(false);
            $replyQueue = $context->createQueue($message->getReplyTo());
            $context->createProducer()->send($replyQueue, $replyMessage);

            return self::REJECT;
        }

        $options = JSON::decode($message->getBody());

        $provider = $this->providerRegistry->getProvider($options['indexName'], $options['typeName']);
        $provider->populate(null, $options);

        $this->sendReply($context, $message->getReplyTo(), true);

        return self::ACK;
    }

    /**
     * @param Context $context
     * @param string $replyTo
     * @param bool $message
     */
    private function sendReply(Context $context, $replyTo, $message)
    {
        $replyMessage = $context->createMessage($message);
        $replyQueue = $context->createQueue($replyTo);
        $context->createProducer()->send($replyQueue, $replyMessage);
    }
}
