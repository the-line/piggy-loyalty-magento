<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\ResourceModel\Loyalty;

use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Setup\Patch\Data\AddContactUuidCustomerAttribute;
use Leat\LoyaltyAsync\Model\ResourceModel\JobQueries;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\SessionFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Piggy\Api\Mappers\Contacts\ContactMapper;
use Piggy\Api\Models\Contacts\Contact;

class ContactResource extends AbstractLeatResource
{
    protected const string LOGGER_PURPOSE = 'contact';

    public function __construct(
        protected JobQueries $jobQueries,
        Config $config,
        Connector $connector,
        StoreManagerInterface $storeManager,
        CacheInterface $cache,
        Json $serializer,
        CustomerRepositoryInterface $customerRepository,
        SessionFactory $sessionFactory
    ) {
        parent::__construct(
            $config,
            $connector,
            $storeManager,
            $cache,
            $serializer,
            $customerRepository,
            $sessionFactory
        );
    }

    /**
     * Manually call the api because the SDK has no support for referral code
     *
     * @param string $email
     * @param string $referralCode
     * @param int|null $storeId
     * @return Contact
     * @throws LocalizedException
     */
    public function submitReferredContactCreation(string $email, string $referralCode, ?int $storeId = null): Contact
    {
        return $this->executeApiRequest(
            function () use ($email, $referralCode, $storeId) {
                $client = $this->getClient($storeId);
                $response = $client->post(
                    '/api/v3/oauth/clients/contacts',
                    ['email' => $email, 'referral_code' => $referralCode]
                );
                $mapper = new ContactMapper();

                return $mapper->map($response->getData());
            },
            'Error creating contact'
        );
    }


    /**
     * Create or find a contact for the given email address and save to customer
     *
     * @param string $email
     * @param int|null $customerId
     * @param int|null $storeId
     * @return Contact
     * @throws LocalizedException
     */
    public function createOrFindContact(string $email, int $customerId = null, ?int $storeId = null): Contact
    {
        return $this->executeApiRequest(
            function () use ($email, $customerId, $storeId) {
                $client = $this->getClient($storeId);
                $contact = $client->contacts->findOrCreate($email);

                // Save contact UUID to customer
                $customer = $this->getCustomer($customerId);
                if ($customer) {
                    $customer->setCustomAttribute(AddContactUuidCustomerAttribute::ATTRIBUTE_CODE, $contact->getUuid());
                    $this->customerRepository->save($customer);
                }

                return $contact;
            },
            'Error creating or finding contact'
        );
    }

    /**
     * Update contact information
     *
     * @param int $customerId
     * @param array $data
     * @param int|null $storeId
     * @return Contact
     * @throws LocalizedException
     */
    public function updateContact(int $customerId, array $data, ?int $storeId = null): Contact
    {
        return $this->executeApiRequest(
            function () use ($customerId, $data, $storeId) {
                $contactUuid = $this->getContactUuid($customerId);
                $client = $this->getClient($storeId);

                return $client->contacts->update($contactUuid, $data);
            },
            'Error updating contact'
        );
    }

    /**
     * Check if the customer does not already have a pending creation request
     *
     * @param int $customerId
     * @return bool
     */
    public function hasCreateJob(int $customerId): bool
    {
        return $this->jobQueries->hasCreateJob($customerId);
    }
}
