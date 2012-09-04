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

    public function testReaderFailsConstructionIfProvidedDiscriminatorIsNoStreamResource()
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
    public function testAvailableReturnsTrueIfDataAvailable()
    {
        self::assertTrue($this->object->available());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::available
     */
    public function testAvailableReturnsFalseIfNoMoreDataAvailable()
    {
        fseek($this->fp, 0, SEEK_END);
        self::assertFalse($this->object->available());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::available
     */
    public function testAvailableThrowsExpectedExceptionIfStreamHasBeenClosedFromOutside()
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
    public function testAvailableThrowsExpectedExceptionIfStreamHasBeenClosedByInterfaceMethod()
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
    public function testGetSizeReturnsTheCorrectStreamSize()
    {
        self::assertEquals(128, $this->object->getSize());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::getFileDescriptor
     */
    public function testGetFileDescriptorReturnsTheOriginalFileDescriptor()
    {
        self::assertEquals($this->fp, $this->object->getFileDescriptor());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::skip
     */
    public function testSkipWillSkipGivenNumberOfBytes()
    {
        $this->object->skip(10);
        self::assertEquals(10, ftell($this->fp));
        $this->object->skip(7);
        self::assertEquals(17, ftell($this->fp));
    }

    /**
     * @covers MetaSyntactical\Io\Reader::skip
     */
    public function testSkippingZeroNumberOfBytesDoesNotChangeTheFilePointer()
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
    public function testSkipThrowsExpectedExceptionIfTryingToSkipNegativeValue()
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
    public function testReadReturnsTheCorrectValueBeforeChangingPosition()
    {
        self::assertEquals('0123456789', $this->object->read(10));
    }

    /**
     * @covers MetaSyntactical\Io\Reader::read
     */
    public function testReadReturnsTheCorrectValueAfterChangingPosition()
    {
        $this->object->setOffset(4);
        self::assertEquals('4567890123', $this->object->read(10));
    }

    /**
     * @covers MetaSyntactical\Io\Reader::read
     */
    public function testReadReturnsOnlyBytesLeft()
    {
        $this->object->setOffset(120);
        self::assertEquals('01234567', $this->object->read(10));
    }

    /**
     * @covers MetaSyntactical\Io\Reader::read
     */
    public function testReadReturnsNothingAndFilePointerHasNotChangedIfNothingShouldBeRead()
    {
        self::assertEquals('', $this->object->read(0));
        self::assertEquals(0, ftell($this->fp));
    }

    /**
     * @covers MetaSyntactical\Io\Reader::read
     */
    public function testReadThrowsExpectedExceptionIfTryingToReadNegativeLength()
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
    public function testReadingInt8ReturnsExpectedValues()
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
    public function testReadingUnsignedInt8ReturnsExpectedValues()
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
    public function testOnLittleEndianMachineReadingInt16AsLittleEndianReturnsExpectedValues()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(12592, $this->object->readInt16LE());
        $this->advancedObject->skip(64);
        self::assertEquals(16704, $this->advancedObject->readInt16LE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt16LE
     * @covers MetaSyntactical\Io\Reader::isBigEndian
     * @covers MetaSyntactical\Io\Reader::getEndianess
     * @covers MetaSyntactical\Io\Reader::fromInt16
     */
    public function testOnBigEndianMachineReadingInt16AsLittleEndianReturnsExpectedValues()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(12337, $this->object->readInt16LE());
        $this->advancedObject->skip(64);
        self::assertEquals(16449, $this->advancedObject->readInt16LE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt16BE
     * @covers MetaSyntactical\Io\Reader::isLittleEndian
     * @covers MetaSyntactical\Io\Reader::getEndianess
     * @covers MetaSyntactical\Io\Reader::fromInt16
     */
    public function testOnLittleEndianMachineReadingInt16AsBigEndianReturnsExpectedValues()
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
    public function testOnBigEndianMachineReadingInt16AsBigEndianReturnsExpectedValues()
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
    public function testAutodetectedEndianMachineReadingInt16AsBigEndianReturnsExpectedValues()
    {
        self::assertEquals(12337, $this->object->readInt16BE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt16
     * @covers MetaSyntactical\Io\Reader::fromInt16
     */
    public function testReadingInt16ReturnsExpectedValues()
    {
        self::assertEquals(12592, $this->object->readInt16());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt16LE
     * @covers MetaSyntactical\Io\Reader::fromUInt16
     */
    public function testReadingUnsignedInt16AsLittleEndianReturnsExpectedValues()
    {
        self::assertEquals(12592, $this->object->readUInt16LE());
        $this->advancedObject->skip(64);
        self::assertEquals(16704, $this->advancedObject->readUInt16LE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt16BE
     * @covers MetaSyntactical\Io\Reader::fromUInt16
     */
    public function testReadingUnsignedInt16AsBigEndianReturnsExpectedValues()
    {
        self::assertEquals(12337, $this->object->readUInt16BE());
        $this->advancedObject->skip(64);
        self::assertEquals(16449, $this->advancedObject->readUInt16BE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt16
     * @covers MetaSyntactical\Io\Reader::fromUInt16
     */
    public function testReadingUnsignedInt16ReturnsExpectedValues()
    {
        self::assertEquals(12592, $this->object->readUInt16());
        $this->advancedObject->skip(64);
        self::assertEquals(16704, $this->advancedObject->readUInt16());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt24LE
     * @covers MetaSyntactical\Io\Reader::isBigEndian
     * @covers MetaSyntactical\Io\Reader::fromInt24
     */
    public function testOnLittleEndianMachineReadingInt24AsLittleEndianReturnsExpectedValues()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(842084352, $this->object->readInt24LE());
        $this->advancedObject->skip(64);
        self::assertEquals(1111572480, $this->advancedObject->readInt24LE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt24LE
     * @covers MetaSyntactical\Io\Reader::isBigEndian
     * @covers MetaSyntactical\Io\Reader::fromInt24
     */
    public function testOnBigEndianMachineReadingInt24AsLittleEndianReturnsExpectedValues()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(3158322, $this->object->readInt24LE());
        $this->advancedObject->skip(64);
        self::assertEquals(4211010, $this->advancedObject->readInt24LE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt24BE
     * @covers MetaSyntactical\Io\Reader::isLittleEndian
     * @covers MetaSyntactical\Io\Reader::fromInt24
     */
    public function testOnLittleEndianMachineReadingInt24AsBigEndianReturnsExpectedValues()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(808530432, $this->object->readInt24BE());
        $this->advancedObject->skip(64);
        self::assertEquals(1078018560, $this->advancedObject->readInt24BE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt24BE
     * @covers MetaSyntactical\Io\Reader::isLittleEndian
     * @covers MetaSyntactical\Io\Reader::fromInt24
     */
    public function testOnBigEndianMachineReadingInt24AsBigEndianReturnsExpectedValues()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(3289392, $this->object->readInt24BE());
        $this->advancedObject->skip(64);
        self::assertEquals(4342080, $this->advancedObject->readInt24BE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt24
     * @covers MetaSyntactical\Io\Reader::fromInt24
     */
    public function testReadingInt24ReturnsExpectedValues()
    {
        self::assertEquals(842084352, $this->object->readInt24());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt24LE
     * @covers MetaSyntactical\Io\Reader::fromUInt24
     */
    public function testReadingUnsignedInt24AsLittleEndianReturnsExpectedValues()
    {
        self::assertEquals(842084352, $this->object->readUInt24LE());
        $this->advancedObject->skip(64);
        self::assertEquals(1111572480, $this->advancedObject->readUInt24LE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt24BE
     * @covers MetaSyntactical\Io\Reader::fromUInt24
     */
    public function testReadingUnsignedInt24AsBigEndianReturnsExpectedValues()
    {
        self::assertEquals(3158322, $this->object->readUInt24BE());
        $this->advancedObject->skip(64);
        self::assertEquals(4211010, $this->advancedObject->readUInt24BE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt24
     * @covers MetaSyntactical\Io\Reader::fromUInt24
     */
    public function testReadingUnsignedInt24ReturnsExpectedValues()
    {
        self::assertEquals(842084352, $this->object->readUInt24());
        $this->advancedObject->skip(64);
        self::assertEquals(1111572480, $this->advancedObject->readUInt24());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt32LE
     * @covers MetaSyntactical\Io\Reader::isBigEndian
     * @covers MetaSyntactical\Io\Reader::fromInt32
     */
    public function testOnLittleEndianMachineReadingInt32AsLittleEndianReturnsExpectedValues()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(858927408, $this->object->readInt32LE());
        $this->advancedObject->skip(64);
        self::assertEquals(1128415552, $this->advancedObject->readInt32LE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt32LE
     * @covers MetaSyntactical\Io\Reader::isBigEndian
     * @covers MetaSyntactical\Io\Reader::fromInt24
     */
    public function testOnBigEndianMachineReadingInt32AsLittleEndianReturnsExpectedValues()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(808530483, $this->object->readInt32LE());
        $this->advancedObject->skip(64);
        self::assertEquals(1078018627, $this->advancedObject->readInt32LE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt32BE
     * @covers MetaSyntactical\Io\Reader::isLittleEndian
     * @covers MetaSyntactical\Io\Reader::fromInt32
     */
    public function testOnLittleEndianMachineReadingInt32AsBigEndianReturnsExpectedValues()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(808530483, $this->object->readInt32BE());
        $this->advancedObject->skip(64);
        self::assertEquals(1078018627, $this->advancedObject->readInt32BE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt32BE
     * @covers MetaSyntactical\Io\Reader::isLittleEndian
     * @covers MetaSyntactical\Io\Reader::fromInt32
     */
    public function testOnBigEndianMachineReadingInt32AsBigEndianReturnsExpectedValues()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(858927408, $this->object->readInt32BE());
        $this->advancedObject->skip(64);
        self::assertEquals(1128415552, $this->advancedObject->readInt32BE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt32
     * @covers MetaSyntactical\Io\Reader::fromInt32
     */
    public function testReadingInt32ReturnsExpectedValues()
    {
        self::assertEquals(858927408, $this->object->readInt32());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt32LE
     */
    public function testReadingUnsignedInt32AsLittleEndianReturnsExpectedValues()
    {
        self::assertEquals(858927408, $this->object->readUInt32LE());
        $this->advancedObject->skip(64);
        self::assertEquals(1128415552, $this->advancedObject->readUInt32LE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt32BE
     */
    public function testReadingUnsignedInt32AsBigEndianReturnsExpectedValues()
    {
        self::assertEquals(808530483, $this->object->readUInt32BE());
        $this->advancedObject->skip(64);
        self::assertEquals(1078018627, $this->advancedObject->readUInt32BE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readUInt32
     */
    public function testReadingUnsignedInt32ReturnsExpectedValues()
    {
        self::assertEquals(858927408, $this->object->readUInt32());
        $this->advancedObject->skip(64);
        self::assertEquals(1128415552, $this->advancedObject->readUInt32());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt64LE
     */
    public function testReadingInt64AsLittleEndianReturnsExpectedValues()
    {
        self::assertEquals(3978425819141910832, $this->object->readInt64LE());
        $this->advancedObject->skip(64);
        self::assertEquals(3833745473465760056, $this->object->readInt64LE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readInt64BE
     */
    public function testReadingInt64AsBigEndianReturnsExpectedValues()
    {
        self::assertEquals(3472611983179986487, $this->object->readInt64BE());
        $this->advancedObject->skip(64);
        self::assertEquals(4051322327650219061, $this->object->readInt64BE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readFloatLE
     * @covers MetaSyntactical\Io\Reader::fromFloat
     */
    public function testOnLittleEndianMachineReadingFloatAsLittleEndian()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(4.1488590341032E-8, $this->object->readFloatLE());
        $this->advancedObject->skip(64);
        self::assertEquals(194.2548828125, $this->advancedObject->readFloatLE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readFloatLE
     * @covers MetaSyntactical\Io\Reader::fromFloat
     */
    public function testOnBigEndianMachineReadingFloatAsLittleEndian()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(6.4463562265971E-10, $this->object->readFloatLE());
        $this->advancedObject->skip(64);
        self::assertEquals(3.0196692943573, $this->advancedObject->readFloatLE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readFloatBE
     * @covers MetaSyntactical\Io\Reader::fromFloat
     */
    public function testOnLittleEndianMachineReadingFloatAsBigEndian()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(6.4463562265971E-10, $this->object->readFloatBE());
        $this->advancedObject->skip(64);
        self::assertEquals(3.0196692943573, $this->advancedObject->readFloatBE());
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readFloatBE
     * @covers MetaSyntactical\Io\Reader::fromFloat
     */
    public function testOnBigEndianMachineReadingFloatAsBigEndian()
    {
        $reflection = new \ReflectionObject($this->object);
        $endianess = $reflection->getProperty('endianess');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(4.1488590341032E-8, $this->object->readFloatBE());
        $this->advancedObject->skip(64);
        self::assertEquals(194.2548828125, $this->advancedObject->readFloatBE());
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
    public function testReadString16ReturnsEmptyStringIfRequestedLengthIsLower2()
    {
        $order = null;
        self::assertEquals('', $this->object->readString16(1, $order));
        self::assertNull($order);
    }

    /**
     * @covers MetaSyntactical\Io\Reader::readString16
     * @covers MetaSyntactical\Io\Reader::read
     */
    public function testReadString16ReturnsExpectedStringIfRequestedLengthIsHighEnough()
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
    public function testTheFilePointerIsInvalidAfterClosingFileInReader()
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
    public function testCloseDoesNotProduceErrorsOnConsecutiveCalls()
    {
        $this->object->close();
        $this->object->close();
        $this->object->close();
        self::assertTrue(true);
    }

    /**
     * @covers MetaSyntactical\Io\Reader::__get
     */
    public function testGettingEndianessByMagicGetterReturnsValue()
    {
        $this->object->endianess;
    }

    /**
     * @covers MetaSyntactical\Io\Reader::__get
     */
    public function testGettingUnknownFieldByMagicGetterThrowsExpectedOutOfRangeException()
    {
        $this->setExpectedException(
            '\\MetaSyntactical\\Io\\Exception\\OutOfRangeException',
            'Unknown field'
        );
        $this->object->unknownfield;
    }

    /**
     * @covers MetaSyntactical\Io\Reader::__set
     */
    public function testSettingOffsetByMagicGetterDoesNotThrowException()
    {
        $this->object->offset = 0;
    }

    /**
     * @covers MetaSyntactical\Io\Reader::__set
     */
    public function testSettingUnknownFieldByMagicGetterThrowsExpectedOutOfRangeException()
    {
        $this->setExpectedException(
            '\\MetaSyntactical\\Io\\Exception\\InvalidArgumentException',
            'Unknown field'
        );
        $this->object->unknownfield = false;
    }
}
