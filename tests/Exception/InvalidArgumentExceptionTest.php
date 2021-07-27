<?php

namespace MetaSyntactical\Io\Tests\Exception;

use MetaSyntactical\Io\Exception\InvalidArgumentException;

class InvalidArgumentExceptionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var InvalidArgumentException
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new InvalidArgumentException;
    }

    public function testThatClassProvidesTheExpectedInterfaces()
    {
        self::assertInstanceOf('\\LogicException', $this->object);
        self::assertInstanceOf('\\InvalidArgumentException', $this->object);
        self::assertInstanceOf('\\MetaSyntactical\\Io\\Exception\\Exception', $this->object);
    }
}
