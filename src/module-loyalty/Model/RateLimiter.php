<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model;

class RateLimiter implements RateLimiterInterface
{
    /**
     * Number of milliseconds in a second
     */
    private const int MILLISECONDS_PER_SECOND = 1000;

    /**
     * Number of microseconds in a millisecond
     */
    private const int MICROSECONDS_PER_MILLISECOND = 1000;

    /**
     * Timestamp of the last execution
     */
    private float $lastExecutionTime;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * Constructor
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->lastExecutionTime = $this->getCurrentTimeInMilliseconds();
    }

    /**
     * Apply rate limiting based on configuration
     *
     * @return void
     */
    public function limit(): void
    {
        $callsPerSecond = $this->config->getCallsPerSecond();
        $minimumDelayBetweenCalls = ceil(self::MILLISECONDS_PER_SECOND / $callsPerSecond);
        $currentTimeInMs = $this->getCurrentTimeInMilliseconds();
        $elapsedTimeSinceLastCall = ($currentTimeInMs - $this->lastExecutionTime);

        if ($elapsedTimeSinceLastCall < $minimumDelayBetweenCalls) {
            $this->sleep($minimumDelayBetweenCalls - $elapsedTimeSinceLastCall);
        }

        $this->lastExecutionTime = $this->getCurrentTimeInMilliseconds();
    }

    /**
     * Sleep for the specified number of milliseconds
     *
     * @param float $milliseconds
     * @return void
     */
    private function sleep(float $milliseconds): void
    {
        $microseconds = round($milliseconds * self::MICROSECONDS_PER_MILLISECOND);

        // For long delays, use sleep() which accepts seconds
        if ($microseconds >= (self::MILLISECONDS_PER_SECOND * self::MICROSECONDS_PER_MILLISECOND)) {
            $seconds = floor($milliseconds / self::MILLISECONDS_PER_SECOND);
            sleep((int) $seconds);

            // Calculate remaining microseconds
            $remainingMicroseconds = $microseconds - ($seconds * self::MILLISECONDS_PER_SECOND * self::MICROSECONDS_PER_MILLISECOND);
            if ($remainingMicroseconds > 0) {
                usleep((int) $remainingMicroseconds);
            }
        } else {
            // For short delays, use usleep() which accepts microseconds
            usleep((int) $microseconds);
        }
    }

    /**
     * Get current time in milliseconds
     *
     * @return float
     */
    private function getCurrentTimeInMilliseconds(): float
    {
        return microtime(true) * self::MILLISECONDS_PER_SECOND;
    }
}
