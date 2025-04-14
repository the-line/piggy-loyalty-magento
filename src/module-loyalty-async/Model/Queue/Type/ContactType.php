<?php


declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Type;

use Leat\AsyncQueue\Exception\IllegalJobException;
use Leat\AsyncQueue\Model\Job;
use Leat\AsyncQueue\Model\Request;
use Leat\Loyalty\Exception\NoContactException;
use Leat\LoyaltyAsync\Model\Queue\Type\Contact\ContactCreate;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

abstract class ContactType extends LeatGenericType
{
    protected const string CONNECTOR_CODE = 'leat_connector';

    /**
     * @var DataObject
     */
    protected DataObject $data;

    /**
     * Check if job is allowed to be executed based on customer conditions
     *
     * @param Job|null $job
     * @param Request|null $request
     * @return $this
     * @throws LocalizedException
     * @throws NoContactException
     */
    public function beforeExecute(Job $job = null, Request $request = null): static
    {
        if (isset($job) && !$this->isRequestCustomerValid($job, $request)) {
            throw new LocalizedException(__("Customer id Contact UUID or is missing"));
        }

        return parent::beforeExecute($job, $request);
    }

    /**
     * @param Job $job
     * @param Request $request
     * @return bool
     * @throws LocalizedException
     * @throws NoContactException
     */
    public function isRequestCustomerValid(Job $job, Request $request): bool
    {
        $customerId = (int) $job->getRelationId();
        if (!$customerId) {
            return false;
        }

        if (!($this instanceof ContactCreate) && !$this->contactResource->hasContactUuid($customerId)) {
            $customer = $this->contactResource->getCustomer($customerId);
            if ($customer && in_array($customer->getGroupId() ?? 0, $this->config->getCustomerGroupMapping())) {
                throw new NoContactException(__("Customer should have a contact but doesn't"));
            }

            // Job and request can be marked as completed, has been added to the queue by some mistake.
            $error = sprintf(
                "Customer with id %d has had a job added to the queue - not permitted - %s",
                $customer->getId(),
                sprintf(
                    "Job id: %d, Request id: %d, Request type: %s",
                    $job->getId(),
                    $request->getId(),
                    $request->getTypeCode()
                )
            );

            $this->getLogger()->log($error);
            throw new IllegalJobException($error);
        }

        return true;
    }
}
