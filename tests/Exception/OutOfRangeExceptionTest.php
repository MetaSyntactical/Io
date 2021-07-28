<?php

namespace MetaSyntactical\Io\Tests\Exception;

use LogicException;
use MetaSyntactical\Io\Exception\OutOfRangeException;
use OutOfRangeException as BaseOutOfRangeException;
use PHPUnit\Framework\TestCase;
use MetaSyntactical\Io\Exception\Exception;

class OutOfRangeExceptionTest extends TestCase
{
    protected OutOfRangeException $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new OutOfRangeException;
    }

    public function testThatClassProvidesTheExpectedInterfaces(): void
    {
        self::assertInstanceOf(LogicException::class, $this->object);
        self::assertInstanceOf(BaseOutOfRangeException::class, $this->object);
        self::assertInstanceOf(Exception::class, $this->object);
    }
}
