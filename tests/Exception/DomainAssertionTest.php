<?php

namespace MetaSyntactical\Io\Tests\Exception;

use MetaSyntactical\Io\Exception\DomainAssertion;

class DomainAssertionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var DomainAssertion
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new DomainAssertion;
    }

    public function testThatClassProvidesTheExpectedInterfaces()
    {
        self::assertInstanceOf('\\LogicException', $this->object);
        self::assertInstanceOf('\\DomainException', $this->object);
        self::assertInstanceOf('\\MetaSyntactical\\Io\\Exception\\Exception', $this->object);
    }
}
