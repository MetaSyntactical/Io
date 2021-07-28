<?php

namespace MetaSyntactical\Io\Tests;

use MetaSyntactical\Io\FileReader;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use MetaSyntactical\Io\Exception\FileNotFoundException;
use TypeError;

class FileReaderTest extends TestCase
{
    protected FileReader $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new FileReader(__DIR__ . '/_Data/testfile.txt');
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown(): void
    {
        if (isset($this->object) && is_object($this->object)) {
            $this->object->close();
            unset($this->object);
        }
    }

    public function testThat__constructThrowsExpectedExceptionIfGivenFilenameIsNoRealFile(): void
    {
        $this->expectException(
            FileNotFoundException::class,
        );
        $this->expectExceptionMessage('Unable to open file for reading');

        new FileReader('php://memory');
    }

    public function testThat__constructThrowsExpectedExceptionIfGivenFilenameIsNotReadable(): void
    {
        $structure = [
            'dir' => [
                'file' => 'dummy_file',
            ],
        ];
        vfsStream::setup('root', null, $structure);
        chmod('vfs://root/dir/file', '0000');

        $this->expectException(
            FileNotFoundException::class,
        );
        $this->expectExceptionMessage('Unable to open file for reading: vfs://root/dir/file');

        new FileReader('vfs://root/dir/file');
    }

    /**
     * @covers \MetaSyntactical\Io\FileReader::__destruct
     */
    public function testThat__destructClosesFileResourceCorrectly(): void
    {
        $fp = $this->object->getFileDescriptor();
        unset($this->object);
        $this->expectException(
            TypeError::class,
        );
        $this->expectExceptionMessage(
            'is not a valid stream resource'
        );

        fread($fp, 1);
    }
}
