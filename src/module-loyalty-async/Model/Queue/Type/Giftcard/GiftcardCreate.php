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
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\Loyalty\Model\ResourceModel\Loyalty\GiftcardResource;
use Leat\LoyaltyAsync\Model\Connector\AsyncConnector;
use Leat\LoyaltyAsync\Model\Queue\Type\LeatGenericType;
use Magento\Store\Model\StoreManagerInterface;

class GiftcardCreate extends LeatGenericType
{
    protected const string TYPE_CODE = 'giftcard_create';

    public const string DATA_GIFTCARD_PROGRAM_UUID = 'giftcard_program_uuid';
    public const string DATA_GIFTCARD_TYPE = 'giftcard_type';

    public const int  GIFTCARD_TYPE_DIGITAL = 1;
    public const int GIFTCARD_TYPE_PHYSICAL = 0;

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
        $request = $this->getRequest();
        $storeId = (int)$this->getStoreId();
        $giftcardProgramId = (string) ($this->getData(self::DATA_GIFTCARD_PROGRAM_UUID)
            ?? $this->config->getGiftcardProgramUUID($storeId));
        if (!$giftcardProgramId) {
            throw new \InvalidArgumentException('Giftcard program UUID is not set');
        }

        $resultGiftcard = $this->giftcardResource->createGiftcard(
            $giftcardProgramId,
            $this->getData(self::DATA_GIFTCARD_TYPE),
            $storeId
        );
        if (!$resultGiftcard) {
            throw new \RuntimeException('Error creating giftcard');
        }

        $request->setResult($resultGiftcard->getUuid());
        return $resultGiftcard;
    }
}
