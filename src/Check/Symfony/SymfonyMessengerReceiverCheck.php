<?php

namespace Liip\Monitor\Check\Symfony;

use Liip\Monitor\Check;
use Liip\Monitor\DependencyInjection\ConfigurableCheck;
use Liip\Monitor\DependencyInjection\Configuration;
use Liip\Monitor\Result;
use Override;
use Stringable;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Throwable;

use function array_combine;
use function array_is_list;
use function array_keys;
use function array_map;
use function is_array;
use function sprintf;

final class SymfonyMessengerReceiverCheck implements Check, ConfigurableCheck, Stringable
{
    private const ALL_RECEIVERS = '__ALL__';

    /** @param ServiceLocator<TransportInterface> $transportLocator */
    public function __construct(
        private readonly ServiceLocator $transportLocator,
        private readonly string $name,
    ) {}


    #[Override]
    public function __toString(): string
    {
        return sprintf('Symfony Messenger Receiver "%s"', $this->name);
    }

    #[Override]
    public function run(): Result
    {
        $receiver = $this->transportLocator->get($this->name);
        if (!($receiver instanceof MessageCountAwareInterface)) {
            return Result::skip('Not a MessageCountAwareInterface.');
        }

        try {
            $receiver->getMessageCount(); // this needs a working connection
            return Result::success('ok');
        } catch (Throwable) {
            return Result::failure('failed');
        }
    }

    #[Override]
    public static function configKey(): string
    {
        return 'symfony_messenger_receiver';
    }

    #[Override]
    public static function configInfo(): ?string
    {
        return 'fails if it cannot execute getMessageCount().';
    }

    // inspired by DbalConnectionCheck
    #[Override]
    public static function addConfig(ArrayNodeDefinition $node): NodeDefinition
    {
        return $node // @phpstan-ignore-line
        ->beforeNormalization()
            ->ifTrue(fn($v) => is_array($v) && array_is_list($v))
            ->then(fn($v) => array_map(static fn() => [], array_combine($v, $v)))
            ->end()
            ->beforeNormalization()
            ->ifString()->then(fn(string $v) => [['name' => $v]])
            ->end()
            ->beforeNormalization()
            ->ifTrue()->then(fn() => [['name' => self::ALL_RECEIVERS]])
            ->end()
            ->beforeNormalization()
            ->ifTrue(fn($v) => is_array($v) && isset($v['suite']))
            ->then(fn($v) => [['name' => self::ALL_RECEIVERS, ...$v]])
            ->end()
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->children()
            ->append(Configuration::addSuiteConfig())
            ->append(Configuration::addTtlConfig())
            ->append(Configuration::addLabelConfig())
            ->append(Configuration::addIdConfig())
            ->end()
            ->end()
            ;
    }

    // inspired by DbalConnectionCheck
    #[Override]
    public static function load(array $config, ContainerBuilder $container): void
    {
        if ([self::ALL_RECEIVERS] === array_keys($config)) {
            // handle in compiler pass
            $container->setParameter('liip_monitor.check.' . self::configKey() . '.all', $config[self::ALL_RECEIVERS]);
            return;
        }

        foreach ($config as $name => $check) {
            $container->register(sprintf('.liip_monitor.check.' . self::configKey() . '.%s', $name), self::class)
                ->setArguments([
                    new Reference('messenger.receiver_locator'),
                    $name,
                ])
                ->addTag('liip_monitor.check', $check)
            ;
        }
    }

    // inspired by DbalConnectionCheck
    public static function process(ContainerBuilder $container): void
    {
        $checkAllReceiversParameterName = 'liip_monitor.check.' . self::configKey() . '.all';
        if (!$container->hasParameter($checkAllReceiversParameterName)) {
            return;
        }
        $config = $container->getParameter($checkAllReceiversParameterName);
        $container->getParameterBag()->remove($checkAllReceiversParameterName);

        $receivers = $container->findTaggedServiceIds('messenger.receiver');
        if (count($receivers) === 0) {
            throw new LogicException('Could not determine Messenger receivers. Is symfony/messenger installed/enabled?');
        }
        $config = array_map(static fn() => $config, $receivers);

        self::load($config, $container);
    }

}
