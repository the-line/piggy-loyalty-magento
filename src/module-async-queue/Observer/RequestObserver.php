<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Observer;

use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManager;

abstract class RequestObserver implements ObserverInterface
{
    protected array $eventHistory = [];

    public function __construct(
        protected StoreManager                $storeManager,
        protected CustomerRepositoryInterface $customerRepository,
        protected RequestTypePool             $requestTypePool,
    ) {
    }

    /**
     * Upon execution of event, ensure Job creation gets handled securely.
     *  Changing customer details from the admin returns the wrong store_id, in this case, they will be retrieved
     *  from the customer specifically.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if ($this->debounceEvent($observer)) {
            return;
        }

        if (!$this->validateEvent($observer)) {
            return;
        }

        $this->addJob($observer);
    }

    /**
     * @param Observer $observer
     * @return void
     */
    abstract public function addJob(Observer $observer): void;

    /**
     * Get store id from the observer (if available) as store id's retrieved from the admin are not always accurate.
     *
     * @param Observer $observer
     * @return int
     */
    abstract public function getStoreId(Observer $observer): int;

    /**
     * Get Relation id from the observer as this is necessary for the debouncing process.
     *
     * @param Observer $observer
     * @return int
     */
    abstract public function getRelationId(Observer $observer): int;

    /**
     * Check if the event is valid for processing.
     *
     * @param Observer $observer
     * @return bool
     */
    protected function validateEvent(Observer $observer): bool
    {
        return true;
    }

    /**
     * Prevent event data being converted into a job more than once.
     *
     * @param Observer $observer
     * @return bool
     */
    private function debounceEvent(Observer $observer): bool
    {
        $relationId = $this->getRelationId($observer);
        $observerName = $observer->getEvent()->getName();
        if (!isset($this->eventHistory[$relationId][$observerName])) {
            $this->eventHistory[$relationId][$observerName] = true;
            return false;
        }

        return true;
    }
}
