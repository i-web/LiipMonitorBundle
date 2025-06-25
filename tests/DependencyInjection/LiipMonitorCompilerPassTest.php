<?php

/*
 * This file is part of the liip/monitor-bundle package.
 *
 * (c) Liip
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Liip\Monitor\Tests\DependencyInjection;

use Liip\Monitor\Check\CheckContext;
use Liip\Monitor\Check\Doctrine\DbalConnectionCheck;
use Liip\Monitor\Check\Symfony\SymfonyMessengerReceiverCheck;
use Liip\Monitor\DependencyInjection\LiipMonitorExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class LiipMonitorCompilerPassTest extends AbstractCompilerPassTestCase
{
    /**
     * @test
     */
    public function adds_default_doctrine_connection_checks(): void
    {
        $this->setParameter('liip_monitor.check.doctrine_dbal_connection.all', []);
        $this->setParameter('doctrine.connections', ['default' => 'service']);

        $this->compile();

        $this->assertContainerBuilderHasService('.liip_monitor.check.doctrine_dbal_connection.default', DbalConnectionCheck::class);
        $this->assertContainerBuilderHasService('.liip_monitor.check.doctrine_dbal_connection.default.context', CheckContext::class);
    }

    /**
     * @test
     */
    public function adds_default_symfony_messenger_receiver_checks(): void
    {
        $this->setParameter('liip_monitor.check.symfony_messenger_receiver.all', []);
        $this->registerService('default', 'service')->addTag('messenger.receiver');

        $this->compile();

        $this->assertContainerBuilderHasService('.liip_monitor.check.symfony_messenger_receiver.default', SymfonyMessengerReceiverCheck::class);
        $this->assertContainerBuilderHasService('.liip_monitor.check.symfony_messenger_receiver.default.context', CheckContext::class);
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new LiipMonitorExtension());
    }
}
