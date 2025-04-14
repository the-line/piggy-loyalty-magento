<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Observer\Customer;

use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\LoyaltyAsync\Model\Queue\Builder\LoyaltyJobBuilder;
use Leat\LoyaltyAsync\Model\Queue\Builder\Service\ContactBuilder;
use Leat\LoyaltyAsync\Observer\ContactRequestObserver;
use Leat\LoyaltyFrontend\Model\FrontendConfig;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\Observer;
use Magento\Store\Model\StoreManager;

class UpdateCustomer extends ContactRequestObserver
{
    public function __construct(
        LoyaltyJobBuilder                 $jobBuilder,
        FrontendConfig                  $config,
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
     * Observer for customer_account_edited
     * - Upon editing account, insert customer_update request into the queue.
     * @param Observer $observer
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function addJob(Observer $observer): void
    {
        $customer = $this->getCustomer($observer);
        $this->contactBuilder->addUpdateContactJob($customer);
    }

    /**
     * @inheritdoc
     */
    public function getStoreId(Observer $observer): int
    {
        $customer = $this->getCustomer($observer);

        return (int) $customer->getStoreId();
    }

    /**
     * @inheritdoc
     */
    public function getRelationId(Observer $observer): int
    {
        $customer = $this->getCustomer($observer);

        return (int) $customer->getId();
    }

    /**
     * @param Observer $observer
     * @return CustomerInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getCustomer(Observer $observer): CustomerInterface
    {
        $event = $observer->getEvent();
        $customerEmail = $event->getEmail();

        return $this->customerRepository->get($customerEmail);
    }
}
