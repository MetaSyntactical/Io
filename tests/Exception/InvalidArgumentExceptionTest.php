<?php

namespace MetaSyntactical\Io\Tests\Exception;

use InvalidArgumentException as BaseInvalidArgumentException;
use LogicException;
use MetaSyntactical\Io\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use MetaSyntactical\Io\Exception\Exception;

class InvalidArgumentExceptionTest extends TestCase
{
    protected InvalidArgumentException $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new InvalidArgumentException;
    }

    public function testThatClassProvidesTheExpectedInterfaces(): void
    {
        self::assertInstanceOf(LogicException::class, $this->object);
        self::assertInstanceOf(BaseInvalidArgumentException::class, $this->object);
        self::assertInstanceOf(Exception::class, $this->object);
    }
}
