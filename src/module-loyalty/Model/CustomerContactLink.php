<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model;

use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Piggy\Api\Models\Contacts\Contact;

class CustomerContactLink
{
    /**
     * @param ContactResource $contactResource
     * @param Config $config
     */
    public function __construct(
        private ContactResource $contactResource,
        private Config $config
    ) {
    }


    /**
     * Check if the customer has a contact UUID
     *
     * @param $customerId
     * @return bool
     */
    public function hasContactUuid($customerId = null): bool
    {
        if ($customerId !== null) {
            $customerId = (int) $customerId;
        }
        return $this->contactResource->hasContactUuid($customerId);
    }

    /**
     * Get the contact UUID for the customer
     *
     * @param $customerId
     * @return string|null
     * @throws LocalizedException
     */
    public function getContactUuid($customerId = null): ?string
    {
        if ($customerId !== null) {
            $customerId = (int) $customerId;
        }
        return $this->contactResource->getContactUuid($customerId);
    }

    /**
     * Get the customer by the customer ID
     *
     * @param $customerId
     * @return CustomerInterface|null
     */
    public function getCustomer($customerId = null): ?CustomerInterface
    {
        if ($customerId !== null) {
            $customerId = (int) $customerId;
        }
        return $this->contactResource->getCustomer($customerId);
    }

    /**
     * Get the Leat contact Object for the customer
     * - If the customer does not have a contact, return null.
     *
     * @param $customerId
     * @return Contact|null
     */
    public function getCustomerContact($customerId = null): ?Contact
    {
        if ($customerId !== null) {
            $customerId = (int) $customerId;
        }
        return $this->contactResource->getCustomerContact($customerId);
    }

    /**
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Check if the customer does not already have a pending creation request
     *
     * @param $customerId
     * @return bool
     */
    public function hasCreateJob($customerId): bool
    {
        if ($customerId !== null) {
            $customerId = (int) $customerId;
        }
        return $this->contactResource->hasCreateJob($customerId);
    }
}
