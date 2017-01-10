<?php
namespace Enqueue\ElasticaBundle\DependencyInjection\Compiler;

use Enqueue\ElasticaBundle\Elastica\AsyncDoctrineOrmProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AsyncProviderCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getExtensionConfig('fos_elastica') as $config) {
            foreach ($config['indexes'] as $index => $indexData) {
                foreach ($indexData['types'] as $type => $typeData) {
                    if ('orm' != $typeData['persistence']['driver']) {
                        continue;
                    }

                    $providerId = sprintf('fos_elastica.provider.%s.%s', $index, $type);
                    if (false == $container->hasDefinition($providerId)) {
                        continue;
                    }

                    $provider = $container->getDefinition($providerId);
                    $provider->setClass(AsyncDoctrineOrmProvider::class);
                    $provider->addMethodCall('setContext', [new Reference('enqueue.transport.context')]);
                }
            }
        }
    }
}
