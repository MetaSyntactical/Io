<?php

namespace MetaSyntactical\Io\Tests;

use MetaSyntactical\Io\Reader;

class ReaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Reader
     */
    protected $object;

    /**
     * @var Reader
     */
    protected $advancedObject;

    /**
     * @var Reader
     */
    protected $unicodeObject;

    /**
     * @var Reader
     */
    protected $unicodeObjectBomLE;

    /**
     * @var Reader
     */
    protected $unicodeObjectBomBE;

    /**
     * @var Reader
     */
    protected $advancedObjectWithNullBytes;

    /**
     * @var resource
     */
    protected $fp;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->fp = fopen('php://memory', 'rw');
        for ($i = 0; $i < 128; $i++) {
            fwrite($this->fp, $i % 10, strlen($i % 10));
        }
        fseek($this->fp, 0);
        $this->object = new Reader($this->fp);

        $fp = fopen('php://memory', 'rwb');
        for ($i = 0; $i < 256; $i++) {
            fwrite($fp, chr($i), 1);
        }
        fseek($fp, 0);
        $this->advancedObject = new Reader($fp);

        $fp = fopen('php://memory', 'rwb');
        fwrite($fp, 'äöüß€@');
        fseek($fp, 0);
        $this->unicodeObject = new Reader($fp);

        $fp = fopen('php://memory', 'rwb');
        fwrite($fp, chr(0xff));
        fwrite($fp, chr(0xfe));
        fwrite($fp, 'äöüß€@');
        fseek($fp, 0);
        $this->unicodeObjectBomLE = new Reader($fp);

        $fp = fopen('php://memory', 'rwb');
        fwrite($fp, chr(0xfe));
        fwrite($fp, chr(0xff));
        fwrite($fp, 'äöüß€@');
        fseek($fp, 0);
        $this->unicodeObjectBomBE = new Reader($fp);

        $fp = fopen('php://memory', 'rwb');
        fwrite($fp, 'abc');
        fwrite($fp, chr(0x00));
        fwrite($fp, chr(0x00));
        fwrite($fp, chr(0x00));
        fwrite($fp, chr(0x00));
        fseek($fp, 0);
        $this->advancedObjectWithNullBytes = new Reader($fp);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::MACHINE_ENDIAN_ORDER);

        $this->object->close();
        unset($this->object);
        @fclose($this->fp);
        unset($this->fp);

        $this->advancedObject->close();
        unset($this->advancedObject);
        $this->unicodeObject->close();
        unset($this->unicodeObject);
        $this->unicodeObjectBomLE->close();
        unset($this->unicodeObjectBomLE);
        $this->unicodeObjectBomBE->close();
        unset($this->unicodeObjectBomBE);
        $this->advancedObjectWithNullBytes->close();
        unset($this->advancedObjectWithNullBytes);
    }

    public function testThatReaderFailsConstructionIfProvidedDiscriminatorIsNoStreamResource()
    {
        $this->setExpectedException(
            'MetaSyntactical\\Io\\Exception\\InvalidResourceTypeException',
            'Invalid resource type (only resources of type stream are supported)'
        );
        new Reader('dummyString');
    }

    /**
     * @covers MetaSyntactical\Io\Reader::available
     */
    public function testThatAvailableReturnsTrueIfDataAvailable()
    {
        self::assertTrue($this->object->available());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::available
     */
    public function testThatAvailableReturnsFalseIfNoMoreDataAvailable()
    {
        fseek($this->fp, 0, SEEK_END);
        self::assertFalse($this->object->available());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::available
     */
    public function testThatAvailableThrowsExpectedExceptionIfStreamHasBeenClosedFromOutside()
    {
        fclose($this->fp);
        $this->setExpectedException(
            'MetaSyntactical\\Io\\Exception\\InvalidStreamException',
            'Cannot operate on a closed stream'
        );
        $this->object->available();
    }

    /**
     * @covers MetaSyntactical\Io\Reader::available
     * @covers MetaSyntactical\Io\Reader::checkStreamAvailable
     */
    public function testThatAvailableThrowsExpectedExceptionIfStreamHasBeenClosedByInterfaceMethod()
    {
        $this->object->close();
        $this->setExpectedException(
            'MetaSyntactical\\Io\\Exception\\InvalidStreamException',
            'Cannot operate on a closed stream'
        );
        $this->object->available();
    }

    public function provideOffsetData()
    {
        return array(
            array(0),
            array(1),
            array(2),
            array(10),
            array(99),
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::getOffset
     * @dataProvider provideOffsetData
     * @param integer $offset
     */
    public function testGetOffsetReturnsExpectedValue($offset)
    {
        fseek($this->fp, $offset);
        self::assertEquals($offset, $this->object->getOffset());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::setOffset
     * @dataProvider provideOffsetData
     * @param integer $offset
     */
    public function testSetOffsetSetsCorrectOffsetValue($offset)
    {
        $this->object->setOffset($offset);
        self::assertEquals($offset, ftell($this->fp));
    }

    /**
     * @covers MetaSyntactical\Io\Reader::setOffset
     */
    public function testSetOffsetDoesSetOffsetExceedingSize()
    {
        $this->object->setOffset(129);
        self::assertFalse(ftell($this->fp));
    }

    /**
     * @covers MetaSyntactical\Io\Reader::getSize
     */
    public function testThatGetSizeReturnsTheCorrectStreamSize()
    {
        self::assertEquals(128, $this->object->getSize());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::getFileDescriptor
     */
    public function testThatGetFileDescriptorReturnsTheOriginalFileDescriptor()
    {
        self::assertEquals($this->fp, $this->object->getFileDescriptor());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::skip
     */
    public function testThatSkipWillSkipGivenNumberOfBytes()
    {
        $this->object->skip(10);
        self::assertEquals(10, ftell($this->fp));
        $this->object->skip(7);
        self::assertEquals(17, ftell($this->fp));
    }

    /**
     * @covers MetaSyntactical\Io\Reader::skip
     */
    public function testThatSkippingZeroNumberOfBytesDoesNotChangeTheFilePointer()
    {
        $this->object->skip(0);
        self::assertEquals(0, ftell($this->fp));
        $this->object->setOffset(10);
        self::assertEquals(10, ftell($this->fp));
        $this->object->skip(0);
        self::assertEquals(10, ftell($this->fp));
    }

    /**
     * @covers MetaSyntactical\Io\Reader::skip
     */
    public function testThatSkipThrowsExpectedExceptionIfTryingToSkipNegativeValue()
    {
        fseek($this->fp, 0, SEEK_END);
        $this->setExpectedException(
            'MetaSyntactical\\Io\\Exception\\DomainAssertion',
            'Size cannot be negative'
        );
        $this->object->skip(-10);
    }

    /**
     * @covers MetaSyntactical\Io\Reader::read
     */
    public function testThatReadReturnsTheCorrectValueBeforeChangingPosition()
    {
        self::assertEquals('0123456789', $this->object->read(10));
    }

    /**
     * @covers MetaSyntactical\Io\Reader::read
     */
    public function testThatReadReturnsTheCorrectValueAfterChangingPosition()
    {
        $this->object->setOffset(4);
        self::assertEquals('4567890123', $this->object->read(10));
    }

    /**
     * @covers MetaSyntactical\Io\Reader::read
     */
    public function testThatReadReturnsOnlyBytesLeft()
    {
        $this->object->setOffset(120);
        self::assertEquals('01234567', $this->object->read(10));
    }

    /**
     * @covers MetaSyntactical\Io\Reader::read
     */
    public function testThatReadReturnsNothingAndFilePointerHasNotChangedIfNothingShouldBeRead()
    {
        self::assertEquals('', $this->object->read(0));
        self::assertEquals(0, ftell($this->fp));
    }

    /**
     * @covers MetaSyntactical\Io\Reader::read
     */
    public function testThatReadThrowsExpectedExceptionIfTryingToReadNegativeLength()
    {
        $this->setExpectedException(
            'MetaSyntactical\\Io\\Exception\\DomainAssertion',
            'Length cannot be negative'
        );
        $this->object->read(-1);
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt8
     */
    public function testReadInt8()
    {
        self::assertEquals(48, $this->object->readInt8());
        self::assertEquals(49, $this->object->readInt8());
        $this->object->skip(2);
        self::assertEquals(52, $this->object->readInt8());

        self::assertEquals(0, $this->advancedObject->readInt8());
        $this->advancedObject->setOffset(135);
        self::assertEquals(-121, $this->advancedObject->readInt8());
        self::assertEquals(-120, $this->advancedObject->readInt8());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt8
     */
    public function testReadUInt8()
    {
        self::assertEquals(48, $this->object->readUInt8());
        self::assertEquals(49, $this->object->readUInt8());
        $this->object->skip(2);
        self::assertEquals(52, $this->object->readInt8());

        self::assertEquals(0, $this->advancedObject->readUInt8());
        $this->advancedObject->setOffset(135);
        self::assertEquals(135, $this->advancedObject->readUInt8());
        self::assertEquals(136, $this->advancedObject->readUInt8());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt16LE
     * @covers MetaSyntactical\Io\Reader::isBigEndian
     * @covers MetaSyntactical\Io\Reader::getEndianess
     * @covers MetaSyntactical\Io\Reader::fromInt16
     */
    public function testOnALittleEndianMachineReadInt16LE()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(12592, $this->object->readInt16LE());
        $this->advancedObject->skip(128);
        self::assertEquals(-32384, $this->advancedObject->readInt16LE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt16LE
     * @covers MetaSyntactical\Io\Reader::isBigEndian
     * @covers MetaSyntactical\Io\Reader::getEndianess
     * @covers MetaSyntactical\Io\Reader::fromInt16
     */
    public function testOnABigEndianMachineReadInt16LE()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(12337, $this->object->readInt16LE());
        $this->advancedObject->skip(128);
        self::assertEquals(-32639, $this->advancedObject->readInt16LE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt16BE
     * @covers MetaSyntactical\Io\Reader::isLittleEndian
     * @covers MetaSyntactical\Io\Reader::getEndianess
     * @covers MetaSyntactical\Io\Reader::fromInt16
     */
    public function testOnALittleEndianMachineReadInt16BE()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(12337, $this->object->readInt16BE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt16BE
     * @covers MetaSyntactical\Io\Reader::isLittleEndian
     * @covers MetaSyntactical\Io\Reader::getEndianess
     * @covers MetaSyntactical\Io\Reader::fromInt16
     */
    public function testOnABigEndianMachineReadInt16BE()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(12592, $this->object->readInt16BE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt16BE
     * @covers MetaSyntactical\Io\Reader::isLittleEndian
     * @covers MetaSyntactical\Io\Reader::getEndianess
     * @covers MetaSyntactical\Io\Reader::fromInt16
     */
    public function testOnAnAutodetectedEndianMachineReadInt16BE()
    {
        self::assertEquals(12337, $this->object->readInt16BE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt16
     * @covers MetaSyntactical\Io\Reader::fromInt16
     */
    public function testReadInt16()
    {
        self::assertEquals(12592, $this->object->readInt16());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt16LE
     * @covers MetaSyntactical\Io\Reader::fromUInt16
     */
    public function testReadUInt16LE()
    {
        self::assertEquals(12592, $this->object->readUInt16LE());
        $this->advancedObject->skip(128);
        self::assertEquals(33152, $this->advancedObject->readUInt16LE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt16BE
     * @covers MetaSyntactical\Io\Reader::fromUInt16
     */
    public function testReadUInt16BE()
    {
        self::assertEquals(12337, $this->object->readUInt16BE());
        $this->advancedObject->skip(128);
        self::assertEquals(32897, $this->advancedObject->readUInt16BE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt16
     * @covers MetaSyntactical\Io\Reader::fromUInt16
     */
    public function testReadUInt16()
    {
        self::assertEquals(12592, $this->object->readUInt16());
        $this->advancedObject->skip(128);
        self::assertEquals(33152, $this->advancedObject->readUInt16());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt24LE
     * @covers MetaSyntactical\Io\Reader::isBigEndian
     * @covers MetaSyntactical\Io\Reader::fromInt24
     */
    public function testOnALittleEndianMachineReadInt24LE()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(842084352, $this->object->readInt24LE());
        $this->advancedObject->skip(128);
        self::assertEquals(-2105442304, $this->advancedObject->readInt24LE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt24LE
     * @covers MetaSyntactical\Io\Reader::isBigEndian
     * @covers MetaSyntactical\Io\Reader::fromInt24
     */
    public function testOnABigEndianMachineReadInt24LE()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(3158322, $this->object->readInt24LE());
        $this->advancedObject->skip(128);
        self::assertEquals(8421762, $this->advancedObject->readInt24LE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt24BE
     * @covers MetaSyntactical\Io\Reader::isLittleEndian
     * @covers MetaSyntactical\Io\Reader::fromInt24
     */
    public function testOnALittleEndianMachineReadInt24BE()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(808530432, $this->object->readInt24BE());
        $this->advancedObject->skip(128);
        self::assertEquals(-2138996224, $this->advancedObject->readInt24BE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt24BE
     * @covers MetaSyntactical\Io\Reader::isLittleEndian
     * @covers MetaSyntactical\Io\Reader::fromInt24
     */
    public function testOnABigEndianMachineReadInt24BE()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(3289392, $this->object->readInt24BE());
        $this->advancedObject->skip(128);
        self::assertEquals(8552832, $this->advancedObject->readInt24BE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt24BE
     * @todo   Implement testReadInt24BE().
     */
    public function testReadInt24BE()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt24
     * @todo   Implement testReadInt24().
     */
    public function testReadInt24()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt24LE
     * @todo   Implement testReadUInt24LE().
     */
    public function testReadUInt24LE()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt24BE
     * @todo   Implement testReadUInt24BE().
     */
    public function testReadUInt24BE()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt24
     * @todo   Implement testReadUInt24().
     */
    public function testReadUInt24()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt32LE
     * @todo   Implement testReadInt32LE().
     */
    public function testReadInt32LE()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt32BE
     * @todo   Implement testReadInt32BE().
     */
    public function testReadInt32BE()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt32
     * @todo   Implement testReadInt32().
     */
    public function testReadInt32()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt32LE
     * @todo   Implement testReadUInt32LE().
     */
    public function testReadUInt32LE()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt32BE
     * @todo   Implement testReadUInt32BE().
     */
    public function testReadUInt32BE()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt32
     * @todo   Implement testReadUInt32().
     */
    public function testReadUInt32()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt64LE
     * @todo   Implement testReadInt64LE().
     */
    public function testReadInt64LE()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt64BE
     * @todo   Implement testReadInt64BE().
     */
    public function testReadInt64BE()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readFloatLE
     * @todo   Implement testReadFloatLE().
     */
    public function testReadFloatLE()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readFloatBE
     * @todo   Implement testReadFloatBE().
     */
    public function testReadFloatBE()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readDoubleLE
     * @todo   Implement testReadDoubleLE().
     */
    public function testReadDoubleLE()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readDoubleBE
     * @todo   Implement testReadDoubleBE().
     */
    public function testReadDoubleBE()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readString8
     * @todo   Implement testReadString8().
     */
    public function testReadString8()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readString16
     * @covers MetaSyntactical\Io\Reader::read
     */
    public function testThatReadString16ReturnsEmptyStringIfRequestedLengthIsLower2()
    {
        $order = null;
        self::assertEquals('', $this->object->readString16(1, $order));
        self::assertNull($order);
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readString16
     * @covers MetaSyntactical\Io\Reader::read
     */
    public function testThatReadString16ReturnsExpectedStringIfRequestedLengthIsHighEnough()
    {
        $order = null;
        self::assertEquals('012', $this->object->readString16(3, $order));
        self::assertNull($order);

        $order = null;
        self::assertEquals('äöü', $this->unicodeObject->readString16(6, $order));
        self::assertNull($order);

        $order = null;
        self::assertEquals('äöü', $this->unicodeObjectBomLE->readString16(8, $order, true));
        self::assertEquals(Reader::LITTLE_ENDIAN_ORDER, $order);

        $order = null;
        self::assertEquals('äöü', $this->unicodeObjectBomBE->readString16(8, $order, true));
        self::assertEquals(Reader::BIG_ENDIAN_ORDER, $order);

        $order = null;
        self::assertEquals('abc', $this->advancedObjectWithNullBytes->readString16(
            $this->advancedObjectWithNullBytes->getSize(),
            $order,
            true
        ));
        self::assertNull($order);
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readHHex
     * @todo   Implement testReadHHex().
     */
    public function testReadHHex()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readLHex
     * @todo   Implement testReadLHex().
     */
    public function testReadLHex()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readGuid
     * @todo   Implement testReadGuid().
     */
    public function testReadGuid()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::reset
     */
    public function testReset()
    {
        fseek($this->fp, 0, SEEK_END);
        $this->object->reset();
        self::assertEquals(0, ftell($this->fp));
    }

    /**
     * @covers MetaSyntactical\Io\Reader::close
     */
    public function testThatTheFilePointerIsInvalidAfterClosingFileInReader()
    {
        $this->object->close();
        $this->setExpectedException(
            'PHPUnit_Framework_Error_Warning',
            'is not a valid stream resource'
        );
        self::assertFalse(ftell($this->fp));
    }

    /**
     * @covers MetaSyntactical\Io\Reader::close
     */
    public function testThatCloseDoesNotProduceErrorsOnConsecutiveCalls()
    {
        $this->object->close();
        $this->object->close();
        $this->object->close();
        self::assertTrue(true);
    }

    /**
     * @covers MetaSyntactical\Io\Reader::__get
     * @todo   Implement test__get().
     */
    public function test__get()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @covers MetaSyntactical\Io\Reader::__set
     * @todo   Implement test__set().
     */
    public function test__set()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }
}
