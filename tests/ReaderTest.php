<?php

namespace MetaSyntactical\Io\Tests;

use MetaSyntactical\Io\Reader;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use TypeError;
use MetaSyntactical\Io\Exception\InvalidResourceTypeException;
use MetaSyntactical\Io\Exception\InvalidStreamException;
use MetaSyntactical\Io\Exception\DomainAssertion;
use MetaSyntactical\Io\Exception\OutOfRangeException;
use MetaSyntactical\Io\Exception\InvalidArgumentException;

class ReaderTest extends TestCase
{
    protected Reader $object;

    protected Reader $advancedObject;

    protected Reader $unicodeObject;

    protected Reader $unicodeObjectBomLE;

    protected Reader $unicodeObjectBomBE;

    protected Reader $advancedObjectWithNullBytes;

    /**
     * @var resource
     */
    protected $fp;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
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
    protected function tearDown(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::MACHINE_ENDIAN_ORDER);

        try {
            $this->object->close();
        } catch (TypeError $exception) {
            if ($exception->getMessage() !== 'fclose(): supplied resource is not a valid stream resource') {
                throw $exception;
            }
        }
        unset($this->object);
        try {
            @fclose($this->fp);
        } catch (TypeError $exception) {
            if ($exception->getMessage() !== 'fclose(): supplied resource is not a valid stream resource') {
                throw $exception;
            }
        }
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

    public function testReaderFailsConstructionIfProvidedDiscriminatorIsNoStreamResource(): void
    {
        $this->expectException(
            InvalidResourceTypeException::class,
        );
        $this->expectExceptionMessage(
            'Invalid resource type (only resources of type stream are supported)'
        );

        new Reader('dummyString');
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::available
     */
    public function testAvailableReturnsTrueIfDataAvailable(): void
    {
        self::assertTrue($this->object->available());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::available
     */
    public function testAvailableReturnsFalseIfNoMoreDataAvailable(): void
    {
        fseek($this->fp, 0, SEEK_END);
        self::assertFalse($this->object->available());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::available
     */
    public function testAvailableThrowsExpectedExceptionIfStreamHasBeenClosedFromOutside(): void
    {
        fclose($this->fp);
        $this->expectException(
            InvalidStreamException::class,
        );
        $this->expectExceptionMessage(
            'Cannot operate on a closed stream'
        );

        $this->object->available();
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::available
     * @covers \MetaSyntactical\Io\Reader::checkStreamAvailable
     */
    public function testAvailableThrowsExpectedExceptionIfStreamHasBeenClosedByInterfaceMethod(): void
    {
        $this->object->close();
        $this->expectException(
            InvalidStreamException::class,
        );
        $this->expectExceptionMessage('Cannot operate on a closed stream');

        $this->object->available();
    }

    public function provideOffsetData(): array
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
     * @covers \MetaSyntactical\Io\Reader::getOffset
     * @dataProvider provideOffsetData
     */
    public function testGetOffsetReturnsExpectedValue(int $offset): void
    {
        fseek($this->fp, $offset);
        self::assertEquals($offset, $this->object->getOffset());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::setOffset
     * @dataProvider provideOffsetData
     */
    public function testSetOffsetSetsCorrectOffsetValue(int $offset): void
    {
        $this->object->setOffset($offset);
        self::assertEquals($offset, ftell($this->fp));
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::setOffset
     */
    public function testSetOffsetDoesSetOffsetExceedingSize(): void
    {
        $this->object->setOffset(129);
        self::assertFalse(ftell($this->fp));
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::getSize
     */
    public function testGetSizeReturnsTheCorrectStreamSize(): void
    {
        self::assertEquals(128, $this->object->getSize());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::getFileDescriptor
     */
    public function testGetFileDescriptorReturnsTheOriginalFileDescriptor(): void
    {
        self::assertEquals($this->fp, $this->object->getFileDescriptor());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::skip
     */
    public function testSkipWillSkipGivenNumberOfBytes(): void
    {
        $this->object->skip(10);
        self::assertEquals(10, ftell($this->fp));
        $this->object->skip(7);
        self::assertEquals(17, ftell($this->fp));
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::skip
     */
    public function testSkippingZeroNumberOfBytesDoesNotChangeTheFilePointer(): void
    {
        $this->object->skip(0);
        self::assertEquals(0, ftell($this->fp));
        $this->object->setOffset(10);
        self::assertEquals(10, ftell($this->fp));
        $this->object->skip(0);
        self::assertEquals(10, ftell($this->fp));
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::skip
     */
    public function testSkipThrowsExpectedExceptionIfTryingToSkipNegativeValue(): void
    {
        fseek($this->fp, 0, SEEK_END);
        $this->expectException(DomainAssertion::class);
        $this->expectExceptionMessage('Size cannot be negative');

        $this->object->skip(-10);
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::read
     */
    public function testReadReturnsTheCorrectValueBeforeChangingPosition(): void
    {
        self::assertEquals('0123456789', $this->object->read(10));
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::read
     */
    public function testReadReturnsTheCorrectValueAfterChangingPosition(): void
    {
        $this->object->setOffset(4);
        self::assertEquals('4567890123', $this->object->read(10));
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::read
     */
    public function testReadReturnsOnlyBytesLeft(): void
    {
        $this->object->setOffset(120);
        self::assertEquals('01234567', $this->object->read(10));
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::read
     */
    public function testReadReturnsNothingAndFilePointerHasNotChangedIfNothingShouldBeRead(): void
    {
        self::assertEquals('', $this->object->read(0));
        self::assertEquals(0, ftell($this->fp));
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::read
     */
    public function testReadThrowsExpectedExceptionIfTryingToReadNegativeLength(): void
    {
        $this->expectException(
            DomainAssertion::class,
        );
        $this->expectExceptionMessage('Length cannot be negative');

        $this->object->read(-1);
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readInt8
     */
    public function testReadingInt8ReturnsExpectedValues(): void
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
     * @covers \MetaSyntactical\Io\Reader::readUInt8
     */
    public function testReadingUnsignedInt8ReturnsExpectedValues(): void
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
     * @covers \MetaSyntactical\Io\Reader::readInt16LE
     * @covers \MetaSyntactical\Io\Reader::isBigEndian
     * @covers \MetaSyntactical\Io\Reader::getEndianess
     * @covers \MetaSyntactical\Io\Reader::fromInt16
     */
    public function testOnLittleEndianMachineReadingInt16AsLittleEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(12592, $this->object->readInt16LE());
        $this->advancedObject->skip(64);
        self::assertEquals(16704, $this->advancedObject->readInt16LE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readInt16LE
     * @covers \MetaSyntactical\Io\Reader::isBigEndian
     * @covers \MetaSyntactical\Io\Reader::getEndianess
     * @covers \MetaSyntactical\Io\Reader::fromInt16
     */
    public function testOnBigEndianMachineReadingInt16AsLittleEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(12337, $this->object->readInt16LE());
        $this->advancedObject->skip(64);
        self::assertEquals(16449, $this->advancedObject->readInt16LE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readInt16BE
     * @covers \MetaSyntactical\Io\Reader::isLittleEndian
     * @covers \MetaSyntactical\Io\Reader::getEndianess
     * @covers \MetaSyntactical\Io\Reader::fromInt16
     */
    public function testOnLittleEndianMachineReadingInt16AsBigEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(12337, $this->object->readInt16BE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readInt16BE
     * @covers \MetaSyntactical\Io\Reader::isLittleEndian
     * @covers \MetaSyntactical\Io\Reader::getEndianess
     * @covers \MetaSyntactical\Io\Reader::fromInt16
     */
    public function testOnBigEndianMachineReadingInt16AsBigEndianReturnsExpectedValues(): void
    {
        $endianess = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianess->setAccessible(true);
        $endianess->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(12592, $this->object->readInt16BE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readInt16BE
     * @covers \MetaSyntactical\Io\Reader::isLittleEndian
     * @covers \MetaSyntactical\Io\Reader::getEndianess
     * @covers \MetaSyntactical\Io\Reader::fromInt16
     */
    public function testAutodetectedEndianMachineReadingInt16AsBigEndianReturnsExpectedValues(): void
    {
        self::assertEquals(12337, $this->object->readInt16BE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readInt16
     * @covers \MetaSyntactical\Io\Reader::fromInt16
     */
    public function testReadingInt16ReturnsExpectedValues(): void
    {
        self::assertEquals(12592, $this->object->readInt16());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readUInt16LE
     * @covers \MetaSyntactical\Io\Reader::fromUInt16
     */
    public function testReadingUnsignedInt16AsLittleEndianReturnsExpectedValues(): void
    {
        self::assertEquals(12592, $this->object->readUInt16LE());
        $this->advancedObject->skip(64);
        self::assertEquals(16704, $this->advancedObject->readUInt16LE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readUInt16BE
     * @covers \MetaSyntactical\Io\Reader::fromUInt16
     */
    public function testReadingUnsignedInt16AsBigEndianReturnsExpectedValues(): void
    {
        self::assertEquals(12337, $this->object->readUInt16BE());
        $this->advancedObject->skip(64);
        self::assertEquals(16449, $this->advancedObject->readUInt16BE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readUInt16
     * @covers \MetaSyntactical\Io\Reader::fromUInt16
     */
    public function testReadingUnsignedInt16ReturnsExpectedValues(): void
    {
        self::assertEquals(12592, $this->object->readUInt16());
        $this->advancedObject->skip(64);
        self::assertEquals(16704, $this->advancedObject->readUInt16());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readInt24LE
     * @covers \MetaSyntactical\Io\Reader::isBigEndian
     * @covers \MetaSyntactical\Io\Reader::fromInt24
     */
    public function testOnLittleEndianMachineReadingInt24AsLittleEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(842084352, $this->object->readInt24LE());
        $this->advancedObject->skip(64);
        self::assertEquals(1111572480, $this->advancedObject->readInt24LE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readInt24LE
     * @covers \MetaSyntactical\Io\Reader::isBigEndian
     * @covers \MetaSyntactical\Io\Reader::fromInt24
     */
    public function testOnBigEndianMachineReadingInt24AsLittleEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(3158322, $this->object->readInt24LE());
        $this->advancedObject->skip(64);
        self::assertEquals(4211010, $this->advancedObject->readInt24LE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readInt24BE
     * @covers \MetaSyntactical\Io\Reader::isLittleEndian
     * @covers \MetaSyntactical\Io\Reader::fromInt24
     */
    public function testOnLittleEndianMachineReadingInt24AsBigEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(808530432, $this->object->readInt24BE());
        $this->advancedObject->skip(64);
        self::assertEquals(1078018560, $this->advancedObject->readInt24BE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readInt24BE
     * @covers \MetaSyntactical\Io\Reader::isLittleEndian
     * @covers \MetaSyntactical\Io\Reader::fromInt24
     */
    public function testOnBigEndianMachineReadingInt24AsBigEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(3289392, $this->object->readInt24BE());
        $this->advancedObject->skip(64);
        self::assertEquals(4342080, $this->advancedObject->readInt24BE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readInt24
     * @covers \MetaSyntactical\Io\Reader::fromInt24
     */
    public function testReadingInt24ReturnsExpectedValues(): void
    {
        self::assertEquals(842084352, $this->object->readInt24());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readUInt24LE
     * @covers \MetaSyntactical\Io\Reader::fromUInt24
     */
    public function testReadingUnsignedInt24AsLittleEndianReturnsExpectedValues(): void
    {
        self::assertEquals(842084352, $this->object->readUInt24LE());
        $this->advancedObject->skip(64);
        self::assertEquals(1111572480, $this->advancedObject->readUInt24LE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readUInt24BE
     * @covers \MetaSyntactical\Io\Reader::fromUInt24
     */
    public function testReadingUnsignedInt24AsBigEndianReturnsExpectedValues(): void
    {
        self::assertEquals(3158322, $this->object->readUInt24BE());
        $this->advancedObject->skip(64);
        self::assertEquals(4211010, $this->advancedObject->readUInt24BE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readUInt24
     * @covers \MetaSyntactical\Io\Reader::fromUInt24
     */
    public function testReadingUnsignedInt24ReturnsExpectedValues(): void
    {
        self::assertEquals(842084352, $this->object->readUInt24());
        $this->advancedObject->skip(64);
        self::assertEquals(1111572480, $this->advancedObject->readUInt24());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readInt32LE
     * @covers \MetaSyntactical\Io\Reader::isBigEndian
     * @covers \MetaSyntactical\Io\Reader::fromInt32
     */
    public function testOnLittleEndianMachineReadingInt32AsLittleEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(858927408, $this->object->readInt32LE());
        $this->advancedObject->skip(64);
        self::assertEquals(1128415552, $this->advancedObject->readInt32LE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readInt32LE
     * @covers \MetaSyntactical\Io\Reader::isBigEndian
     * @covers \MetaSyntactical\Io\Reader::fromInt24
     */
    public function testOnBigEndianMachineReadingInt32AsLittleEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(808530483, $this->object->readInt32LE());
        $this->advancedObject->skip(64);
        self::assertEquals(1078018627, $this->advancedObject->readInt32LE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readInt32BE
     * @covers \MetaSyntactical\Io\Reader::isLittleEndian
     * @covers \MetaSyntactical\Io\Reader::fromInt32
     */
    public function testOnLittleEndianMachineReadingInt32AsBigEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(808530483, $this->object->readInt32BE());
        $this->advancedObject->skip(64);
        self::assertEquals(1078018627, $this->advancedObject->readInt32BE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readInt32BE
     * @covers \MetaSyntactical\Io\Reader::isLittleEndian
     * @covers \MetaSyntactical\Io\Reader::fromInt32
     */
    public function testOnBigEndianMachineReadingInt32AsBigEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(858927408, $this->object->readInt32BE());
        $this->advancedObject->skip(64);
        self::assertEquals(1128415552, $this->advancedObject->readInt32BE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readInt32
     * @covers \MetaSyntactical\Io\Reader::fromInt32
     */
    public function testReadingInt32ReturnsExpectedValues(): void
    {
        self::assertEquals(858927408, $this->object->readInt32());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readUInt32LE
     */
    public function testReadingUnsignedInt32AsLittleEndianReturnsExpectedValues(): void
    {
        self::assertEquals(858927408, $this->object->readUInt32LE());
        $this->advancedObject->skip(64);
        self::assertEquals(1128415552, $this->advancedObject->readUInt32LE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readUInt32BE
     */
    public function testReadingUnsignedInt32AsBigEndianReturnsExpectedValues(): void
    {
        self::assertEquals(808530483, $this->object->readUInt32BE());
        $this->advancedObject->skip(64);
        self::assertEquals(1078018627, $this->advancedObject->readUInt32BE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readUInt32
     */
    public function testReadingUnsignedInt32ReturnsExpectedValues(): void
    {
        self::assertEquals(858927408, $this->object->readUInt32());
        $this->advancedObject->skip(64);
        self::assertEquals(1128415552, $this->advancedObject->readUInt32());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readInt64LE
     */
    public function testReadingInt64AsLittleEndianReturnsExpectedValues(): void
    {
        self::assertEquals(3978425819141910832, $this->object->readInt64LE());
        $this->advancedObject->skip(64);
        self::assertEquals(3833745473465760056, $this->object->readInt64LE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readInt64BE
     */
    public function testReadingInt64AsBigEndianReturnsExpectedValues(): void
    {
        self::assertEquals(3472611983179986487, $this->object->readInt64BE());
        $this->advancedObject->skip(64);
        self::assertEquals(4051322327650219061, $this->object->readInt64BE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readFloatLE
     * @covers \MetaSyntactical\Io\Reader::isBigEndian
     * @covers \MetaSyntactical\Io\Reader::fromFloat
     */
    public function testOnLittleEndianMachineReadingFloatAsLittleEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(4.1488590341032E-8, $this->object->readFloatLE());
        $this->advancedObject->skip(64);
        self::assertEquals(194.2548828125, $this->advancedObject->readFloatLE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readFloatLE
     * @covers \MetaSyntactical\Io\Reader::isBigEndian
     * @covers \MetaSyntactical\Io\Reader::fromFloat
     */
    public function testOnBigEndianMachineReadingFloatAsLittleEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(6.4463562265971E-10, $this->object->readFloatLE());
        $this->advancedObject->skip(64);
        self::assertEquals(3.0196692943573, $this->advancedObject->readFloatLE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readFloatBE
     * @covers \MetaSyntactical\Io\Reader::isLittleEndian
     * @covers \MetaSyntactical\Io\Reader::fromFloat
     */
    public function testOnLittleEndianMachineReadingFloatAsBigEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(6.4463562265971E-10, $this->object->readFloatBE());
        $this->advancedObject->skip(64);
        self::assertEquals(3.0196692943573, $this->advancedObject->readFloatBE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readFloatBE
     * @covers \MetaSyntactical\Io\Reader::isLittleEndian
     * @covers \MetaSyntactical\Io\Reader::fromFloat
     */
    public function testOnBigEndianMachineReadingFloatAsBigEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(4.1488590341032E-8, $this->object->readFloatBE());
        $this->advancedObject->skip(64);
        self::assertEquals(194.2548828125, $this->advancedObject->readFloatBE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readDoubleLE
     * @covers \MetaSyntactical\Io\Reader::isBigEndian
     * @covers \MetaSyntactical\Io\Reader::fromDouble
     */
    public function testOnLittleEndianMachineReadingDoubleAsLittleEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(9.9583343788967E-43, (string)$this->object->readDoubleLE());
        $this->advancedObject->skip(64);
        self::assertEquals(2.3127085096212E+35, (string)$this->advancedObject->readDoubleLE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readDoubleLE
     * @covers \MetaSyntactical\Io\Reader::isBigEndian
     * @covers \MetaSyntactical\Io\Reader::fromDouble
     */
    public function testOnBigEndianMachineReadingDoubleAsLittleEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(1.4850836463301E-76, (string)$this->object->readDoubleLE());
        $this->advancedObject->skip(64);
        self::assertEquals(34.517677816225, (string)$this->advancedObject->readDoubleLE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readDoubleBE
     * @covers \MetaSyntactical\Io\Reader::isLittleEndian
     * @covers \MetaSyntactical\Io\Reader::fromDouble
     */
    public function testOnLittleEndianMachineReadingDoubleAsBigEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::LITTLE_ENDIAN_ORDER);
        self::assertEquals(9.9583343788967E-43, (string)$this->object->readDoubleBE());
        $this->advancedObject->skip(64);
        self::assertEquals(34.517677816225, (string)$this->advancedObject->readDoubleBE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readDoubleBE
     * @covers \MetaSyntactical\Io\Reader::isLittleEndian
     * @covers \MetaSyntactical\Io\Reader::fromDouble
     */
    public function testOnBigEndianMachineReadingDoubleAsBigEndianReturnsExpectedValues(): void
    {
        $endianness = (new ReflectionObject($this->object))->getProperty('endianessValue');
        $endianness->setAccessible(true);
        $endianness->setValue(Reader::BIG_ENDIAN_ORDER);
        self::assertEquals(1.4850836463301E-76, (string)$this->object->readDoubleBE());
        $this->advancedObject->skip(64);
        self::assertEquals(2.3127085096212E+35, (string)$this->advancedObject->readDoubleBE());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readString8
     * @covers \MetaSyntactical\Io\Reader::read
     */
    public function testReadingString8ReturnsExpectedValues(): void
    {
        self::assertEquals('012', $this->object->readString8(3));

        self::assertEquals('äöü', $this->unicodeObject->readString8(6));

        self::assertEquals(
            'abc',
            $this->advancedObjectWithNullBytes->readString8($this->advancedObjectWithNullBytes->getSize())
        );
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readString16
     * @covers \MetaSyntactical\Io\Reader::read
     */
    public function testReadString16ReturnsEmptyStringIfRequestedLengthIsLower2(): void
    {
        $order = null;
        self::assertEquals('', $this->object->readString16(1, $order));
        self::assertNull($order);
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readString16
     * @covers \MetaSyntactical\Io\Reader::read
     */
    public function testReadString16ReturnsExpectedStringIfRequestedLengthIsHighEnough(): void
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
        self::assertEquals(
            'abc',
            $this->advancedObjectWithNullBytes->readString16(
                $this->advancedObjectWithNullBytes->getSize(),
                $order,
                true
            )
        );
        self::assertNull($order);
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readHHex
     */
    public function testReadingHighNibbleFirstHexadecimalValueReturnsExpectedValues(): void
    {
        self::assertEquals('30', $this->object->readHHex(1)); // reads "a"
        self::assertEquals('31', $this->object->readHHex(1)); // reads "b"
        self::assertEquals('32', $this->object->readHHex(1)); // reads "c"
        self::assertEquals('333435', $this->object->readHHex(3)); // reads "def"
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readLHex
     */
    public function testReadingLowNibbleFirstHexadecimalValueReturnsExpectedValues(): void
    {
        self::assertEquals('03', $this->object->readLHex(1)); // reads "a" nibble reversed
        self::assertEquals('13', $this->object->readLHex(1)); // reads "b" nibble reversed
        self::assertEquals('23', $this->object->readLHex(1)); // reads "c" nibble reversed
        self::assertEquals('334353', $this->object->readLHex(3)); // reads "def" nibbles reversed
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::readGuid
     */
    public function testReadingGuidReturnsExpectedValues(): void
    {
        self::assertEquals('33323130-3534-3736-3839-303132333435', $this->object->readGuid());
        $this->advancedObject->skip(64);
        self::assertEquals('43424140-4544-4746-4849-4a4b4c4d4e4f', $this->advancedObject->readGuid());
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::reset
     */
    public function testReset(): void
    {
        fseek($this->fp, 0, SEEK_END);
        $this->object->reset();
        self::assertEquals(0, ftell($this->fp));
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::close
     */
    public function testTheFilePointerIsInvalidAfterClosingFileInReader(): void
    {
        $this->object->close();
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('is not a valid stream resource');

        self::assertFalse(ftell($this->fp));
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::close
     */
    public function testCloseDoesNotProduceErrorsOnConsecutiveCalls(): void
    {
        $this->object->close();
        $this->object->close();
        $this->object->close();
        self::assertTrue(true);
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::__get
     */
    public function testGettingEndianessByMagicGetterReturnsValue(): void
    {
        self::assertIsInt($this->object->endianess);
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::__get
     */
    public function testGettingUnknownFieldByMagicGetterThrowsExpectedOutOfRangeException(): void
    {
        $this->expectException(OutOfRangeException::class,);
        $this->expectExceptionMessage('Unknown field');

        $this->object->unknownfield;
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::__set
     */
    public function testSettingOffsetByMagicGetterDoesNotThrowException(): void
    {
        $this->object->offset = 0;
        self::assertEquals(0, $this->object->offset);
    }

    /**
     * @covers \MetaSyntactical\Io\Reader::__set
     */
    public function testSettingUnknownFieldByMagicGetterThrowsExpectedOutOfRangeException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown field');

        $this->object->unknownfield = false;
    }

    public function testCheckingUnknownFieldIsset(): void
    {
        self::assertFalse(isset($this->object->unknownfield));
    }

    public function testCheckingKnownFieldIsset(): void
    {
        self::assertTrue(isset($this->object->endianess));
    }
}
