<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Observer;

use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\AsyncQueue\Observer\RequestObserver;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\LoyaltyAsync\Model\Queue\Builder\LoyaltyJobBuilder;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManager;

abstract class ContactRequestObserver extends RequestObserver
{
    protected array $eventHistory = [];

    public function __construct(
        protected LoyaltyJobBuilder             $jobBuilder,
        protected Config                      $config,
        protected StoreManager                $storeManager,
        protected CustomerRepositoryInterface $customerRepository,
        protected Connector                   $leatConnector,
        protected RequestTypePool             $requestTypePool,
        protected ContactResource             $contactResource
    ) {
        parent::__construct(
            $storeManager,
            $customerRepository,
            $requestTypePool,
        );
    }

    /**
     * Ensure Job creation gets handled with logging.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            parent::execute($observer);
        } catch (\Magento\Framework\Exception\AuthenticationException $e) {
            // Handle authentication errors (e.g., API connection issues)
            $this->leatConnector->getLogger()->log(sprintf(
                "Authentication error in RequestObserver: %s \n %s",
                $e->getMessage(),
                $e->getTraceAsString()
            ));
        } catch (\Leat\Loyalty\Exception\NoContactException $e) {
            // Handle missing contact errors
            $this->leatConnector->getLogger()->log(sprintf(
                "Contact error in RequestObserver: %s",
                $e->getMessage()
            ));
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Handle Magento-specific exceptions
            $this->leatConnector->getLogger()->log(sprintf(
                "Magento exception in RequestObserver: %s \n %s",
                $e->getMessage(),
                $e->getTraceAsString()
            ));
        } catch (\Throwable $e) {
            // Handle any other unexpected errors
            $this->leatConnector->getLogger()->log(sprintf(
                "Unexpected error in RequestObserver: %s \n %s",
                $e->getMessage(),
                $e->getTraceAsString()
            ));
        }
    }

    /**
     * @param Observer $observer
     * @param bool $requireUUID
     * @return bool
     * @throws LocalizedException
     */
    protected function validateEvent(Observer $observer, bool $requireUUID = true): bool
    {
        if (!($customerId = (int) $this->getRelationId($observer))) {
            return false;
        }

        $customer = $this->contactResource->getCustomer($customerId);
        if (!in_array((int) $customer?->getGroupId() ?? 0, $this->config->getCustomerGroupMapping())) {
            return false;
        }

        if ($requireUUID && !$this->contactResource->hasContactUuid($this->getRelationId($observer))) {
            return false;
        }

        return true;
    }
}
