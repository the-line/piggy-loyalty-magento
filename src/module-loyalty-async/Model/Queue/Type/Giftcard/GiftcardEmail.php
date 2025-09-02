<?php
/**
 * GiftcardCreate
 *
 * @copyright Copyright © 2025 Bold. All rights reserved.
 * @author    luuk@boldcommerce.nl
 */
declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Type\Giftcard;

use Leat\AsyncQueue\Model\Connector\ConnectorPool;
use Leat\AsyncQueue\Service\JobDigest;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\CustomerContactLink;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\Loyalty\Model\ResourceModel\Loyalty\GiftcardResource;
use Leat\LoyaltyAsync\Model\Connector\AsyncConnector;
use Leat\LoyaltyAsync\Model\Queue\Type\LeatGenericType;
use Magento\Store\Model\StoreManagerInterface;

class GiftcardEmail extends LeatGenericType
{
    protected const string TYPE_CODE = 'giftcard_email';

    public const string DATA_GIFTCARD_RECIPIENT_EMAIL = 'giftcard_recipient_email';
    public const string DATA_GIFTCARD_EMAIL_UUID = 'giftcard_email_uuid';
    public const string DATA_GIFTCARD_MERGE_TAGS = 'giftcard_merge_tags';

    public function __construct(
        protected GiftcardResource $giftcardResource,
        Config $config,
        ContactResource $contactResource,
        ConnectorPool $connectorPool,
        StoreManagerInterface $storeManager,
        AsyncConnector $connector,
        array $data = []
    ) {
        parent::__construct(
            $config,
            $contactResource,
            $connectorPool,
            $storeManager,
            $connector,
            $data
        );
    }

    /**
     * @inheritDoc
     */
    protected function execute(): mixed
    {
        $storeId = (int)$this->getStoreId();
        $giftcardUUID = $this->getData(JobDigest::PREVIOUS_RESULT);
        if (!$giftcardUUID) {
            throw new \InvalidArgumentException('No giftcard UUID supplied by previous request');
        }

        $contactUUID = $this->contactResource->createOrFindContact(
            $this->getData(self::DATA_GIFTCARD_RECIPIENT_EMAIL),
            storeId: $storeId
        )->getUuid();
        if (!$contactUUID) {
            throw new \InvalidArgumentException('Contact UUID not supplied');
        }

        $resultGiftcard = $this->giftcardResource->sendGiftcardEmail(
            $giftcardUUID,
            $contactUUID,
            $this->getData(self::DATA_GIFTCARD_EMAIL_UUID),
            $this->getData(self::DATA_GIFTCARD_MERGE_TAGS),
            $storeId
        );
        if (!$resultGiftcard) {
            throw new \RuntimeException('Error sending giftcard email');
        }

        return $resultGiftcard;
    }
}
