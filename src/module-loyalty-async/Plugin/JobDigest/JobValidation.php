<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Plugin\JobDigest;

use Leat\AsyncQueue\Api\JobRepositoryInterface;
use Leat\AsyncQueue\Model\Job as AsyncJob;
use Leat\AsyncQueue\Service\JobDigest;
use Leat\LoyaltyAsync\Model\ResourceModel\JobQueries;

class JobValidation
{
    public function __construct(
        protected JobRepositoryInterface $jobRepository,
        protected JobQueries $jobQueries
    ) {
    }

    /**
     * If the job has a contact create request, we want to return true for the validation
     *
     * @param JobDigest $subject
     * @param bool $result
     * @param AsyncJob $job
     * @return bool
     */
    public function afterValidateJob(JobDigest $subject, bool $result, AsyncJob $job): bool
    {
        if ($this->jobQueries->hasContactCreateRequest($job)) {
            $result = true;
        }

        return $result;
    }
}
