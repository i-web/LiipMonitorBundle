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
        private readonly string $mode,
        private readonly array $operations,
        private string $path,
    ) {
        $this->path = $this->normalizePath($path, $mode);
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
        $path = $this->path;

        if (\in_array('write', $this->operations, true)) {
            if (('directory' === $this->mode && $this->canWriteToDirectory($path)) || ('file' === $this->mode && $this->canWriteFile($path))) {
                $successfullOperations[] = 'write';
            } else {
                $failedOperations[] = 'write';
            }
        }

        if (\in_array('read', $this->operations, true)) {
            if (('directory' === $this->mode && $this->canReadFromDirectory($path)) || ('file' === $this->mode && $this->canReadFile($path))) {
                $successfullOperations[] = 'read';
            } else {
                $failedOperations[] = 'read';
            }
        }

        if (\in_array('delete', $this->operations, true)) {
            if (('directory' === $this->mode && $this->canDeleteFromDirectory($path)) || ('file' === $this->mode && $this->canDeleteFile($path))) {
                $successfullOperations[] = 'delete';
            } else {
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
        return 'fails if it cannot write/read/delete a directory or file.';
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
                    ->scalarNode('mode')
                        ->info('The mode to use. Possible values are: file, directory.')
                        ->defaultValue('directory')
                    ->end()
                    ->arrayNode('operations')
                        ->prototype('scalar')->end()
                        ->info('The operations to perform. Possible values are: write, read, delete.')
                        ->defaultValue(['write', 'read', 'delete'])
                    ->end()
                    ->scalarNode('path')
                        ->info('The path to check. If mode is file, the path must end with a file name. If mode is directory, the path must end with a directory name.')
                        ->defaultValue('/')
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
                              $check['mode'] ?? 'directory',
                              $check['operations'] ?? ['write', 'read', 'delete'],
                              $check['path'] ?? '/',
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

    private function canWriteToDirectory(string $directoryPath): bool
    {
        try {
            if (!$this->storage->directoryExists($directoryPath)) { // check if directory exists
                return false;
            }
            $this->storage->visibility($directoryPath); // try to get and set visibility (indicates write permission)

            return true; // if we can read visibility, we likely have write access
        } catch (\Throwable) {
            return false;
        }
    }

    private function canReadFromDirectory(string $directoryPath): bool
    {
        try {
            $contents = $this->storage->listContents($directoryPath, false); // check if we can list directory contents
            $contents->toArray(); // try to count items (forces iteration)

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function canDeleteFromDirectory(string $directoryPath): bool
    {
        return $this->canWriteToDirectory($directoryPath);
    }

    private function canWriteFile(string $filePath): bool
    {
        try {
            $this->storage->write($filePath, 'test');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function canReadFile(string $filePath): bool
    {
        try {
            if (!$this->storage->fileExists($filePath)) {
                return false;
            }
            $this->storage->read($filePath);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function canDeleteFile(string $filePath): bool
    {
        try {
            if (!$this->storage->fileExists($filePath)) {
                return false;
            }
            $this->storage->delete($filePath);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizePath(string $path, string $mode): string
    {
        // Normalize path separators and clean up
        $normalized = \rtrim(\str_replace('\\', '/', $path), '/');
        if ('' === $normalized) {
            $normalized = '/';
        }

        if ('file' === $mode) {
            // For file mode, if path ends with '/' or is just '/', append a test file
            if ('/' === $normalized || \str_ends_with($normalized, '/')) {
                $normalized = \rtrim($normalized, '/').'/monitor-test.txt';
            }
        } elseif ('directory' === $mode) {
            // For directory mode, ensure we're working with a directory path
            if ('/' !== $normalized && \preg_match('/\.[a-zA-Z0-9]+$/', \basename($normalized))) {
                // If it looks like a file, use its directory
                $normalized = \dirname($normalized);
                if ('.' === $normalized) {
                    $normalized = '/';
                }
            }
        }

        return $normalized;
    }
}
