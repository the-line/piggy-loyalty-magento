<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Observer\Customer;

use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\LoyaltyAsync\Model\Queue\Builder\LoyaltyJobBuilder;
use Leat\LoyaltyAsync\Model\Queue\Builder\Service\ContactBuilder;
use Leat\LoyaltyAsync\Observer\ContactRequestObserver;
use Leat\Loyalty\Model\Config;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Store\Model\StoreManager;

class UpdateCustomerAddress extends ContactRequestObserver
{
    public function __construct(
        LoyaltyJobBuilder               $jobBuilder,
        Config                          $config,
        StoreManager                    $storeManager,
        CustomerRepositoryInterface     $customerRepository,
        Connector                       $leatConnector,
        RequestTypePool                 $leatRequestTypePool,
        ContactResource                 $contactResource,
        protected ContactBuilder        $contactBuilder
    ) {
        parent::__construct(
            $jobBuilder,
            $config,
            $storeManager,
            $customerRepository,
            $leatConnector,
            $leatRequestTypePool,
            $contactResource
        );
    }

    /**
     * Observer for customer_address_save_commit_after
     *  - Upon updating of customer default shipping address insert customer_update request into the queue.
     *
     * @param Observer $observer
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function addJob(Observer $observer): void
    {
        $customerId = $observer->getCustomerAddress()->getCustomerId();
        $customer = $this->customerRepository->getById($customerId);

        $this->contactBuilder->updateContactAddress($customer);
    }

    /**
     * @inheritdoc
     */
    public function getStoreId(Observer $observer): int
    {
        $customerId = $observer->getCustomerAddress()->getCustomerId();
        $customer = $this->customerRepository->getById($customerId);

        return (int) $customer->getStoreId();
    }

    /**
     * @inheritdoc
     */
    public function getRelationId(Observer $observer): int
    {
        return (int) $observer->getCustomerAddress()->getCustomerId();
    }
}
