<?php
namespace Enqueue\ElasticaBundle\Elastica;

use Enqueue\Client\ProducerInterface;
use Enqueue\ElasticaBundle\Async\Commands;
use Enqueue\Rpc\Promise;
use FOS\ElasticaBundle\Doctrine\ORM\Provider;

class AsyncDoctrineOrmProvider extends Provider
{
    /**
     * @var int
     */
    private $batchSize;

    /**
     * @var ProducerInterface
     */
    private $producer;

    /**
     * @param ProducerInterface $producer
     */
    public function setContext(ProducerInterface $producer)
    {
        $this->producer = $producer;
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

        /** @var Promise[] $promises */
        $promises = [];
        for (; $offset < $nbObjects; $offset += $options['batch_size']) {
            $options['offset'] = $offset;
            $options['real_populate'] = true;

            $promises[] = $this->producer->sendCommand(Commands::POPULATE, $options, true);
        }

        $limitTime = time() + 180;
        while ($promises) {
            foreach ($promises as $index => $promise) {
                if ($message = $promise->receiveNoWait()) {
                    unset($promises[$index]);

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

                    $limitTime = time() + 180;
                }

                sleep(1);

                if (time() > $limitTime) {
                    throw new \LogicException(sprintf('No response in %d seconds', 180));
                }
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
