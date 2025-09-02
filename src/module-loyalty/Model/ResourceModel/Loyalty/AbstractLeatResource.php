<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\ResourceModel\Loyalty;

use Leat\Loyalty\Model\Logger;
use Leat\Loyalty\Model\Client;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Setup\Patch\Data\AddContactUuidCustomerAttribute;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\SessionFactory;
use Magento\Framework\App\Cache\Type\Config as CacheConfig;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Piggy\Api\Models\Contacts\Contact;

abstract class AbstractLeatResource
{
    /**
     * Define the purpose for logging in child classes
     */
    protected const string LOGGER_PURPOSE = 'leat_resource';

    /**
     * Default cache lifetime (1 week in seconds)
     */
    protected const int WEEK_IN_SECONDS = 604800;

    /**
     * @var Logger|null
     */
    protected ?Logger $logger = null;

    /**
     * @var Session $customerSession
     */
    protected Session $customerSession;

    /**
     * Current customer ID from session
     * @var int|null
     */
    protected ?int $currentCustomerId = null;

    public function __construct(
        protected Config $config,
        protected Connector $connector,
        protected StoreManagerInterface $storeManager,
        protected CacheInterface $cache,
        protected Json $serializer,
        protected CustomerRepositoryInterface $customerRepository,
        protected SessionFactory $sessionFactory,
    ) {
    }

    /**
     * Get the Leat API client for the specified store
     *
     * @param int|null $storeId
     * @return Client
     * @throws \Magento\Framework\Exception\AuthenticationException
     */
    public function getClient(?int $storeId = null): Client
    {
        return $this->connector->getConnection($storeId);
    }

    /**
     * Get logger for this resource
     *
     * @param string|null $purpose
     * @return Logger
     */
    public function getLogger(string $purpose = null): Logger
    {
        return $this->connector->getLogger($purpose ?? static::LOGGER_PURPOSE);
    }

    /**
     * Get the contact UUID for the customer
     *
     * @param int|null $customerId
     * @return string|null
     */
    public function getContactUuid(?int $customerId = null): ?string
    {
        $customer = $this->getCustomer($customerId);
        return $customer?->getCustomAttribute(AddContactUuidCustomerAttribute::ATTRIBUTE_CODE)?->getValue();
    }

    /**
     * Get contact by customer ID
     *
     * @param int $customerId
     * @param int|null $storeId
     * @return Contact
     * @throws LocalizedException
     */
    public function getContact(int $customerId, ?int $storeId = null): Contact
    {
        return $this->executeApiRequest(
            function () use ($customerId, $storeId) {
                $contactUuid = $this->getContactUuid($customerId);
                $client = $this->getClient($storeId);

                return $client->contacts->get($contactUuid);
            },
            'Error retrieving contact'
        );
    }

    /**
     * Get the Leat contact Object for the customer
     * - If the customer does not have a contact, return null.
     *
     * @param int|null $customerId
     * @param int|null $storeId
     * @return Contact|null
     */
    public function getCustomerContact(?int $customerId = null, ?int $storeId = null): ?Contact
    {
        try {
            $customer = $this->getCustomer($customerId);
            if (!$customer) {
                return null;
            }

            $contactUuid = $this->getContactUuid((int) $customer->getId());
            $storeId = $storeId ?? (int) $customer->getStoreId();

            return $this->getClient($storeId)->contacts->get($contactUuid);
        } catch (\Throwable $exception) {
            $this->getLogger()->debug(
                $exception->getMessage() . PHP_EOL . $exception->getTraceAsString()
            );
            return null;
        }
    }

    /**
     * Get the customer by the customer ID
     *
     * @param int|null $customerId
     * @return CustomerInterface|null
     */
    public function getCustomer(?int $customerId = null): ?CustomerInterface
    {
        try {
            if (empty($customerId)) {
                if (!isset($this->customerSession)) {
                    $this->customerSession = $this->sessionFactory->create();
                }

                if (!$this->currentCustomerId) {
                    $this->currentCustomerId = (int)$this->customerSession->getCustomerId();
                }

                $customerId = $this->currentCustomerId;
            }

            return $this->customerRepository->getById($customerId);
        } catch (NoSuchEntityException $exception) {
            return null;
        } catch (LocalizedException $exception) {
            $this->getLogger()->debug(
                $exception->getMessage() . PHP_EOL . $exception->getTraceAsString()
            );
            return null;
        }
    }

    /**
     * Check if the customer has a contact UUID
     *
     * @param int|null $customerId
     * @return bool
     */
    public function hasContactUuid(?int $customerId = null): bool
    {
        try {
            return (bool) $this->getContactUuid($customerId);
        } catch (\throwable $exception) {
            return false;
        }
    }

    /**
     * Get shop UUID for a store
     *
     * @param int|null $storeId
     * @return string|null
     */
    protected function getShopUuid(?int $storeId = null): ?string
    {
        return $this->config->getShopUuid($storeId);
    }

    /**
     * Execute API request with error handling
     *
     * @param callable $apiCall
     * @param string $errorMessage
     * @return mixed
     * @throws LocalizedException
     */
    protected function executeApiRequest(callable $apiCall, string $errorMessage): mixed
    {
        try {
            return $apiCall();
        } catch (\Throwable $e) {
            $this->getLogger()->log($e->getMessage());
            throw new LocalizedException(__($errorMessage . ': %1', $e->getMessage()));
        }
    }

    /**
     * Save data to the cache
     * - Data is saved for 1 week by default
     *
     * @param array $data
     * @param string|null $key
     * @param int $lifeTime
     * @param array $tags
     * @return void
     * @throws NoSuchEntityException
     */
    protected function saveToCache(
        array $data,
        string $identifier,
        int $lifeTime = self::WEEK_IN_SECONDS,
        array $tags = []
    ): void {
        $tags[] = CacheConfig::TYPE_IDENTIFIER;
        $serializedData = $this->serializer->serialize($data);
        $this->cache->save($serializedData, $identifier, $tags, $lifeTime);
    }

    /**
     * Load data from the cache
     *
     * @param string $key
     * @return array
     */
    protected function loadCache(string $key): array
    {
        $loadedData = $this->cache->load($key);
        return $loadedData ? $this->serializer->unserialize($loadedData) : [];
    }

    /**
     * Get cache identifier - to be implemented by child classes
     *
     * @param string $purpose
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getCacheIdentifier(string $purpose): string
    {
        return sprintf(
            'leat_%s%s%s',
            static::LOGGER_PURPOSE,
            "_{$purpose}_",
            (string) $this->storeManager->getStore()->getId()
        );
    }

    /**
     * Clear cache for a specific key
     *
     * @param string $key
     * @return void
     */
    protected function clearCache(string $key): void
    {
        $this->cache->remove($key);
    }
}
