<?php

/*
 * This file is part of the liip/monitor-bundle package.
 *
 * (c) Liip
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Liip\Monitor\Tests\Check\Flysystem;

use League\Flysystem\DirectoryListing;
use League\Flysystem\Filesystem;
use Liip\Monitor\Check\Flysystem\FlysystemStorageCheck;
use Liip\Monitor\Result;
use Liip\Monitor\Tests\CheckTests;
use PHPUnit\Framework\TestCase;

final class FlysystemStorageCheckDirectoryTest extends TestCase
{
    use CheckTests;

    public static function checkResultProvider(): iterable
    {
        // Test successful directory operations (write, read, delete)
        yield [
            function() {
                $storage = (new FlysystemStorageCheckDirectoryTest())->createMock(Filesystem::class);
                // For write operation
                $storage->expects(self::exactly(2))
                    ->method('directoryExists')
                    ->with('/test-dir')
                    ->willReturn(true);
                $storage->expects(self::exactly(2))
                    ->method('visibility')
                    ->with('/test-dir');

                // For read operation
                $directoryListing = (new FlysystemStorageCheckDirectoryTest())->createMock(DirectoryListing::class);
                $directoryListing->expects(self::once())
                    ->method('toArray')
                    ->willReturn([]);

                $storage->expects(self::once())
                    ->method('listContents')
                    ->with('/test-dir', false)
                    ->willReturn($directoryListing);

                return new FlysystemStorageCheck(
                    $storage,
                    'default',
                    'directory',
                    ['write', 'read', 'delete'],
                    '/test-dir'
                );
            },
            Result::success('successfull operations: write, read, delete'),
            'Flysystem Storage "default"',
        ];

        // Test successful write operation only
        yield [
            function() {
                $storage = (new FlysystemStorageCheckDirectoryTest())->createMock(Filesystem::class);
                $storage->expects(self::once())
                    ->method('directoryExists')
                    ->with('/test-dir')
                    ->willReturn(true);
                $storage->expects(self::once())
                    ->method('visibility')
                    ->with('/test-dir');

                $storage->expects(self::never())
                    ->method('listContents');

                return new FlysystemStorageCheck(
                    $storage,
                    'default',
                    'directory',
                    ['write'],
                    '/test-dir'
                );
            },
            Result::success('successfull operations: write'),
        ];

        // Test failed write operation
        yield [
            function() {
                $storage = (new FlysystemStorageCheckDirectoryTest())->createMock(Filesystem::class);
                $storage->expects(self::once())
                    ->method('directoryExists')
                    ->with('/test-dir')
                    ->willReturn(false);

                $storage->expects(self::never())
                    ->method('visibility');
                $storage->expects(self::never())
                    ->method('listContents');

                return new FlysystemStorageCheck(
                    $storage,
                    'default',
                    'directory',
                    ['write'],
                    '/test-dir'
                );
            },
            Result::failure('failed operations: write'),
        ];

        // Test failed read operation
        yield [
            function() {
                $storage = (new FlysystemStorageCheckDirectoryTest())->createMock(Filesystem::class);
                $storage->expects(self::once())
                    ->method('directoryExists')
                    ->with('/test-dir')
                    ->willReturn(true);
                $storage->expects(self::once())
                    ->method('visibility')
                    ->with('/test-dir');

                $storage->expects(self::once())
                    ->method('listContents')
                    ->with('/test-dir', false)
                    ->willThrowException(new \Exception('Read error'));

                return new FlysystemStorageCheck(
                    $storage,
                    'default',
                    'directory',
                    ['write', 'read'],
                    '/test-dir'
                );
            },
            Result::failure('failed operations: read'),
        ];

        // Test failed delete operation (same as write since delete uses canWriteToDirectory)
        yield [
            function() {
                $storage = (new FlysystemStorageCheckDirectoryTest())->createMock(Filesystem::class);
                $storage->expects(self::exactly(2))
                    ->method('directoryExists')
                    ->with('/test-dir')
                    ->willReturnOnConsecutiveCalls(true, false);

                $storage->expects(self::once())
                    ->method('visibility')
                    ->with('/test-dir');

                $directoryListing = (new FlysystemStorageCheckDirectoryTest())->createMock(DirectoryListing::class);
                $directoryListing->expects(self::once())
                    ->method('toArray')
                    ->willReturn([]);

                $storage->expects(self::once())
                    ->method('listContents')
                    ->with('/test-dir', false)
                    ->willReturn($directoryListing);

                return new FlysystemStorageCheck(
                    $storage,
                    'default',
                    'directory',
                    ['write', 'read', 'delete'],
                    '/test-dir'
                );
            },
            Result::failure('failed operations: delete'),
        ];

        // Test with different storage name
        yield [
            function() {
                $storage = (new FlysystemStorageCheckDirectoryTest())->createMock(Filesystem::class);
                // For write and delete operations
                $storage->expects(self::exactly(2))
                    ->method('directoryExists')
                    ->with('/test-dir')
                    ->willReturn(true);
                $storage->expects(self::exactly(2))
                    ->method('visibility')
                    ->with('/test-dir');

                // For read operation
                $directoryListing = (new FlysystemStorageCheckDirectoryTest())->createMock(DirectoryListing::class);
                $directoryListing->expects(self::once())
                    ->method('toArray')
                    ->willReturn([]);

                $storage->expects(self::once())
                    ->method('listContents')
                    ->with('/test-dir', false)
                    ->willReturn($directoryListing);

                return new FlysystemStorageCheck(
                    $storage,
                    'custom_storage',
                    'directory',
                    ['write', 'read', 'delete'],
                    '/test-dir'
                );
            },
            Result::success('successfull operations: write, read, delete'),
            'Flysystem Storage "custom_storage"',
        ];

        // Test with different path
        yield [
            function() {
                $storage = (new FlysystemStorageCheckDirectoryTest())->createMock(Filesystem::class);
                // For write and delete operations
                $storage->expects(self::exactly(2))
                    ->method('directoryExists')
                    ->with('/custom/path')
                    ->willReturn(true);
                $storage->expects(self::exactly(2))
                    ->method('visibility')
                    ->with('/custom/path');

                // For read operation
                $directoryListing = (new FlysystemStorageCheckDirectoryTest())->createMock(DirectoryListing::class);
                $directoryListing->expects(self::once())
                    ->method('toArray')
                    ->willReturn([]);

                $storage->expects(self::once())
                    ->method('listContents')
                    ->with('/custom/path', false)
                    ->willReturn($directoryListing);

                return new FlysystemStorageCheck(
                    $storage,
                    'default',
                    'directory',
                    ['write', 'read', 'delete'],
                    '/custom/path'
                );
            },
            Result::success('successfull operations: write, read, delete'),
        ];

        // Test path normalization (file path converted to directory)
        yield [
            function() {
                $storage = (new FlysystemStorageCheckDirectoryTest())->createMock(Filesystem::class);
                // For write and delete operations
                $storage->expects(self::exactly(2))
                    ->method('directoryExists')
                    ->with('/custom')
                    ->willReturn(true);
                $storage->expects(self::exactly(2))
                    ->method('visibility')
                    ->with('/custom');

                // For read operation
                $directoryListing = (new FlysystemStorageCheckDirectoryTest())->createMock(DirectoryListing::class);
                $directoryListing->expects(self::once())
                    ->method('toArray')
                    ->willReturn([]);

                $storage->expects(self::once())
                    ->method('listContents')
                    ->with('/custom', false)
                    ->willReturn($directoryListing);

                return new FlysystemStorageCheck(
                    $storage,
                    'default',
                    'directory',
                    ['write', 'read', 'delete'],
                    '/custom/file.txt'
                );
            },
            Result::success('successfull operations: write, read, delete'),
        ];
    }
}
