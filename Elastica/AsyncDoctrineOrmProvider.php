<?php
namespace Enqueue\ElasticaBundle\Elastica;

use Enqueue\Psr\Context;
use Enqueue\Util\JSON;
use FOS\ElasticaBundle\Doctrine\ORM\Provider;

class AsyncDoctrineOrmProvider extends Provider
{
    private $batchSize;

    /**
     * @var Context
     */
    private $context;

    /**
     * @param Context $context
     */
    public function setContext(Context $context)
    {
        $this->context = $context;
    }

    /**
     * {@inheritDoc}
     */
    protected function doPopulate($options, \Closure $loggerClosure = null)
    {
        if (getenv('ENQUEUE_ELASTICA_DISABLE_ASYNC')) {
            return parent::doPopulate($options, $loggerClosure);
        }

        $this->batchSize = null;
        if ($options['real_populate']) {
            $this->batchSize = $options['offset'] + $options['batch_size'];

            return parent::doPopulate($options, $loggerClosure);
        }

        $queryBuilder = $this->createQueryBuilder($options['query_builder_method']);
        $nbObjects = $this->countObjects($queryBuilder);
        $offset = $options['offset'];

        $queue = $this->context->createQueue('fos_elastica.populate');
        $resultQueue = $this->context->createTemporaryQueue();
        $consumer = $this->context->createConsumer($resultQueue);

        $producer = $this->context->createProducer();

        $nbMessages = 0;
        for (; $offset < $nbObjects; $offset += $options['batch_size']) {
            $options['offset'] = $offset;
            $options['real_populate'] = true;
            $message = $this->context->createMessage(JSON::encode($options));
            $message->setReplyTo($resultQueue->getQueueName());
            $producer->send($queue, $message);

            $nbMessages++;
        }

        $limitTime = time() + 180;
        while ($nbMessages) {
            if ($message = $consumer->receive(20000)) {
                $errorMessage = null;

                $errorMessage = null;
                if (false == $message->getProperty('fos-populate-successful', false)) {
                    $errorMessage = sprintf(
                        '<error>Batch failed: </error> <comment>Failed to process message %s</comment>',
                        $message->getBody()
                    );
                }

                if ($loggerClosure) {
                    $loggerClosure($options['batch_size'], $nbObjects, $errorMessage);
                }

                $consumer->acknowledge($message);

                $nbMessages--;

                $limitTime = time() + 180;
            }

            if (time() > $limitTime) {
                throw new \LogicException(sprintf('No response in %d seconds', 180));
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function countObjects($queryBuilder)
    {
        return $this->batchSize ? $this->batchSize : parent::countObjects($queryBuilder);
    }

    /**
     * {@inheritDoc}
     */
    protected function configureOptions()
    {
        parent::configureOptions();

        $this->resolver->setDefaults([
            'real_populate' => false,
        ]);
    }
}
