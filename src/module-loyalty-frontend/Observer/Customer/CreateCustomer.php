<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Observer\Customer;

use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\AsyncQueue\Service\JobDigest;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\LoyaltyAsync\Model\Queue\Builder\LoyaltyJobBuilder;
use Leat\LoyaltyAsync\Model\Queue\Builder\Service\ContactBuilder;
use Leat\LoyaltyAsync\Observer\ContactRequestObserver;
use Leat\Loyalty\Model\Config;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Event\Observer;
use Magento\Store\Model\StoreManager;

class CreateCustomer extends ContactRequestObserver
{
    public function __construct(
        LoyaltyJobBuilder                 $jobBuilder,
        Config                          $config,
        StoreManager                    $storeManager,
        CustomerRepositoryInterface     $customerRepository,
        Connector                       $leatConnector,
        RequestTypePool                 $leatRequestTypePool,
        ContactResource                 $contactResource,
        protected JobDigest             $jobDigest,
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
     * When validating, require the customer to have a contact UUID.
     */
    protected function validateEvent(Observer $observer, bool $requireUUID = true): bool
    {
        return parent::validateEvent($observer, false);
    }

    /**
     * Observer for customer_register_success
     *
     * @param Observer $observer
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addJob(Observer $observer): void
    {
        /** @var Customer $customer */
        $customer = $observer->getCustomer();

        $this->jobDigest->setJob(
            $this->contactBuilder->addNewContact($customer)->setSkipValidation(true)
        )->execute();
    }

    /**
     * @inheritdoc
     */
    public function getStoreId(Observer $observer): int
    {
        return (int) $observer->getCustomer()->getStoreId();
    }

    /**
     * @inheritdoc
     */
    public function getRelationId(Observer $observer): int
    {
        return (int) $observer->getCustomer()->getId();
    }
}
