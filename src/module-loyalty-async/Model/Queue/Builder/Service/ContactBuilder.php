<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Builder\Service;

use Leat\AsyncQueue\Api\Data\JobInterface;
use Leat\AsyncQueue\Model\Job;
use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\LoyaltyAsync\Model\Queue\Builder\LoyaltyJobBuilder;
use Leat\LoyaltyAsync\Model\Queue\Type\Contact\ContactCreate;
use Leat\LoyaltyAsync\Model\Queue\Type\Contact\ContactUpdate;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Data\Customer as CustomerData;
use Magento\Framework\Exception\LocalizedException;

class ContactBuilder
{
    public function __construct(
        protected LoyaltyJobBuilder $jobBuilder,
        protected RequestTypePool $requestTypePool
    ) {
    }

    /**
     * Insert customer_create followed by update request into the queue.
     * - Create contact
     * - Update contact with necessary information like name and address
     *
     * If requested:
     * - Must have placed an order in the last year
     * - No pending contact creation jobs
     *
     * @param Customer|CustomerData $customer
     * @return Job
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addNewContact(Customer|CustomerData $customer): Job
    {
        $customerCreate = $this->requestTypePool->getRequestType(ContactCreate::getTypeCode());
        $customerCreate = $customerCreate->setData('email', $customer->getEmail());

        $customerUpdate = $this->requestTypePool->getRequestType(ContactUpdate::getTypeCode());
        $customerUpdate = $customerUpdate->setData('email', $customer->getEmail())
            ->setData('firstname', $customer->getFirstname())
            ->setData('lastname', $customer->getLastname());

        if ($address = $this->getCustomerAddress($customer)) {
            $customerUpdate = $customerUpdate->setData('address', $address);
        }

        $job = $this->jobBuilder
            ->newJob($customer->getId())
            ->setStoreId((int) $customer->getStoreId())
            ->addRequest(
                $customerCreate->getPayload(),
                $customerCreate::getTypeCode()
            )->addRequest(
                $customerUpdate->getPayload(),
                $customerUpdate::getTypeCode()
            );

        return $job->create();
    }

    /**
     * @param Customer|CustomerInterface $customer
     * @return JobInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addUpdateContactJob(Customer|CustomerInterface $customer): JobInterface
    {
        $customerUpdate = $this->requestTypePool->getRequestType(ContactUpdate::getTypeCode());
        $customerUpdate = $customerUpdate
            ->setData('firstname', $customer->getFirstname())
            ->setData('lastname', $customer->getLastname())
            ->setData('birthdate', $customer->getDob());

        return $this->jobBuilder
            ->newJob($customer->getId())
            ->setStoreId((int) $customer->getStoreId())
            ->addRequest(
                $customerUpdate->getPayload(),
                $customerUpdate::getTypeCode()
            )->create();
    }

    /**
     * @param Customer|CustomerInterface $customer
     * @return JobInterface
     * @throws LocalizedException
     */
    public function updateContactAddress(Customer|CustomerInterface $customer): ?JobInterface
    {
        $customerUpdate = $this->requestTypePool->getRequestType(ContactUpdate::getTypeCode());
        $address = $this->getCustomerAddress($customer);

        if ($address !== null) {
            $customerUpdate = $customerUpdate->setData('address', $address);
            return $this->jobBuilder
                ->newJob($customer->getId())
                ->setStoreId((int) $customer->getStoreId())
                ->addRequest(
                    $customerUpdate->getPayload(),
                    $customerUpdate::getTypeCode()
                )->create();
        }
        return null;
    }

    /**
     * @param $customer
     * @return array|null
     */
    protected function getCustomerAddress($customer): ?array
    {
        if ($customer instanceof Customer) {
            return $customer->getDefaultBillingAddress()->getData();
        } elseif ($customer instanceof CustomerData) {
            return $this->getCustomerDataAddress($customer);
        }

        return null;
    }

    /**
     * @param $customer
     * @return mixed|null
     */
    protected function getCustomerDataAddress($customer): ?array
    {
        $address = null;
        $defaultBillingId = $customer->getDefaultBilling();
        foreach ($customer->getAddresses() as $customerAddress) {
            if ($customerAddress->getId() === $defaultBillingId) {
                $address = $customerAddress->__toArray();
                break;
            }
        }

        if (!$address && count($customer->getAddresses()) > 0) {
            $address = current($customer->getAddresses())->__toArray();
        }

        return $address;
    }
}
