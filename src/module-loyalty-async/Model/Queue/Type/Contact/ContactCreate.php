<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Type\Contact;

use Leat\AsyncQueue\Model\Connector\ConnectorPool;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\CustomerContactLink;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\LoyaltyAsync\Model\Queue\Type\ContactType;
use Leat\LoyaltyAsync\Model\Queue\Type\LeatGenericType;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Piggy\Api\Models\Contacts\Contact;

class ContactCreate extends LeatGenericType
{
    protected const string TYPE_CODE = 'contact_create';

    /**
     * Create a new contact, or retrieve the contact UUID for a given email and save this into
     * the customer account.
     *
     * @return Contact
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function execute(): Contact
    {
        $job = $this->getJob();
        $customerId = (int) $job->getRelationId();
        $email = $this->getData('email');
        $storeId = (int)$this->getStoreId();

        return $this->contactResource->createOrFindContact($email, $customerId, $storeId);
    }
}
