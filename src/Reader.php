<?php

namespace MetaSyntactical\Io;

use MetaSyntactical\Io\Exception\InvalidResourceTypeException;
use MetaSyntactical\Io\Exception\InvalidStreamException;
use MetaSyntactical\Io\Exception\DomainAssertion;
use MetaSyntactical\Io\Exception\OutOfRangeException;
use MetaSyntactical\Io\Exception\InvalidArgumentException;

/**
 * @property      integer $offset    point of operation in stream
 * @property-read integer $endianess endianess of the current machine
 */
class Reader
{
    public const MACHINE_ENDIAN_ORDER = 0;
    public const LITTLE_ENDIAN_ORDER  = 1;
    public const BIG_ENDIAN_ORDER     = 2;

    /**
     * The endianess of the current machine.
     *
     * @var integer
     */
    private static int $endianessValue = 0;

    /**
     * The resource identifier of the stream.
     *
     * @var resource|null
     * @psalm-var resource|closed-resource|null
     */
    protected $fileDescriptor = null;

    /**
     * Size of the underlying stream.
     *
     * @var integer
     */
    protected int $size = 0;

    /**
     * Constructs the Zend_Io_Reader class with given open file descriptor.
     *
     * @param resource $fd The file descriptor.
     * @psalm-param mixed $fd
     * @throws InvalidResourceTypeException if given file descriptor is not valid
     */
    public function __construct($fd)
    {
        if (PHP_INT_SIZE < 8) {
            // @codeCoverageIgnoreStart
            throw new OutOfRangeException('PHP_INT_SIZE is lower than 8. Not supported.');
        }
        // @codeCoverageIgnoreEnd

        if (!is_resource($fd) || get_resource_type($fd) !== 'stream') {
            throw new InvalidResourceTypeException(
                'Invalid resource type (only resources of type stream are supported)'
            );
        }

        $this->fileDescriptor = $fd;

        $offset = $this->getOffset();
        fseek($this->fileDescriptor, 0, SEEK_END);
        $this->size = ftell($this->fileDescriptor);
        fseek($this->fileDescriptor, $offset);
    }

    /**
     * Checks whether there is more to be read from the stream. Returns
     * <var>true</var> if the end has not yet been reached; <var>false</var>
     * otherwise.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    public function available(): bool
    {
        return $this->getOffset() < $this->getSize();
    }

    /**
     * Checks if a stream is available.
     *
     * @return resource
     * @throws InvalidStreamException if an I/O error occurs
     */
    private function checkStreamAvailable()
    {
        if (is_null($this->fileDescriptor) || !is_resource($this->fileDescriptor)) {
            throw new InvalidStreamException('Cannot operate on a closed stream');
        }

        return $this->fileDescriptor;
    }

    /**
     * Returns the current point of operation.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    public function getOffset(): int
    {
        return ftell($this->checkStreamAvailable());
    }

    /**
     * Sets the point of operation, ie the cursor offset value. The offset may
     * also be set to a negative value when it is interpreted as an offset from
     * the end of the stream instead of the beginning.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    public function setOffset(int $offset): void
    {
        fseek($this->checkStreamAvailable(), $offset < 0 ? $this->getSize() + $offset : $offset);
    }

    /**
     * Returns the stream size in bytes.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Returns the underlying stream file descriptor.
     *
     * @return resource
     */
    public function getFileDescriptor()
    {
        return $this->checkStreamAvailable();
    }

    /**
     * Jumps <var>size</var> amount of bytes in the stream.
     *
     * @throws DomainAssertion if <var>size</var> attribute is negative or if
     * @throws InvalidStreamException if an I/O error occurs
     */
    public function skip(int $size): void
    {
        if ($size < 0) {
            throw new DomainAssertion('Size cannot be negative');
        }

        fseek($this->checkStreamAvailable(), $size, SEEK_CUR);
    }

    /**
     * Reads <var>length</var> amount of bytes from the stream.
     *
     * @throws DomainAssertion if <var>size</var> attribute is negative or if
     * @throws InvalidStreamException if an I/O error occurs
     */
    public function read(int $length): string
    {
        if ($length < 0) {
            throw new DomainAssertion('Length cannot be negative');
        }
        if ($length === 0) {
            return '';
        }

        return fread($this->checkStreamAvailable(), $length);
    }

    /**
     * Reads 1 byte from the stream and returns binary data as an 8-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt8(): int
    {
        $ord = ord($this->read(1));

        if ($ord > 127) {
            return -$ord - 2 * (128 - $ord);
        }

        return $ord;
    }

    /**
     * Reads 1 byte from the stream and returns binary data as an unsigned 8-bit
     * integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt8(): int
    {
        return ord($this->read(1));
    }

    /**
     * Returns machine endian ordered binary data as signed 16-bit integer.
     */
    private function fromInt16(string $value): int
    {
        /** @psalm-var int $int */
        [, $int] = unpack('s*', $value);
        return $int;
    }

    /**
     * Reads 2 bytes from the stream and returns little-endian ordered binary
     * data as signed 16-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt16LE(): int
    {
        if ($this->isBigEndian()) {
            return $this->fromInt16(strrev($this->read(2)));
        }

        return $this->fromInt16($this->read(2));
    }

    /**
     * Reads 2 bytes from the stream and returns big-endian ordered binary data
     * as signed 16-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt16BE(): int
    {
        if ($this->isLittleEndian()) {
            return $this->fromInt16(strrev($this->read(2)));
        }

        return $this->fromInt16($this->read(2));
    }

    /**
     * Reads 2 bytes from the stream and returns machine ordered binary data
     * as signed 16-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt16(): int
    {
        return $this->fromInt16($this->read(2));
    }

    /**
     * Returns machine endian ordered binary data as unsigned 16-bit integer.
     */
    private function fromUInt16(string $value, int $order = self::MACHINE_ENDIAN_ORDER): int
    {
        $format = match ($order) {
            self::MACHINE_ENDIAN_ORDER => 'S*',
            self::BIG_ENDIAN_ORDER => 'n*',
            self::LITTLE_ENDIAN_ORDER => 'v*',
        };

        /** @psalm-var int $int */
        [, $int] = unpack(
            $format,
            $value
        );

        return $int;
    }

    /**
     * Reads 2 bytes from the stream and returns little-endian ordered binary
     * data as unsigned 16-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt16LE(): int
    {
        return $this->fromUInt16($this->read(2), self::LITTLE_ENDIAN_ORDER);
    }

    /**
     * Reads 2 bytes from the stream and returns big-endian ordered binary data
     * as unsigned 16-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt16BE(): int
    {
        return $this->fromUInt16($this->read(2), self::BIG_ENDIAN_ORDER);
    }

    /**
     * Reads 2 bytes from the stream and returns machine ordered binary data
     * as unsigned 16-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt16(): int
    {
        return $this->fromUInt16($this->read(2));
    }

    /**
     * Returns machine endian ordered binary data as signed 24-bit integer.
     */
    private function fromInt24(string $value): int
    {
        /** @psalm-var int $int */
        [, $int] = unpack('l*', $this->isLittleEndian() ? ("\x00" . $value) : ($value . "\x00"));
        return $int;
    }

    /**
     * Reads 3 bytes from the stream and returns little-endian ordered binary
     * data as signed 24-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt24LE(): int
    {
        if ($this->isBigEndian()) {
            return $this->fromInt24(strrev($this->read(3)));
        }

        return $this->fromInt24($this->read(3));
    }

    /**
     * Reads 3 bytes from the stream and returns big-endian ordered binary data
     * as signed 24-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt24BE(): int
    {
        if ($this->isLittleEndian()) {
            return $this->fromInt24(strrev($this->read(3)));
        }

        return $this->fromInt24($this->read(3));
    }

    /**
     * Reads 3 bytes from the stream and returns machine ordered binary data
     * as signed 24-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt24(): int
    {
        return $this->fromInt24($this->read(3));
    }

    /**
     * Returns machine endian ordered binary data as unsigned 24-bit integer.
     */
    private function fromUInt24(string $value, int $order = self::MACHINE_ENDIAN_ORDER): int
    {
        $format = match ($order) {
            self::MACHINE_ENDIAN_ORDER => 'L*',
            self::BIG_ENDIAN_ORDER => 'N*',
            self::LITTLE_ENDIAN_ORDER => 'V*',
        };

        /** @psalm-var int $int */
        [, $int] = unpack(
            $format,
            $this->isLittleEndian() ? ("\x00" . $value) : ($value . "\x00")
        );

        return $int;
    }

    /**
     * Reads 3 bytes from the stream and returns little-endian ordered binary
     * data as unsigned 24-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt24LE(): int
    {
        return $this->fromUInt24($this->read(3), self::LITTLE_ENDIAN_ORDER);
    }

    /**
     * Reads 3 bytes from the stream and returns big-endian ordered binary data
     * as unsigned 24-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt24BE(): int
    {
        return $this->fromUInt24($this->read(3), self::BIG_ENDIAN_ORDER);
    }

    /**
     * Reads 3 bytes from the stream and returns machine ordered binary data
     * as unsigned 24-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt24(): int
    {
        return $this->fromUInt24($this->read(3));
    }

    /**
     * Returns machine-endian ordered binary data as signed 32-bit integer.
     */
    private function fromInt32(string $value): int
    {
        /** @psalm-var int $int */
        [, $int] = unpack('l*', $value);
        return $int;
    }

    /**
     * Reads 4 bytes from the stream and returns little-endian ordered binary
     * data as signed 32-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt32LE(): int
    {
        if ($this->isBigEndian()) {
            return $this->fromInt32(strrev($this->read(4)));
        }

        return $this->fromInt32($this->read(4));
    }

    /**
     * Reads 4 bytes from the stream and returns big-endian ordered binary data
     * as signed 32-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt32BE(): int
    {
        if ($this->isLittleEndian()) {
            return $this->fromInt32(strrev($this->read(4)));
        }

        return $this->fromInt32($this->read(4));
    }

    /**
     * Reads 4 bytes from the stream and returns machine ordered binary data
     * as signed 32-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt32(): int
    {
        return $this->fromInt32($this->read(4));
    }

    /**
     * Reads 4 bytes from the stream and returns little-endian ordered binary
     * data as unsigned 32-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt32LE(): int
    {
        /** @psalm-var int $int */
        [, $int] = unpack('V*', $this->read(4));
        return $int;
    }

    /**
     * Reads 4 bytes from the stream and returns big-endian ordered binary data
     * as unsigned 32-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt32BE(): int
    {
        /** @psalm-var int $int */
        [, $int] = unpack('N*', $this->read(4));
        return $int;
    }

    /**
     * Reads 4 bytes from the stream and returns machine ordered binary data
     * as unsigned 32-bit integer.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt32(): int
    {
        /** @psalm-var int $int */
        [, $int] = unpack('L*', $this->read(4)) + array(0, 0);
        return $int;
    }

    /**
     * Reads 8 bytes from the stream and returns little-endian ordered binary
     * data as 64-bit float.
     *
     * {@internal PHP does not support 64-bit integers as the long
     * integer is of 32-bits but using arithmetic operations it is implicitly
     * converted into floating point which is of 64-bits long.}
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt64LE(): float
    {
        /**
         * @psalm-var int $lolo
         * @psalm-var int $lohi
         * @psalm-var int $hilo
         * @psalm-var int $hihi
         */
        [, $lolo, $lohi, $hilo, $hihi] = unpack('v*', $this->read(8));

        return ($hihi * (0xffff+1) + $hilo) * (0xffffffff+1) + ($lohi * (0xffff+1) + $lolo);
    }

    /**
     * Reads 8 bytes from the stream and returns big-endian ordered binary data
     * as 64-bit float.
     *
     * {@internal PHP does not support 64-bit integers as the long integer is of
     * 32-bits but using aritmetic operations it is implicitly converted into
     * floating point which is of 64-bits long.}
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt64BE(): float
    {
        /**
         * @psalm-var int $lolo
         * @psalm-var int $lohi
         * @psalm-var int $hilo
         * @psalm-var int $hihi
         */
        [, $hihi, $hilo, $lohi, $lolo] = unpack('n*', $this->read(8));

        return ($hihi * (0xffff+1) + $hilo) * (0xffffffff+1) + ($lohi * (0xffff+1) + $lolo);
    }

    /**
     * Returns machine endian ordered binary data as a 32-bit floating point
     * number as defined by IEEE 754.
     */
    private function fromFloat(string $value): float
    {
        /** @psalm-var float $float */
        [, $float] = unpack('f', $value);
        return $float;
    }

    /**
     * Reads 4 bytes from the stream and returns little-endian ordered binary
     * data as a 32-bit float point number as defined by IEEE 754.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readFloatLE(): float
    {
        if ($this->isBigEndian()) {
            return $this->fromFloat(strrev($this->read(4)));
        }

        return $this->fromFloat($this->read(4));
    }

    /**
     * Reads 4 bytes from the stream and returns big-endian ordered binary data
     * as a 32-bit float point number as defined by IEEE 754.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readFloatBE(): float
    {
        if ($this->isLittleEndian()) {
            return $this->fromFloat(strrev($this->read(4)));
        }

        return $this->fromFloat($this->read(4));
    }

    /**
     * Returns machine endian ordered binary data as a 64-bit floating point
     * number as defined by IEEE754.
     */
    private function fromDouble(string $value): float
    {
        /** @psalm-var float $double */
        [, $double] = unpack('d', $value);
        return $double;
    }

    /**
     * Reads 8 bytes from the stream and returns little-endian ordered binary
     * data as a 64-bit floating point number as defined by IEEE 754.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readDoubleLE(): float
    {
        if ($this->isBigEndian()) {
            return $this->fromDouble(strrev($this->read(8)));
        }

        return $this->fromDouble($this->read(8));
    }

    /**
     * Reads 8 bytes from the stream and returns big-endian ordered binary data
     * as a 64-bit float point number as defined by IEEE 754.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readDoubleBE(): float
    {
        if ($this->isLittleEndian()) {
            return $this->fromDouble(strrev($this->read(8)));
        }

        return $this->fromDouble($this->read(8));
    }

    /**
     * Reads <var>length</var> amount of bytes from the stream and returns
     * binary data as string. Removes terminating zero.
     *
     * @throws DomainAssertion if <var>size</var> attribute is negative or if
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readString8(int $length, string $charList = "\0"): string
    {
        return rtrim($this->read($length), $charList);
    }

    /**
     * Reads <var>length</var> amount of bytes from the stream and returns
     * binary data as multibyte Unicode string. Removes terminating zero.
     *
     * The byte order is possibly determined from the byte order mark included
     * in the binary data string. The order parameter is updated if the BOM is
     * found.
     */
    final public function readString16(int $length, int &$order = null, bool $trimOrder = false): string
    {
        $value = $this->read($length);

        if (strlen($value) < 2) {
            return '';
        }

        if (ord($value[0]) === 0xfe && ord($value[1]) === 0xff) {
            $order = self::BIG_ENDIAN_ORDER;
            if ($trimOrder) {
                $value = substr($value, 2);
            }
        }
        if (ord($value[0]) === 0xff && ord($value[1]) === 0xfe) {
            $order = self::LITTLE_ENDIAN_ORDER;
            if ($trimOrder) {
                $value = substr($value, 2);
            }
        }

        while (str_ends_with($value, "\0\0")) {
            $value = substr($value, 0, -2);
        }

        return $value;
    }

    /**
     * Reads <var>length</var> amount of bytes from the stream and returns
     * binary data as hexadecimal string having high nibble first.
     *
     * @throws DomainAssertion if <var>length</var> attribute is negative or if
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readHHex(int $length): string
    {
        /** @psalm-var string $hex */
        [$hex] = unpack('H*0', $this->read($length));

        return $hex;
    }

    /**
     * Reads <var>length</var> amount of bytes from the stream and returns
     * binary data as hexadecimal string having low nibble first.
     *
     * @throws DomainAssertion if <var>length</var> attribute is negative or if
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readLHex(int $length): string
    {
        /** @psalm-var string $hex */
        [$hex] = unpack('h*0', $this->read($length));

        return $hex;
    }

    /**
     * Reads 16 bytes from the stream and returns the little-endian ordered
     * binary data as mixed-ordered hexadecimal GUID string.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readGuid(): string
    {
        $C = @unpack('V1V/v2v/N2N', $this->read(16));
        /** @psalm-var string $hex */
        [$hex] = @unpack('H*0', pack('NnnNN', $C['V'], $C['v1'], $C['v2'], $C['N1'], $C['N2']));

        return preg_replace('/^(.{8})(.{4})(.{4})(.{4})/', "\\1-\\2-\\3-\\4-", $hex);
    }

    /**
     * Resets the stream. Attempts to reset it in some way appropriate to the
     * particular stream, for example by repositioning it to its starting point.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    public function reset(): void
    {
        fseek($this->checkStreamAvailable(), 0);
    }

    /**
     * Closes the stream. Once a stream has been closed, further calls to read
     * methods will throw an exception. Closing a previously-closed stream,
     * however, has no effect.
     */
    public function close(): void
    {
        if (is_resource($this->fileDescriptor)) {
            @fclose($this->fileDescriptor);
        }
        if ($this->fileDescriptor !== null) {
            $this->fileDescriptor = null;
        }
    }

    /**
     * Returns the current machine endian order.
     */
    private function getEndianess(): int
    {
        if (0 === self::$endianessValue) {
            // @codeCoverageIgnoreStart
            self::$endianessValue = $this->fromInt32("\x01\x00\x00\x00") === 1
                             ? self::LITTLE_ENDIAN_ORDER
                             : self::BIG_ENDIAN_ORDER;
        }
        // @codeCoverageIgnoreEnd
        return self::$endianessValue;
    }

    /**
     * Returns whether the current machine endian order is little endian.
     */
    private function isLittleEndian(): bool
    {
        return $this->getEndianess() === self::LITTLE_ENDIAN_ORDER;
    }

    /**
     * Returns whether the current machine endian order is big endian.
     */
    private function isBigEndian(): bool
    {
        return $this->getEndianess() === self::BIG_ENDIAN_ORDER;
    }

    /**
     * Magic function so that $obj->value will work.
     *
     * @return mixed
     * @throws OutOfRangeException if requested property is unknown
     */
    public function __get(string $name)
    {
        $methodName = 'get' . ucfirst(strtolower($name));
        if (method_exists($this, $methodName)) {
            return $this->$methodName();
        }

        throw new OutOfRangeException("Unknown field: {$name}");
    }

    /**
     * Magic function so that assignments with $obj->value will work.
     *
     * @throws InvalidArgumentException if property to set is unknown
     */
    public function __set(string $name, string $value): void
    {
        if (method_exists($this, 'set' . ucfirst(strtolower($name)))) {
            $this->{'set' . ucfirst(strtolower($name))}($value);
            return;
        }

        throw new InvalidArgumentException("Unknown field: {$name}");
    }

    public function __isset(string $name): bool
    {
        return method_exists($this, 'get' . ucfirst(strtolower($name)));
    }
}

