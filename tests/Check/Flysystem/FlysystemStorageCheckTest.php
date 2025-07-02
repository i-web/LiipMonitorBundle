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

use League\Flysystem\Filesystem;
use Liip\Monitor\Check\Flysystem\FlysystemStorageCheck;
use Liip\Monitor\Result;
use Liip\Monitor\Tests\CheckTests;
use PHPUnit\Framework\TestCase;

final class FlysystemStorageCheckTest extends TestCase
{
    use CheckTests;

    public static function checkResultProvider(): iterable
    {
        // Test successful write, read, delete operations
        yield [
            function() {
                $storage = (new FlysystemStorageCheckTest())->createMock(Filesystem::class);
                $storage->expects(self::once())
                    ->method('write')
                    ->with('test.txt', 'test');
                $storage->expects(self::once())
                    ->method('read')
                    ->with('test.txt')
                    ->willReturn('test');
                $storage->expects(self::once())
                    ->method('delete')
                    ->with('test.txt');

                return new FlysystemStorageCheck($storage, 'default', ['write', 'read', 'delete'], 'test.txt');
            },
            Result::success('successfull operations: write, read, delete'),
            'Flysystem Storage "default"',
        ];

        // Test successful write operation only
        yield [
            function() {
                $storage = (new FlysystemStorageCheckTest())->createMock(Filesystem::class);
                $storage->expects(self::once())
                    ->method('write')
                    ->with('test.txt', 'test');
                $storage->expects(self::never())
                    ->method('read');
                $storage->expects(self::never())
                    ->method('delete');

                return new FlysystemStorageCheck($storage, 'default', ['write'], 'test.txt');
            },
            Result::success('successfull operations: write'),
        ];

        // Test failed write operation
        yield [
            function() {
                $storage = (new FlysystemStorageCheckTest())->createMock(Filesystem::class);
                $storage->expects(self::once())
                    ->method('write')
                    ->with('test.txt', 'test')
                    ->willThrowException(new \Exception('Write error'));
                $storage->expects(self::never())
                    ->method('read');
                $storage->expects(self::never())
                    ->method('delete');

                return new FlysystemStorageCheck($storage, 'default', ['write'], 'test.txt');
            },
            Result::failure('failed operations: write'),
        ];

        // Test failed read operation
        yield [
            function() {
                $storage = (new FlysystemStorageCheckTest())->createMock(Filesystem::class);
                $storage->expects(self::once())
                    ->method('write')
                    ->with('test.txt', 'test');
                $storage->expects(self::once())
                    ->method('read')
                    ->with('test.txt')
                    ->willThrowException(new \Exception('Read error'));
                $storage->expects(self::never())
                    ->method('delete');

                return new FlysystemStorageCheck($storage, 'default', ['write', 'read'], 'test.txt');
            },
            Result::failure('failed operations: read'),
        ];

        // Test failed delete operation
        yield [
            function() {
                $storage = (new FlysystemStorageCheckTest())->createMock(Filesystem::class);
                $storage->expects(self::once())
                    ->method('write')
                    ->with('test.txt', 'test');
                $storage->expects(self::once())
                    ->method('read')
                    ->with('test.txt')
                    ->willReturn('test');
                $storage->expects(self::once())
                    ->method('delete')
                    ->with('test.txt')
                    ->willThrowException(new \Exception('Delete error'));

                return new FlysystemStorageCheck($storage, 'default', ['write', 'read', 'delete'], 'test.txt');
            },
            Result::failure('failed operations: delete'),
        ];

        // Test multiple failed operations
        yield [
            function() {
                $storage = (new FlysystemStorageCheckTest())->createMock(Filesystem::class);
                $storage->expects(self::once())
                    ->method('write')
                    ->with('test.txt', 'test')
                    ->willThrowException(new \Exception('Write error'));
                $storage->expects(self::once())
                    ->method('read')
                    ->with('test.txt')
                    ->willThrowException(new \Exception('Read error'));
                $storage->expects(self::never())
                    ->method('delete');

                return new FlysystemStorageCheck($storage, 'default', ['write', 'read'], 'test.txt');
            },
            Result::failure('failed operations: write, read'),
        ];

        // Test with different storage name
        yield [
            function() {
                $storage = (new FlysystemStorageCheckTest())->createMock(Filesystem::class);
                $storage->expects(self::once())
                    ->method('write')
                    ->with('test.txt', 'test');
                $storage->expects(self::once())
                    ->method('read')
                    ->with('test.txt')
                    ->willReturn('test');
                $storage->expects(self::once())
                    ->method('delete')
                    ->with('test.txt');

                return new FlysystemStorageCheck($storage, 'custom_storage', ['write', 'read', 'delete'], 'test.txt');
            },
            Result::success('successfull operations: write, read, delete'),
            'Flysystem Storage "custom_storage"',
        ];

        // Test with different path
        yield [
            function() {
                $storage = (new FlysystemStorageCheckTest())->createMock(Filesystem::class);
                $storage->expects(self::once())
                    ->method('write')
                    ->with('custom/path.txt', 'test');
                $storage->expects(self::once())
                    ->method('read')
                    ->with('custom/path.txt')
                    ->willReturn('test');
                $storage->expects(self::once())
                    ->method('delete')
                    ->with('custom/path.txt');

                return new FlysystemStorageCheck($storage, 'default', ['write', 'read', 'delete'], 'custom/path.txt');
            },
            Result::success('successfull operations: write, read, delete'),
        ];
    }
}
