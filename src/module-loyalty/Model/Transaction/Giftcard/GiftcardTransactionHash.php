<?php
/**
 * TransactionHash
 *
 * @copyright Copyright © 2025 Bold. All rights reserved.
 * @author    luuk@boldcommerce.nl
 */
declare(strict_types=1);

namespace Leat\Loyalty\Model\Transaction\Giftcard;

use Leat\Loyalty\Helper\GiftcardHelper;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\ResourceModel\Loyalty\GiftcardResource;
use Leat\LoyaltyAsync\Model\Queue\Type\Giftcard\GiftcardTransaction;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Store\Model\StoreManagerInterface;
use Piggy\Api\Exceptions\PiggyRequestException;

class GiftcardTransactionHash extends GiftcardProgramTransactionHash
{
    /**
     * @var mixed|null
     */
    protected string $giftcardUUID;

    public function __construct(
        Connector $connector,
        StoreManagerInterface $storeManager,
        Config $config,
        protected GiftcardResource $giftcardResource,
        protected GiftcardHelper $giftcardHelper,
    ) {
        parent::__construct($connector, $storeManager, $config);
    }

    /**
     * @param array $data
     * @param callable|null $callback
     * @return array
     * @throws AuthenticationException
     * @throws PiggyRequestException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getTransactions(array $data = [], callable $callback = null): array
    {
        $giftcardUuid = $data[GiftcardTransaction::DATA_GIFTCARD_UUID_KEY] ?? null;
        $giftcardHash = $data[GiftcardTransaction::DATA_GIFTCARD_HASH_KEY] ?? null;
        if (!$giftcardUuid && $giftcardHash) {
            $data[GiftcardTransaction::DATA_GIFTCARD_UUID_KEY] =
                $this->giftcardHelper->getGiftcardUUIDByHash($giftcardHash);
        }

        if (!isset($data[GiftcardTransaction::DATA_GIFTCARD_UUID_KEY])) {
            return [];
        }

        $this->giftcardUUID = $data[GiftcardTransaction::DATA_GIFTCARD_UUID_KEY];

        return parent::getTransactions($data, static::getTransactionFilter($this->giftcardUUID));
    }
}
