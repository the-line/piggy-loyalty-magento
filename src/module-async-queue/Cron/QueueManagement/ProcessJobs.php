<?php


declare(strict_types=1);

namespace Leat\AsyncQueue\Cron\QueueManagement;

use Leat\AsyncQueue\Service\JobDigest;

class ProcessJobs
{
    public function __construct(
        protected JobDigest $queueDigest
    ) {
    }

    /**
     * @return void
     * @throws \Magento\Framework\Exception\AuthenticationException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $this->queueDigest->execute();
    }
}
