<?php

namespace MetaSyntactical\Io;

use MetaSyntactical\Io\Exception\InvalidResourceTypeException;
use MetaSyntactical\Io\Exception\InvalidStreamException;
use MetaSyntactical\Io\Exception\DomainAssertion;
use MetaSyntactical\Io\Exception\OutOfRangeException;
use MetaSyntactical\Io\Exception\InvalidArgumentException;

/**
 *
 * @property      integer $offset    point of operation in stream
 * @property-read integer $endianess endianess of the current machine
 */
class Reader
{
    const MACHINE_ENDIAN_ORDER = 0;
    const LITTLE_ENDIAN_ORDER  = 1;
    const BIG_ENDIAN_ORDER     = 2;

    /**
     * The endianess of the current machine.
     *
     * @var integer
     */
    private static $endianess = 0;

    /**
     * The resource identifier of the stream.
     *
     * @var resource
     */
    protected $fileDescriptor = null;

    /**
     * Size of the underlying stream.
     *
     * @var integer
     */
    protected $size = 0;

    /**
     * Constructs the Zend_Io_Reader class with given open file descriptor.
     *
     * @param resource $fd The file descriptor.
     * @throws InvalidResourceTypeException if given file descriptor is not valid
     */
    public function __construct($fd)
    {
        if (!is_resource($fd) || !in_array(get_resource_type($fd), array('stream'))) {
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
     * @return boolean
     * @throws InvalidStreamException if an I/O error occurs
     */
    public function available()
    {
        return $this->getOffset() < $this->getSize();
    }

    /**
     * Checks if a stream is available.
     *
     * @throws InvalidStreamException if an I/O error occurs
     */
    protected function checkStreamAvailable()
    {
        if (is_null($this->fileDescriptor) || !is_resource($this->fileDescriptor)) {
            throw new InvalidStreamException('Cannot operate on a closed stream');
        }
    }

    /**
     * Returns the current point of operation.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    public function getOffset()
    {
        $this->checkStreamAvailable();
        return ftell($this->fileDescriptor);
    }

    /**
     * Sets the point of operation, ie the cursor offset value. The offset may
     * also be set to a negative value when it is interpreted as an offset from
     * the end of the stream instead of the beginning.
     *
     * @param integer $offset The new point of operation.
     * @return void
     * @throws InvalidStreamException if an I/O error occurs
     */
    public function setOffset($offset)
    {
        $this->checkStreamAvailable();
        fseek($this->fileDescriptor, $offset < 0 ? $this->getSize() + $offset : $offset);
    }

    /**
     * Returns the stream size in bytes.
     *
     * @return integer
     */
    public function getSize()
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
        return $this->fileDescriptor;
    }

    /**
     * Jumps <var>size</var> amount of bytes in the stream.
     *
     * @param integer $size The amount of bytes.
     * @return void
     * @throws DomainAssertion|InvalidStreamException if <var>size</var> attribute is negative or if
     *  an I/O error occurs
     */
    public function skip($size)
    {
        if ($size < 0) {
            throw new DomainAssertion('Size cannot be negative');
        }
        if ($size == 0) {
            return;
        }
        $this->checkStreamAvailable();
        fseek($this->fileDescriptor, $size, SEEK_CUR);
    }

    /**
     * Reads <var>length</var> amount of bytes from the stream.
     *
     * @param integer $length The amount of bytes.
     * @return string
     * @throws DomainAssertion|InvalidStreamException if <var>length</var> attribute is negative or
     *  if an I/O error occurs
     */
    public function read($length)
    {
        if ($length < 0) {
            throw new DomainAssertion('Length cannot be negative');
        }
        if ($length == 0) {
            return '';
        }
        $this->checkStreamAvailable();
        return fread($this->fileDescriptor, $length);
    }

    /**
     * Reads 1 byte from the stream and returns binary data as an 8-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt8()
    {
        $ord = ord($this->read(1));
        if ($ord > 127) {
            return -$ord - 2 * (128 - $ord);
        } else {
            return $ord;
        }
    }

    /**
     * Reads 1 byte from the stream and returns binary data as an unsigned 8-bit
     * integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt8()
    {
        return ord($this->read(1));
    }

    /**
     * Returns machine endian ordered binary data as signed 16-bit integer.
     *
     * @param string $value The binary data string.
     * @return integer
     */
    private function fromInt16($value)
    {
        list(, $int) = unpack('s*', $value);
        return $int;
    }

    /**
     * Reads 2 bytes from the stream and returns little-endian ordered binary
     * data as signed 16-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt16LE()
    {
        if ($this->isBigEndian()) {
            return $this->fromInt16(strrev($this->read(2)));
        } else {
            return $this->fromInt16($this->read(2));
        }
    }

    /**
     * Reads 2 bytes from the stream and returns big-endian ordered binary data
     * as signed 16-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt16BE()
    {
        if ($this->isLittleEndian()) {
            return $this->fromInt16(strrev($this->read(2)));
        } else {
            return $this->fromInt16($this->read(2));
        }
    }

    /**
     * Reads 2 bytes from the stream and returns machine ordered binary data
     * as signed 16-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt16()
    {
        return $this->fromInt16($this->read(2));
    }

    /**
     * Returns machine endian ordered binary data as unsigned 16-bit integer.
     *
     * @param string  $value The binary data string.
     * @param integer $order The byte order of the binary data string.
     * @return integer
     */
    private function fromUInt16($value, $order = 0)
    {
        list(, $int) = unpack(
            ($order == self::BIG_ENDIAN_ORDER ? 'n' : ($order == self::LITTLE_ENDIAN_ORDER ? 'v' : 'S')) . '*',
            $value
        );
        return $int;
    }

    /**
     * Reads 2 bytes from the stream and returns little-endian ordered binary
     * data as unsigned 16-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt16LE()
    {
        return $this->fromUInt16($this->read(2), self::LITTLE_ENDIAN_ORDER);
    }

    /**
     * Reads 2 bytes from the stream and returns big-endian ordered binary data
     * as unsigned 16-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt16BE()
    {
        return $this->fromUInt16($this->read(2), self::BIG_ENDIAN_ORDER);
    }

    /**
     * Reads 2 bytes from the stream and returns machine ordered binary data
     * as unsigned 16-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt16()
    {
        return $this->fromUInt16($this->read(2), self::MACHINE_ENDIAN_ORDER);
    }

    /**
     * Returns machine endian ordered binary data as signed 24-bit integer.
     *
     * @param string $value The binary data string.
     * @return integer
     */
    private function fromInt24($value)
    {
        list(, $int) = unpack('l*', $this->isLittleEndian() ? ("\x00" . $value) : ($value . "\x00"));
        return $int;
    }

    /**
     * Reads 3 bytes from the stream and returns little-endian ordered binary
     * data as signed 24-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt24LE()
    {
        if ($this->isBigEndian()) {
            return $this->fromInt24(strrev($this->read(3)));
        } else {
            return $this->fromInt24($this->read(3));
        }
    }

    /**
     * Reads 3 bytes from the stream and returns big-endian ordered binary data
     * as signed 24-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt24BE()
    {
        if ($this->isLittleEndian()) {
            return $this->fromInt24(strrev($this->read(3)));
        } else {
            return $this->fromInt24($this->read(3));
        }
    }

    /**
     * Reads 3 bytes from the stream and returns machine ordered binary data
     * as signed 24-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt24()
    {
        return $this->fromInt24($this->read(3));
    }

    /**
     * Returns machine endian ordered binary data as unsigned 24-bit integer.
     *
     * @param string  $value The binary data string.
     * @param integer $order The byte order of the binary data string.
     * @return integer
     */
    private function fromUInt24($value, $order = 0)
    {
        list(, $int) = unpack(
            ($order == self::BIG_ENDIAN_ORDER ? 'N' : ($order == self::LITTLE_ENDIAN_ORDER ? 'V' : 'L')) . '*',
            $this->isLittleEndian() ? ("\x00" . $value) : ($value . "\x00")
        );
        return $int;
    }

    /**
     * Reads 3 bytes from the stream and returns little-endian ordered binary
     * data as unsigned 24-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt24LE()
    {
        return $this->fromUInt24($this->read(3), self::LITTLE_ENDIAN_ORDER);
    }

    /**
     * Reads 3 bytes from the stream and returns big-endian ordered binary data
     * as unsigned 24-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt24BE()
    {
        return $this->fromUInt24($this->read(3), self::BIG_ENDIAN_ORDER);
    }

    /**
     * Reads 3 bytes from the stream and returns machine ordered binary data
     * as unsigned 24-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt24()
    {
        return $this->fromUInt24($this->read(3), self::MACHINE_ENDIAN_ORDER);
    }

    /**
     * Returns machine-endian ordered binary data as signed 32-bit integer.
     *
     * @param string $value The binary data string.
     * @return integer
     */
    private final function fromInt32($value)
    {
        list(, $int) = unpack('l*', $value);
        return $int;
    }

    /**
     * Reads 4 bytes from the stream and returns little-endian ordered binary
     * data as signed 32-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt32LE()
    {
        if ($this->isBigEndian()) {
            return $this->fromInt32(strrev($this->read(4)));
        } else {
            return $this->fromInt32($this->read(4));
        }
    }

    /**
     * Reads 4 bytes from the stream and returns big-endian ordered binary data
     * as signed 32-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt32BE()
    {
        if ($this->isLittleEndian()) {
            return $this->fromInt32(strrev($this->read(4)));
        } else {
            return $this->fromInt32($this->read(4));
        }
    }

    /**
     * Reads 4 bytes from the stream and returns machine ordered binary data
     * as signed 32-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt32()
    {
        return $this->fromInt32($this->read(4));
    }

    /**
     * Reads 4 bytes from the stream and returns little-endian ordered binary
     * data as unsigned 32-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt32LE()
    {
        if (PHP_INT_SIZE < 8) {
            // @codeCoverageIgnoreStart
            list(, $lo, $hi) = unpack('v*', $this->read(4));
            return $hi * (0xffff+1) + $lo; // eq $hi << 16 | $lo
            // @codeCoverageIgnoreEnd
        } else {
            list(, $int) = unpack('V*', $this->read(4));
            return $int;
        }
    }

    /**
     * Reads 4 bytes from the stream and returns big-endian ordered binary data
     * as unsigned 32-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt32BE()
    {
        if (PHP_INT_SIZE < 8) {
            // @codeCoverageIgnoreStart
            list(, $hi, $lo) = unpack('n*', $this->read(4));
            return $hi * (0xffff+1) + $lo; // eq $hi << 16 | $lo
            // @codeCoverageIgnoreEnd
        } else {
            list(, $int) = unpack('N*', $this->read(4));
            return $int;
        }
    }

    /**
     * Reads 4 bytes from the stream and returns machine ordered binary data
     * as unsigned 32-bit integer.
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readUInt32()
    {
        if (PHP_INT_SIZE < 8) {
            // @codeCoverageIgnoreStart
            list(, $hi, $lo) = unpack('S*', $this->read(4)) + array(0, 0, 0);
            return $hi * (0xffff+1) + $lo; // eq $hi << 16 | $lo
            // @codeCoverageIgnoreEnd
        } else {
            list(, $int) = unpack('L*', $this->read(4)) + array(0, 0);
            return $int;
        }
    }

    /**
     * Reads 8 bytes from the stream and returns little-endian ordered binary
     * data as 64-bit float.
     *
     * {@internal PHP does not support 64-bit integers as the long
     * integer is of 32-bits but using aritmetic operations it is implicitly
     * converted into floating point which is of 64-bits long.}}
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt64LE()
    {
        list(, $lolo, $lohi, $hilo, $hihi) = unpack('v*', $this->read(8));
        return ($hihi * (0xffff+1) + $hilo) * (0xffffffff+1) + ($lohi * (0xffff+1) + $lolo);
    }

    /**
     * Reads 8 bytes from the stream and returns big-endian ordered binary data
     * as 64-bit float.
     *
     * {@internal PHP does not support 64-bit integers as the long integer is of
     * 32-bits but using aritmetic operations it is implicitly converted into
     * floating point which is of 64-bits long.}}
     *
     * @return integer
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readInt64BE()
    {
        list(, $hihi, $hilo, $lohi, $lolo) = unpack('n*', $this->read(8));
        return ($hihi * (0xffff+1) + $hilo) * (0xffffffff+1) + ($lohi * (0xffff+1) + $lolo);
    }

    /**
     * Returns machine endian ordered binary data as a 32-bit floating point
     * number as defined by IEEE 754.
     *
     * @param string $value The binary data string.
     * @return float
     */
    private function fromFloat($value)
    {
        list(, $float) = unpack('f', $value);
        return $float;
    }

    /**
     * Reads 4 bytes from the stream and returns little-endian ordered binary
     * data as a 32-bit float point number as defined by IEEE 754.
     *
     * @return float
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readFloatLE()
    {
        if ($this->isBigEndian()) {
            return $this->fromFloat(strrev($this->read(4)));
        } else {
            return $this->fromFloat($this->read(4));
        }
    }

    /**
     * Reads 4 bytes from the stream and returns big-endian ordered binary data
     * as a 32-bit float point number as defined by IEEE 754.
     *
     * @return float
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readFloatBE()
    {
        if ($this->isLittleEndian()) {
            return $this->fromFloat(strrev($this->read(4)));
        } else {
            return $this->fromFloat($this->read(4));
        }
    }

    /**
     * Returns machine endian ordered binary data as a 64-bit floating point
     * number as defined by IEEE754.
     *
     * @param string $value The binary data string.
     * @return float
     */
    private function fromDouble($value)
    {
        list(, $double) = unpack('d', $value);
        return $double;
    }

    /**
     * Reads 8 bytes from the stream and returns little-endian ordered binary
     * data as a 64-bit floating point number as defined by IEEE 754.
     *
     * @return float
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readDoubleLE()
    {
        if ($this->isBigEndian()) {
            return $this->fromDouble(strrev($this->read(8)));
        } else {
            return $this->fromDouble($this->read(8));
        }
    }

    /**
     * Reads 8 bytes from the stream and returns big-endian ordered binary data
     * as a 64-bit float point number as defined by IEEE 754.
     *
     * @return float
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readDoubleBE()
    {
        if ($this->isLittleEndian()) {
            return $this->fromDouble(strrev($this->read(8)));
        } else {
            return $this->fromDouble($this->read(8));
        }
    }

    /**
     * Reads <var>length</var> amount of bytes from the stream and returns
     * binary data as string. Removes terminating zero.
     *
     * @param integer $length   The amount of bytes.
     * @param string  $charList The list of characters you want to strip.
     * @return string
     * @throws DomainAssertion|InvalidStreamException if <var>length</var> attribute is negative or
     *  if an I/O error occurs
     */
    final public function readString8($length, $charList = "\0")
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
     *
     * @param integer $length    The amount of bytes.
     * @param integer &$order     The endianess of the string.
     * @param boolean $trimOrder Whether to remove the byte order mark read the string.
     * @return string
     */
    final public function readString16($length, &$order = null, $trimOrder = false)
    {
        $value = $this->read($length);

        if (strlen($value) < 2) {
            return '';
        }

        if (ord($value[0]) == 0xfe && ord($value[1]) == 0xff) {
            $order = self::BIG_ENDIAN_ORDER;
            if ($trimOrder) {
                $value = substr($value, 2);
            }
        }
        if (ord($value[0]) == 0xff && ord($value[1]) == 0xfe) {
            $order = self::LITTLE_ENDIAN_ORDER;
            if ($trimOrder) {
                $value = substr($value, 2);
            }
        }

        while (substr($value, -2) == "\0\0") {
            $value = substr($value, 0, -2);
        }

        return $value;
    }

    /**
     * Reads <var>length</var> amount of bytes from the stream and returns
     * binary data as hexadecimal string having high nibble first.
     *
     * @param integer $length The amount of bytes.
     * @return string
     * @throws DomainAssertion|InvalidStreamException if <var>length</var> attribute is negative or
     *  if an I/O error occurs
     */
    final public function readHHex($length)
    {
        list($hex) = unpack('H*0', $this->read($length));
        return $hex;
    }

    /**
     * Reads <var>length</var> amount of bytes from the stream and returns
     * binary data as hexadecimal string having low nibble first.
     *
     * @param integer $length The amount of bytes.
     * @return string
     * @throws DomainAssertion|InvalidStreamException if <var>length</var> attribute is negative or
     *  if an I/O error occurs
     */
    final public function readLHex($length)
    {
        list($hex) = unpack('h*0', $this->read($length));
        return $hex;
    }

    /**
     * Reads 16 bytes from the stream and returns the little-endian ordered
     * binary data as mixed-ordered hexadecimal GUID string.
     *
     * @return string
     * @throws InvalidStreamException if an I/O error occurs
     */
    final public function readGuid()
    {
        $C = @unpack('V1V/v2v/N2N', $this->read(16));
        list($hex) = @unpack('H*0', pack('NnnNN', $C['V'], $C['v1'], $C['v2'], $C['N1'], $C['N2']));

        /* Fixes a bug in PHP versions earlier than Jan 25 2006 */
        if (implode('', unpack('H*', pack('H*', 'a'))) == 'a00') {
            // @codeCoverageIgnoreStart
            $hex = substr($hex, 0, -1);
        }
        // @codeCoverageIgnoreEnd

        return preg_replace('/^(.{8})(.{4})(.{4})(.{4})/', "\\1-\\2-\\3-\\4-", $hex);
    }

    /**
     * Resets the stream. Attempts to reset it in some way appropriate to the
     * particular stream, for example by repositioning it to its starting point.
     *
     * @return void
     * @throws InvalidStreamException if an I/O error occurs
     */
    public function reset()
    {
        $this->checkStreamAvailable();
        fseek($this->fileDescriptor, 0);
    }

    /**
     * Closes the stream. Once a stream has been closed, further calls to read
     * methods will throw an exception. Closing a previously-closed stream,
     * however, has no effect.
     *
     * @return void
     */
    public function close()
    {
        if ($this->fileDescriptor !== null) {
            @fclose($this->fileDescriptor);
            $this->fileDescriptor = null;
        }
    }

    /**
     * Returns the current machine endian order.
     *
     * @return integer
     */
    private function getEndianess()
    {
        if (0 === self::$endianess) {
            self::$endianess = $this->fromInt32("\x01\x00\x00\x00") == 1
                             ? self::LITTLE_ENDIAN_ORDER
                             : self::BIG_ENDIAN_ORDER;
        }
        return self::$endianess;
    }

    /**
     * Returns whether the current machine endian order is little endian.
     *
     * @return boolean
     */
    private function isLittleEndian()
    {
        return $this->getEndianess() == self::LITTLE_ENDIAN_ORDER;
    }

    /**
     * Returns whether the current machine endian order is big endian.
     *
     * @return boolean
     */
    private function isBigEndian()
    {
        return $this->getEndianess() == self::BIG_ENDIAN_ORDER;
    }

    /**
     * Magic function so that $obj->value will work.
     *
     * @param string $name The field name.
     * @return mixed
     * @throws OutOfRangeException if requested property is unknown
     */
    public function __get($name)
    {
        if (method_exists($this, 'get' . ucfirst(strtolower($name)))) {
            return call_user_func(array($this, 'get' . ucfirst(strtolower($name))));
        } else {
            throw new OutOfRangeException('Unknown field: ' . $name);
        }
    }

    /**
     * Magic function so that assignments with $obj->value will work.
     *
     * @param string $name  The field name.
     * @param string $value The field value.
     * @return mixed
     * @throws InvalidArgumentException if property to set is unknown
     */
    public function __set($name, $value)
    {
        if (method_exists($this, 'set' . ucfirst(strtolower($name)))) {
            call_user_func(array($this, 'set' . ucfirst(strtolower($name))), $value);
        } else {
            throw new InvalidArgumentException('Unknown field: ' . $name);
        }
    }

}

