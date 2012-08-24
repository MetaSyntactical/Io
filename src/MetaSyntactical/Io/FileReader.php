<?php

namespace MetaSyntactical\Io;

use MetaSyntactical\Io\Exception\FileNotFoundException;

class FileReader extends Reader
{
    /**
     * Constructs the Zend_Io_FileReader class with given path to the file. By
     * default the file is opened in read (rb) mode.
     *
     * @param string $filename The path to the file.
     * @param null $mode
     * @param string $mode File mode
     * @throws FileNotFoundException if file is not readable
     */
    public function __construct($filename, $mode = null)
    {
        if ($mode === null) {
            $mode = 'rb';
        }
        if (!file_exists($filename) || !is_readable($filename) || ($fd = fopen($filename, $mode)) === false) {
            throw new FileNotFoundException('Unable to open file for reading: ' . $filename);
        }
        parent::__construct($fd);
    }

    /**
     * Closes the file descriptor.
     */
    public function __destruct()
    {
        $this->close();
    }
}

