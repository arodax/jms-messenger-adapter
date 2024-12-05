<?php

declare(strict_types=1);

namespace KunicMarko\JMSMessengerAdapter\Features\Fixtures\Project;

use FriendsOfBehat\SymfonyExtension\Bundle\FriendsOfBehatSymfonyExtensionBundle;
use JMS\SerializerBundle\JMSSerializerBundle;
use KunicMarko\JMSMessengerAdapter\Bridge\Symfony\JMSMessengerAdapterBundle;
use KunicMarko\JMSMessengerAdapter\Features\Fixtures\Project\Middleware\AddStampMiddleware;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use KunicMarko\JMSMessengerAdapter\Features\Fixtures\Project\DependencyInjection\Compiler\ExposeServicesAsPublicForTestingCompilerPass;
use KunicMarko\JMSMessengerAdapter\Features\Fixtures\Project\Query\DoesItWork;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return __DIR__.'/..';
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/test';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir().'/var/logs';
    }

    public function registerBundles(): \Generator
    {
        yield new FrameworkBundle();
        yield new JMSSerializerBundle();
        yield new JMSMessengerAdapterBundle();

        if ($this->getEnvironment() === 'test') {
            yield new FriendsOfBehatSymfonyExtensionBundle();
        }
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->setParameter('kernel.secret', 'secret');

        // Register AddStampMiddleware
        $container->setDefinition(
            AddStampMiddleware::class,
            new Definition(AddStampMiddleware::class)
        );

        // Register Behat Context as a Service
        $container->register('KunicMarko\JMSMessengerAdapter\Features\Context\JMSMessengerAdapterContext')
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setArguments([
                new Reference('messenger.bus.default'),
                new Reference('messenger.receiver_locator'),
            ]);

        // Configure Framework and Messenger
        $container->prependExtensionConfig('framework', [
            'messenger' => [
                'transports' => [
                    'amqp' => 'amqp://guest:guest@localhost:5672/%2f/messages',
                ],
                'routing' => [
                    DoesItWork::class => 'amqp',
                ],
                'serializer' => [
                    'default_serializer' => 'messenger.transport.jms_serializer',
                ],
                'buses' => [
                    'messenger.bus.default' => [
                        'middleware' => [
                            AddStampMiddleware::class,
                        ],
                    ],
                ],
            ],
        ]);

        // Configure JMS Serializer
        $container->prependExtensionConfig('jms_serializer', [
            'metadata' => [
                'directories' => [
                    'not sure if this string is important' => [
                        'namespace_prefix' => 'KunicMarko\JMSMessengerAdapter\Features\Fixtures\Project',
                        'path' => '%kernel.project_dir%/config/serializer',
                    ],
                ],
            ],
        ]);

        // Add Compiler Pass for Testing
        $container->addCompilerPass(new ExposeServicesAsPublicForTestingCompilerPass());
    }
}
