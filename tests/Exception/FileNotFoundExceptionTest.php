<?php

namespace MetaSyntactical\Io\Tests\Exception;

use MetaSyntactical\Io\Exception\FileNotFoundException;
use PHPUnit\Framework\TestCase;
use MetaSyntactical\Io\Exception\Exception;
use RuntimeException;

class FileNotFoundExceptionTest extends TestCase
{
    protected FileNotFoundException $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new FileNotFoundException;
    }

    public function testThatClassProvidesTheExpectedInterfaces(): void
    {
        self::assertInstanceOf(RuntimeException::class, $this->object);
        self::assertInstanceOf(Exception::class, $this->object);
    }
}
