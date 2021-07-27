<?php

namespace MetaSyntactical\Io\Tests\Exception;

use MetaSyntactical\Io\Exception\OutOfRangeException;

class OutOfRangeExceptionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var OutOfRangeException
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new OutOfRangeException;
    }

    public function testThatClassProvidesTheExpectedInterfaces()
    {
        self::assertInstanceOf('\\LogicException', $this->object);
        self::assertInstanceOf('\\OutOfRangeException', $this->object);
        self::assertInstanceOf('\\MetaSyntactical\\Io\\Exception\\Exception', $this->object);
    }
}
