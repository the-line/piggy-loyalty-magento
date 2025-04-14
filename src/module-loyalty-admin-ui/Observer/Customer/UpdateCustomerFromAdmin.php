<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Observer\Customer;

use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\AsyncQueue\Service\JobDigest;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\LoyaltyAsync\Model\Queue\Builder\LoyaltyJobBuilder;
use Leat\LoyaltyAsync\Model\Queue\Builder\Service\ContactBuilder;
use Leat\LoyaltyFrontend\Model\FrontendConfig;
use Leat\LoyaltyFrontend\Observer\Customer\UpdateCustomer;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Store\Model\StoreManager;

class UpdateCustomerFromAdmin extends UpdateCustomer
{
    public function __construct(
        protected JobDigest         $jobDigest,
        LoyaltyJobBuilder           $jobBuilder,
        FrontendConfig              $config,
        StoreManager                $storeManager,
        CustomerRepositoryInterface $customerRepository,
        Connector                   $leatConnector,
        RequestTypePool             $leatRequestTypePool,
        ContactResource             $contactResource,
        ContactBuilder              $contactBuilder
    ) {
        parent::__construct(
            $jobBuilder,
            $config,
            $storeManager,
            $customerRepository,
            $leatConnector,
            $leatRequestTypePool,
            $contactResource,
            $contactBuilder
        );
    }

    /**
     * Observer for adminhtml_customer_save_after
     * - Upon creation of job, immediately try to sync with Leat
     *
     * @param Observer $observer
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addJob(Observer $observer): void
    {
        $customer = $observer->getCustomer();

        if ($job = $this->contactBuilder->addUpdateContactJob($customer)) {
            $this->jobDigest->setJob($job)->execute();
        }
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
    public function getCustomerId(Observer $observer): int
    {
        return (int) $observer->getCustomer()->getId();
    }
}
