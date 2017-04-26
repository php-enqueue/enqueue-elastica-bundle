# Enqueue Elastica Bundle

Improves performance of `fos:elastica:populate` commands by distributing the work among consumers. 
The performance gain depends on how much consumers you run. 
For example 10 consumers may give you 5 to 7 times better performance.  

## Installation

Install packages using [composer](https://getcomposer.org/)

```bash
$ composer require enqueue/elastica-bundle friendsofsymfony/elastica-bundle
```

Add bundles to `AppKernel`

```php
<?php
use Symfony\Component\HttpKernel\Kernel;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            // ...
            
            new Enqueue\Bundle\EnqueueBundle(),
            new Enqueue\ElasticaBundle\EnqueueElasticaBundle(),
            new FOS\ElasticaBundle\FOSElasticaBundle(),
        ];
        
        return $bundles;
    }
}
```

Here's an example of what your `FOSElasticaBundle` configuration may look like:

```yaml
fos_elastica:
    clients:
        default: { host: %elasticsearch_host%, port: %elasticsearch_port% }
    indexes:
        app:
            index_name: app_%kernel.environment%
            types:
                blog:
                    mappings:
                        text: ~
                    persistence:
                        driver: orm
                        model: AppBundle\Entity\Blog
                        provider: ~
                        listener: ~
                        finder: ~
```

Here's an example of what your EnqueueBundle configuration may look like:

```yaml
enqueue:
    transport:
        default: 'fs'
        fs:
            store_dir: %kernel.root_dir%/../var/messages
```

Sure you can configure other transports like: [rabbitmq, amqp, stomp and so on](https://github.com/php-enqueue/enqueue-dev/blob/master/docs/bundle/config_reference.md)
Create a `fos_elastica.populate` queue on broker side, if needed.

## Usage 

The bundle once registered automatically replaces Doctrine ORM provider by async one. 
So you have to run as usual 
 
```bash
$ ./bin/console fos:elastica:populate 
```

If you want to disable this behavior you can un register the bundle or use env var

```bash
$ ENQUEUE_ELASTICA_DISABLE_ASYNC=1 ./bin/console fos:elastica:populate 
```

and have pull of consumer commands run somewhere, run them as many as you'd like
 
```bash
$ ./bin/console enqueue:transport:consume fos_elastica.populate enqueue_elastica.populate_processor 
```

We suggest to use [supervisor](http://supervisord.org/) on production to control numbers of consumers and restart them.   

Here's config example

```
# cat /etc/supervisor/conf.d/fos_elastica_populate.conf 
[program:fos_elastica_populate]
command=/mqs/symfony/bin/console enqueue:transport:consume fos_elastica.populate enqueue_elastica.populate_processor
process_name=%(program_name)s_%(process_num)02d
numprocs=10
autostart=true
autorestart=true
startsecs=0
user=root
redirect_stderr=true
```

## Developed by Forma-Pro

Forma-Pro is a full stack development company which interests also spread to open source development. Being a team of strong professionals we have an aim an ability to help community by developing cutting edge solutions in the areas of e-commerce, docker & microservice oriented architecture where we have accumulated a huge many-years experience. Our main specialization is Symfony framework based solution, but we are always looking to the technologies that allow us to do our job the best way. We are committed to creating solutions that revolutionize the way how things are developed in aspects of architecture & scalability.
If you have any questions and inquires about our open source development, this product particularly or any other matter feel free to contact at opensource@forma-pro.com

## License

It is released under the [MIT License](LICENSE).
