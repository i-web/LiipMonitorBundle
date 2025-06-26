<?php

/*
 * This file is part of the liip/monitor-bundle package.
 *
 * (c) Liip
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Liip\Monitor\Tests\Check\Symfony;

use Liip\Monitor\Check\Symfony\SymfonyMessengerReceiverCheck;
use Liip\Monitor\Result;
use Liip\Monitor\Result\Status;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

final class SymfonyMessengerReceiverCheckTest extends TestCase
{
    /**
     * @test
     */
    public function can_run(): void
    {
        $receiver = $this->createMock(MessageCountAwareInterface::class);
        $receiver->expects($this->once())
            ->method('getMessageCount')
            ->willReturn(0);

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->expects($this->once())
            ->method('get')
            ->with('default')
            ->willReturn($receiver);

        $check = new SymfonyMessengerReceiverCheck($serviceLocator, 'default');

        $this->assertSame('Symfony Messenger Receiver "default"', (string) $check);
        $this->assertSame(Status::SUCCESS, $check->run()->status());
    }

    /**
     * @test
     */
    public function not_message_count_aware(): void
    {
        $receiver = $this->createMock(ReceiverInterface::class);

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->expects($this->once())
            ->method('get')
            ->with('default')
            ->willReturn($receiver);

        $check = new SymfonyMessengerReceiverCheck($serviceLocator, 'default');

        $this->assertEquals(Result::skip('Not a MessageCountAwareInterface.'), $check->run());
    }

    /**
     * @test
     */
    public function get_message_count_throws_exception(): void
    {
        $receiver = $this->createMock(MessageCountAwareInterface::class);
        $receiver->expects($this->once())
            ->method('getMessageCount')
            ->willThrowException(new \Exception('Connection error'));

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->expects($this->once())
            ->method('get')
            ->with('default')
            ->willReturn($receiver);

        $check = new SymfonyMessengerReceiverCheck($serviceLocator, 'default');

        $this->assertEquals(Result::failure('failed'), $check->run());
    }
}
