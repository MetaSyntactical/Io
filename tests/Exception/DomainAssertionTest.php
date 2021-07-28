<?php

namespace MetaSyntactical\Io\Tests\Exception;

use DomainException;
use LogicException;
use MetaSyntactical\Io\Exception\DomainAssertion;
use MetaSyntactical\Io\Exception\Exception;
use PHPUnit\Framework\TestCase;

class DomainAssertionTest extends TestCase
{
    protected DomainAssertion $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new DomainAssertion;
    }

    public function testThatClassProvidesTheExpectedInterfaces(): void
    {
        self::assertInstanceOf(LogicException::class, $this->object);
        self::assertInstanceOf(DomainException::class, $this->object);
        self::assertInstanceOf(Exception::class, $this->object);
    }
}
