<?php

namespace MetaSyntactical\Io\Tests\Exception;

use MetaSyntactical\Io\Exception\OutOfRangeException;

class OutOfRangeExceptionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var OutOfRangeException
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new OutOfRangeException;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    public function testThatClassProvidesTheExpectedInterfaces()
    {
        self::assertInstanceOf('\\LogicException', $this->object);
        self::assertInstanceOf('\\OutOfRangeException', $this->object);
        self::assertInstanceOf('\\MetaSyntactical\\Io\\Exception\\Exception', $this->object);
    }
}
