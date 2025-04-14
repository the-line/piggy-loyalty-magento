<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Plugin\JobDigest;

use Leat\AsyncQueue\Model\Job;
use Leat\AsyncQueue\Model\Request;
use Leat\AsyncQueue\Service\JobDigest;
use Leat\Loyalty\Exception\NoContactException;
use Leat\Loyalty\Model\CustomerContactLink;
use Leat\LoyaltyAsync\Model\Queue\Builder\Service\ContactBuilder;
use Magento\Framework\Exception\LocalizedException;

class ExceptionHandler
{
    public function __construct(
        protected CustomerContactLink $contact,
        protected ContactBuilder $contactBuilder
    ) {
    }

    /**
     * If the exception is a NoContactException, we want to add a job to create a contact in Leat
     * - Execute JobDigest again with the new job
     *
     * @param JobDigest $subject
     * @param null $result
     * @param Job $job
     * @param Request $request
     * @param $exception
     * @return void
     * @throws LocalizedException
     */
    public function afterHandleException(
        JobDigest $subject,
        null $result,
        Job $job,
        Request $request,
        $exception
    ): void {
        switch (true) {
            case $exception instanceof NoContactException:
                // Add a job to create a contact in Leat for this customer, the error query will be resolved
                // with the next attempt
                $customer = $this->contact->getCustomer($job->getRelationId());
                if ($customer) {
                    $job = $this->contactBuilder->addNewContact($customer);
                    $subject->setJob($job)->execute();
                }
                break;
            default:
                break;
        }
    }
}
