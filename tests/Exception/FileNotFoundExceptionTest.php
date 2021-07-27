<?php

namespace MetaSyntactical\Io\Tests\Exception;

use MetaSyntactical\Io\Exception\FileNotFoundException;

class FileNotFoundExceptionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var FileNotFoundException
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new FileNotFoundException;
    }

    public function testThatClassProvidesTheExpectedInterfaces()
    {
        self::assertInstanceOf('\\RuntimeException', $this->object);
        self::assertInstanceOf('\\MetaSyntactical\\Io\\Exception\\Exception', $this->object);
    }
}
