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
     * @param Context $context
     * @param Message $message
     * @param bool $successful
     */
    private function sendReply(Context $context, Message $message, $successful)
    {
        $replyMessage = $context->createMessage($message->getBody(), $message->getProperties(), $message->getHeaders());
        $replyMessage->setProperty('fos-populate-successful', (int) $successful);

        $replyQueue = $context->createQueue($message->getReplyTo());

        $context->createProducer()->send($replyQueue, $replyMessage);
    }
}
