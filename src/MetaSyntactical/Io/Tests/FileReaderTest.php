<?php

namespace MetaSyntactical\Io\Tests;

use MetaSyntactical\Io\FileReader;

class FileReaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FileReader
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new FileReader(__DIR__ . '/Data/testfile.txt');
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        if (isset($this->object) && is_object($this->object)) {
            $this->object->close();
            unset($this->object);
        }
    }

    public function testThat__constructThrowsExpectedExceptionIfGivenFilenameIsNoRealFile()
    {
        $this->setExpectedException(
            '\\MetaSyntactical\\Io\\Exception\\FileNotFoundException',
            'Unable to open file for reading'
        );
        new FileReader('php://memory');
    }

    /**
     * @covers MetaSyntactical\Io\FileReader::__destruct
     */
    public function testThat__destructClosesFileResourceCorrectly()
    {
        $fp = $this->object->getFileDescriptor();
        unset($this->object);
        $this->setExpectedException(
            '\\PHPUnit_Framework_Error_Warning',
            'is not a valid stream resource'
        );
        fread($fp, 1);
    }
}
