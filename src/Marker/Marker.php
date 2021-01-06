<?php

namespace Tev\Marker;

use DateTime;
use Psr\Log\LoggerInterface;

/**
 * Class Marker
 * @package Tev\Marker
 *
 * This class is designed to mark checkpoints, throughout a process or script, enabling
 * analysis of how long a script is taking to run and how much resource it may be using.
 * It is designed so that it won't fail, should something be wrong. But will still log.
 *
 * You can also add whatever extra detail you wish to log, throughout.
 *
 */
class Marker implements MarkerInterface
{
    /**
     * For setting number formatting decimal places
     */
    const DECIMAL_PLACES = 3;

    /**
     * Name of the script or process being analysed
     * @var string
     */
    private $scriptName;

    /**
     * Each marker will have a unique id, making it easier to track in logs
     * @var string
     */
    private $id;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $dateTimeFormat = 'Y-m-d H:i:s';

    /**
     * @var float
     */
    private $startTime;

    /**
     * @var string
     */
    private $startDateTimeStamp;

    /**
     * @var float
     */
    private $finishTime;

    /**
     * @var int
     */
    private $startMemoryUsage;

    /**
     * @var int
     */
    private $finishMemoryUsage;

    /**
     * @var int
     */
    private $peakMemoryUsage;

    /**
     * @var bool
     */
    private $hasFinished;

    /**
     * @var bool
     */
    private $reportRealMemoryUsage;

    /**
     * @var bool
     */
    private $getMemoryUsageInMegabytes;

    /**
     * @var bool
     */
    private $hasStarted;

    /**
     * Marker constructor.
     * @param string $scriptName        : Name of the script/class you wish to mark.
     * @param LoggerInterface $logger   : Pass whichever PSR-3 compliant logger you wish to use.
     * @param bool $getMemoryUsageInMegabytes : set to false, if you wish to get the memory usage readout in bytes.
     * @param string $dateFormat        : Set a dateformat to log in. Otherwise it will use it's default.
     */
    public function __construct(
        $scriptName,
        LoggerInterface $logger,
        $getMemoryUsageInMegabytes = true,
        $dateFormat = ''
    ) {
        $this->scriptName                = $scriptName;
        $this->id                        = uniqid();
        $this->dateTimeFormat            = !empty($dateFormat) ?: $this->dateTimeFormat;
        $this->logger                    = $logger;
        $this->hasFinished               = false;
        $this->hasStarted                = false;
        $this->reportRealMemoryUsage     = false;
        $this->getMemoryUsageInMegabytes = $getMemoryUsageInMegabytes;
    }

    /**
     * Use once at an appropriate point where you wish to start from.
     * Ideally before you do anything else.
     *
     * @param string $extraInfo : Log whatever extra detail you feel is necessary
     */
    public function start($extraInfo = '')
    {
        if($this->hasStarted) {
            return;
        }

        $this->hasStarted           = true;
        $this->startTime            = microtime(true);
        $this->startDateTimeStamp   = $this->getCurrentDateTimeStamp();
        $this->startMemoryUsage     = memory_get_usage($this->reportRealMemoryUsage);
        $this->checkMemoryUsage();

        $message = sprintf('Marker %s for %s starting. Memory in use: %s.',
            $this->id,
            $this->scriptName,
            $this->getMemoryUsage()
        );

        if($extraInfo) {
            $message .= ' Detail: ' . $extraInfo;
        }

        $this->logger->info($message);
    }

    /**
     * This method is designed to be used multiple times. Use at appropriate points, throughout
     * to note key checkpoints, whilst a process is running.
     * @param string $extraInfo : Log whatever extra detail you feel is necessary
     */
    public function mark($extraInfo = '')
    {
        /**
         * It's preferable to call the start method yourself, so you can determine the correct
         * start point. However, the Marker class will not fail, and will start from the first marker.
         * But this may lead to poorer analysis.
         */
        if(!$this->hasStarted) {
            $this->start();
            $this->logger->warning('Marker asked to mark, without being started correctly.');
        }

        $this->checkMemoryUsage();

        $message = sprintf('Marker %s for %s. Memory in use: %s. Running for %s seconds, so far.',
            $this->id,
            $this->scriptName,
            $this->getMemoryUsage(),
            $this->getRunTime()
        );

        if($extraInfo) {
            $message .= ' Detail: ' . $extraInfo;
        }

        $this->logger->info($message);
    }

    /**
     * Use once, when the process has mostly completed its task
     *
     * @param string $extraInfo : Log whatever extra detail you feel is necessary
     */
    public function finish($extraInfo = '')
    {
        if(!$this->hasFinished) {
            $this->hasFinished = true;
            $this->finishTime = $this->getRunTime();
            $this->finishMemoryUsage = $this->getMemoryUsage();
            $this->checkMemoryUsage();

            $message = sprintf('Marker %s for script %s finished. Peak memory in use: %s. Started at: %s. Time to run: %s seconds',
                $this->id,
                $this->scriptName,
                $this->getPeakMemoryUsage(),
                $this->startDateTimeStamp,
                $this->finishTime
            );

            if($extraInfo) {
                $message .= ' Detail: ' . $extraInfo;
            }

            $this->logger->info($message);
        }
    }

    /**
     * Use wherever you want to try and record a potential peak memory usage in a script, without adding a mark.
     */
    public function checkMemoryUsage()
    {
        $currentMemoryUsage = memory_get_usage($this->reportRealMemoryUsage);
        if($this->peakMemoryUsage < $currentMemoryUsage) {
            $this->peakMemoryUsage = $currentMemoryUsage;
        }
    }

    /**
     * Destruct method
     */
    public function __destruct()
    {
        if(!$this->hasFinished) {
            $this->logger->warning(
                sprintf('Marker %s for script %s was not finished correctly. Peak memory in use: %s (%s diff to start usage). Time to run: %s seconds',
                    $this->id,
                    $this->scriptName,
                    $this->getPeakMemoryUsage(),
                    $this->getMemoryUsageDifference(),
                    $this->getRunTime()
                )
            );
        }
    }

    /**
     * Get a readout of memory in use
     * @return string
     */
    private function getMemoryUsage()
    {
        return $this->formatMemoryUsageString(memory_get_usage($this->reportRealMemoryUsage));
    }

    /**
     * Get a readout of the peak memory usage
     * @return string
     */
    private function getPeakMemoryUsage()
    {
        return $this->formatMemoryUsageString($this->peakMemoryUsage);
    }

    /**
     * Get a readout of the memory usage difference
     * @return string
     */
    private function getMemoryUsageDifference()
    {
        $memoryUsage = $this->peakMemoryUsage - $this->startMemoryUsage;

        return $this->formatMemoryUsageString($memoryUsage);
    }

    /**
     * Format memory usage readout in Megabytes or bytes
     * @param $memoryUsage
     * @return string
     */
    private function formatMemoryUsageString($memoryUsage)
    {
        if($this->getMemoryUsageInMegabytes) {
            return (string)number_format($memoryUsage / 1024 / 1024, self::DECIMAL_PLACES) . ' MBs';
        }

        return (string)$memoryUsage . ' Bytes';
    }

    /**
     * @return float
     */
    private function getRunTime()
    {
        $interval = microtime(true) - $this->startTime;
        return $this->formatSeconds($interval);
    }

    /**
     * @return string
     */
    private function getCurrentDateTimeStamp()
    {
        $dateTime = new DateTime('now');
        return $dateTime->format($this->dateTimeFormat);
    }

    /**
     * @param $seconds
     * @return string
     */
    private function formatSeconds($seconds)
    {
        return number_format($seconds, self::DECIMAL_PLACES);
    }
}
