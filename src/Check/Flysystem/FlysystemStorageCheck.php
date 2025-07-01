<?php

/*
 * This file is part of the liip/monitor-bundle package.
 *
 * (c) Liip
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Liip\Monitor\Check\Flysystem;

use League\Flysystem\Filesystem;
use Liip\Monitor\Check;
use Liip\Monitor\DependencyInjection\ConfigurableCheck;
use Liip\Monitor\DependencyInjection\Configuration;
use Liip\Monitor\Result;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

final class FlysystemStorageCheck implements Check, ConfigurableCheck, \Stringable
{
    private const ALL_STORAGES = '__ALL__';

    /**
     * @param array<'write'|'read'|'delete'> $operations
     */
    public function __construct(
        private readonly Filesystem $storage,
        private readonly string $name,
        private readonly array $operations,
        private readonly string $path,
    ) {
    }

    #[\Override]
    public function __toString(): string
    {
        return \sprintf('Flysystem Storage "%s"', $this->name);
    }

    #[\Override]
    public function run(): Result
    {
        $successfullOperations = [];
        $failedOperations = [];
        if (\in_array('write', $this->operations, true)) {
            try {
                $this->storage->write($this->path, 'test');
                $successfullOperations[] = 'write';
            } catch (\Throwable) {
                $failedOperations[] = 'write';
            }
        }
        if (\in_array('read', $this->operations, true)) {
            try {
                $this->storage->read($this->path);
                $successfullOperations[] = 'read';
            } catch (\Throwable) {
                $failedOperations[] = 'read';
            }
        }
        if (\in_array('delete', $this->operations, true)) {
            try {
                $this->storage->delete($this->path);
                $successfullOperations[] = 'delete';
            } catch (\Throwable) {
                $failedOperations[] = 'delete';
            }
        }

        if (\count($failedOperations) > 0) {
            return Result::failure('failed operations: '.\implode(', ', $failedOperations));
        }

        return Result::success('successfull operations: '.\implode(', ', $successfullOperations));
    }

    #[\Override]
    public static function configKey(): string
    {
        return 'flysystem_storage';
    }

    #[\Override]
    public static function configInfo(): ?string
    {
        return 'fails if it cannot write/read/delete a file.';
    }

    // inspired by DbalConnectionCheck
    #[\Override]
    public static function addConfig(ArrayNodeDefinition $node): NodeDefinition
    {
        return $node // @phpstan-ignore-line
            ->beforeNormalization()
                ->ifTrue(fn($v) => \is_array($v) && \array_is_list($v))
                ->then(fn($v) => \array_map(static fn() => [], \array_combine($v, $v)))
            ->end()
            ->beforeNormalization()
                ->ifString()->then(fn(string $v) => [['name' => $v]])
            ->end()
            ->beforeNormalization()
                ->ifTrue()->then(fn() => [['name' => self::ALL_STORAGES]])
            ->end()
            ->beforeNormalization()
                ->ifTrue(fn($v) => \is_array($v) && isset($v['suite']))
                ->then(fn($v) => [['name' => self::ALL_STORAGES, ...$v]])
            ->end()
            ->useAttributeAsKey('name')
        ->arrayPrototype()
            ->children()
                ->arrayNode('operations')
                    ->prototype('scalar')->end()
                    ->info('The operations to perform. Possible values are: write, read, delete.')
                    ->defaultValue(['write', 'read', 'delete'])
                ->end()
                ->scalarNode('path')
                    ->defaultValue('monitor.txt')
                ->end()
                ->append(Configuration::addSuiteConfig())
                ->append(Configuration::addTtlConfig())
                ->append(Configuration::addLabelConfig())
                ->append(Configuration::addIdConfig())
            ->end()
        ->end()
        ;
    }

    // inspired by DbalConnectionCheck
    #[\Override]
    public static function load(array $config, ContainerBuilder $container): void
    {
        if ([self::ALL_STORAGES] === \array_keys($config)) {
            // handle in compiler pass
            $container->setParameter('liip_monitor.check.'.self::configKey().'.all', $config[self::ALL_STORAGES]);

            return;
        }

        foreach ($config as $name => $check) {
            $container->register(\sprintf('.liip_monitor.check.'.self::configKey().'.%s', $name), self::class)
                      ->setArguments(
                          [new Reference($name),
                              $name,
                              $check['operations'],
                              $check['path'],
                          ])
                      ->addTag('liip_monitor.check', $check)
            ;
        }
    }

    // inspired by DbalConnectionCheck
    public static function process(ContainerBuilder $container): void
    {
        $checkAllTransportsParameterName = 'liip_monitor.check.'.self::configKey().'.all';
        if (!$container->hasParameter($checkAllTransportsParameterName)) {
            return;
        }
        $config = $container->getParameter($checkAllTransportsParameterName);
        $container->getParameterBag()->remove($checkAllTransportsParameterName);

        $storages = $container->findTaggedServiceIds('flysystem.storage');
        if (0 === \count($storages)) {
            throw new LogicException('Could not determine Flysystem storages. Is league/flysystem-bundle installed/enabled?');
        }
        $config = \array_map(static fn() => $config, $storages);

        self::load($config, $container);
    }
}
