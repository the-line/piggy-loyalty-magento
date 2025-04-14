<?php


declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Type;

use Leat\AsyncQueue\Model\Connector\ConnectorPool;
use Leat\AsyncQueue\Model\Queue\Request\GenericType;
use Leat\Loyalty\Model\Logger;
use Leat\Loyalty\Model\Client;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\LoyaltyAsync\Model\Connector\AsyncConnector;
use Exception;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

abstract class LeatGenericType extends GenericType
{
    protected const string CONNECTOR_CODE = 'leat_connector';

    protected const string DATA_CONTACT_UUID = 'contact_uuid';
    protected const string DATA_CUSTOMER_ID = 'customer_id';
    protected const LOGGER_PURPOSE = 'generic_async';

    /**
     * @var DataObject
     */
    protected DataObject $data;

    /**
     * @var Logger|null
     */
    protected ?Logger $logger = null;

    public function __construct(
        protected Config $config,
        protected ContactResource $contactResource,
        protected ConnectorPool $connectorPool,
        protected StoreManagerInterface $storeManager,
        protected AsyncConnector $connector,
        array $data = [],
    ) {
        parent::__construct($connectorPool, $storeManager, $data);
    }

    /**
     * @return Client
     * @throws \Magento\Framework\Exception\AuthenticationException
     * @throws Exception
     */
    public function getClient(): Client
    {
        return $this->connector->getConnection($this->getStoreId());
    }

    /**
     * Return the logger object
     * - Override LOGGER_PURPOSE const in the child class to set the purpose (file name) of the logger
     *
     * @param string|null $purpose
     * @return Logger
     * @throws Exception
     */
    protected function getLogger(string $purpose = null): Logger
    {
        return $this->getConnector()->getLogger(($purpose ?? static::LOGGER_PURPOSE) ?? null);
    }

    /**
     * Retrieve the contact UUID for the job
     * - The contact UUID can be directly set on the request to be able to execute it without making use of
     *   a job.
     *
     * @return string
     * @throws LocalizedException
     */
    protected function getContactUUID(): string
    {
        $jobCustomerId = $this->getJob()?->getRelationid();
        $requestCustomerId = $this->getData(self::DATA_CUSTOMER_ID);
        $requestContactUUID = $this->getData(self::DATA_CONTACT_UUID);

        if ($requestContactUUID) {
            return $requestContactUUID;
        }

        if ($jobCustomerId || $requestCustomerId) {
            return $this->contactResource->getContactUuid($jobCustomerId ?? $requestCustomerId);
        }

        throw new LocalizedException(__('No contact UUID found'));
    }
}
