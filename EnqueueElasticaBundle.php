<?php

namespace Enqueue\ElasticaBundle;

use Enqueue\ElasticaBundle\DependencyInjection\Compiler\AsyncProviderCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EnqueueElasticaBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new AsyncProviderCompilerPass());
    }
}
