<?php
declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Type\Giftcard;

use Leat\AsyncQueue\Model\Connector\ConnectorPool;
use Leat\Loyalty\Api\AppliedGiftCardRepositoryInterface;
use Leat\Loyalty\Helper\GiftcardHelper;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\Loyalty\Model\ResourceModel\Loyalty\GiftcardResource;
use Leat\Loyalty\Model\Transaction\Giftcard\GiftcardTransactionHash;
use Leat\Loyalty\Model\Transaction\LoyaltyTransactionHash;
use Leat\Loyalty\Model\Transaction\LoyaltyTransactionOrderItems;
use Leat\LoyaltyAsync\Model\Connector\AsyncConnector;
use Magento\Store\Model\StoreManagerInterface;

class GiftcardRedeem extends GiftcardTransaction
{
    public const string DATA_GIFTCARD_MAGENTO_ID = 'giftcard_magento_id';
    public const string DATA_GIFTCARD_IS_REFUND = 'giftcard_is_refund';

    protected const string TYPE_CODE = 'giftcard_redeem';
    public function __construct(
        protected GiftcardResource $giftcardResource,
        protected GiftcardHelper $giftcardHelper,
        protected GiftcardTransactionHash $giftcardTransactionHash,
        protected AppliedGiftCardRepositoryInterface $appliedGiftCardRepository,
        LoyaltyTransactionOrderItems $orderItems,
        LoyaltyTransactionHash $transactionHash,
        Config $config,
        ContactResource $contactResource,
        ConnectorPool $connectorPool,
        StoreManagerInterface $storeManager,
        AsyncConnector $connector,
        array $data = []
    ) {
        parent::__construct(
            $giftcardResource,
            $giftcardHelper,
            $giftcardTransactionHash,
            $orderItems,
            $transactionHash,
            $config,
            $contactResource,
            $connectorPool,
            $storeManager,
            $connector,
            $data
        );
    }

    protected function execute(): mixed
    {
        $result = null;
        try {
            $result = $this->giftcardResource->createGiftcardTransaction(
                $this->getData(self::DATA_GIFTCARD_UUID_KEY),
                $this->getData(self::DATA_AMOUNT_KEY),
                [
                    self::INTERNAL_INCREMENT_ID_NAME => $this->getData(self::DATA_INCREMENT_ID_KEY)
                ],
                $this->getData($this->getStoreId())
            );
        } catch (\Exception $e) {
            $this->getLogger('giftcard-transaction')->log(sprintf(
                'Error creating giftcard transaction | %s | %s',
                $e->getMessage(),
                $e->getTraceAsString()
            ));
            if (str_contains($e->getMessage(), self::GIFTCARD_IS_NOT_UPGRADEABLE_ERROR)) {
                $this->getRequest()->setLatestFailReason($e->getMessage());
            } else {
                throw new \RuntimeException('Error creating giftcard transaction', 0, $e);
            }
        }

        try {
            $card = $this->appliedGiftCardRepository->getById((int) $this->getData(self::DATA_GIFTCARD_MAGENTO_ID));

            if ($this->getData(self::DATA_GIFTCARD_IS_REFUND)) {
                $card->setBaseRefundedAmount($card->getBaseRefundedAmount() + ($this->getData(self::DATA_AMOUNT_KEY) / 100));
                $card->setRefundedAmount($card->getRefundedAmount() + ($this->getData(self::DATA_AMOUNT_KEY) / 100));
            } else {
                $card->setLeatTransactionUuid($result->getUuid());
            }

            $this->appliedGiftCardRepository->save($card);
        } catch (\Exception $e) {
            $this->getLogger('giftcard-transaction')->log(sprintf(
                'Failed to update applied gift card after successful transaction | %s | %s',
                $e->getMessage(),
                $e->getTraceAsString()
            ));
        }

        return $result;
    }
}
