<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model;

use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Serialize\Serializer\Json;

class Logger
{
    /**
     * @var string
     */
    protected $defaultLoggerFilename = 'leat/system.log';

    /**
     * @var string
     */
    protected $defaultLoggerDebugFilename = 'leat/debug.log';

    /**
     * @var string
     */
    protected $logBasePath;

    /**
     * @var string
     */
    protected $logFileName;

    /**
     * @var string
     */
    protected $debugFileName;


    /**
     * @param LoggerFormatter $loggerFormatter
     * @param File $ioFile
     * @param DirectoryList $directoryList
     * @param Json $jsonSerializer
     */
    public function __construct(
        protected LoggerFormatter $loggerFormatter,
        protected File $ioFile,
        protected DirectoryList $directoryList,
        protected Json $jsonSerializer
    ) {
        // Self-init
        $this->initLoggerDefaults();
    }

    /**
     *
     */
    protected function initLoggerDefaults()
    {
        $this
            ->setFilename($this->defaultLoggerFilename)
            ->setDebugFilename($this->defaultLoggerDebugFilename)
        ;

        return $this;
    }

    /**
     * Set filename of main log file
     *
     * @return $this
     */
    public function setFilename($fileName)
    {
        $this->logFileName = $fileName;
        return $this;
    }

    /**
     * Set filename of debug log file
     *
     * @return $this
     */
    public function setDebugFilename($fileName)
    {
        $this->debugFileName = $fileName;
        return $this;
    }

    /**
     *
     */
    public function getLogBasePath()
    {
        if (!$this->logBasePath) {
            $this->logBasePath = $this->directoryList->getPath('log');
        }

        return $this->logBasePath;
    }

    /**
     *
     */
    protected $initedFiles = [];

    /**
     *
     */
    protected function getFullInitedFilename($fileName)
    {
        if (!array_key_exists($fileName, $this->initedFiles)) {
            $fullFileName = $this->getLogBasePath() . DIRECTORY_SEPARATOR . $fileName;
            $pathInfo = pathinfo($fullFileName);

            if (!is_dir($pathInfo['dirname'])) {
                $result = @mkdir($pathInfo['dirname'], 0750, true);

                if ($result !== true) {
                    return false;
                }
            }

            $this->initedFiles[$fileName] = $fullFileName;
        }

        return $this->initedFiles[$fileName];
    }

    /**
     *
     */
    public function output($message, $fileName)
    {
        if ($fullFileName = $this->getFullInitedFilename($fileName)) {
            $message = $this->loggerFormatter->prepareForOutput($message, false);
            $message = date('Y-m-d H:i:s') . ': ' . $message . PHP_EOL;
            file_put_contents($fullFileName, $message, FILE_APPEND);
        }
        return $this;
    }

    /**
     * Log with optional parameters
     *
     * @param $message
     * @param bool $debug
     * @param array $context
     * @return Logger
     */
    public function log($message, bool $debug = false, array $context = [])
    {
        if (!empty($context) && is_string($message)) {
            $message = sprintf('%s [%s]', $message, $this->jsonSerializer->serialize($context));
        }

        if ($debug) {
            $this->debug($message);
        } else {
            $this->output($message, $this->logFileName);
        }
        return $this;
    }

    /**
     *
     */
    public function debug($message = '')
    {
        return $this->output($message, $this->debugFileName);
    }
}
