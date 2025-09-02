<?php
declare(strict_types=1);

namespace Leat\Loyalty\Model\GiftCard;

use Leat\AsyncQueue\Service\JobDigest;
use Leat\Loyalty\Api\Data\AppliedGiftCardInterface;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\Logger;
use Leat\Loyalty\Model\Order\LeatBalanceRepository;
use Leat\LoyaltyAsync\Model\Queue\Builder\LoyaltyJobBuilder;
use Leat\LoyaltyAsync\Model\Queue\Builder\Service\GiftcardPurchaseBuilder;
use Leat\LoyaltyAsync\Model\Queue\Type\Giftcard\GiftcardRedeem;
use Leat\LoyaltyAsync\Model\Queue\Type\Giftcard\GiftcardTransaction;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;

class TransactionService
{
    private ?Logger $logger = null;

    /**
     * @param Connector $leatConnector
     * @param JobDigest $jobDigest
     * @param GiftcardPurchaseBuilder $giftcardPurchaseBuilder
     * @param LoyaltyJobBuilder $jobBuilder
     */
    public function __construct(
        protected Connector $leatConnector,
        protected JobDigest             $jobDigest,
        protected GiftcardPurchaseBuilder $giftcardPurchaseBuilder,
        protected LoyaltyJobBuilder $jobBuilder,
    ) {
    }


    /**
     * @param OrderInterface $order
     * @param AppliedGiftCardInterface[] $appliedGiftCards
     * @return void
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    public function createGiftcardTransaction(OrderInterface $order, array $appliedGiftCards, bool $refund = false)
    {
        $this->logger = $this->leatConnector->getLogger(ApplicationService::LOGGER_PURPOSE);

        $this->jobBuilder
            ->newJob((string) $order->getCustomerId())
            ->setStoreId((int) $order->getStoreId());

        foreach ($appliedGiftCards as $appliedGiftCard) {
            $giftcardAmount = 100 * $appliedGiftCard->getBaseAppliedAmount();

            if (!$refund) {
                $giftcardAmount *= -1;
            }

            $this->logger->log(
                'Creating giftcard transaction' . ($refund ? ' for refund action' : ' for redeem action'),
                true,
                [
                    'order_id' => $order->getIncrementId(),
                    'amount' => $giftcardAmount,
                    'giftcard_uuid' => $appliedGiftCard->getLeatGiftcardUuid()
                ]
            );

            $this->jobBuilder->addRequest(
                [
                    GiftcardTransaction::DATA_GIFTCARD_UUID_KEY => $appliedGiftCard->getLeatGiftcardUuid(),
                    GiftcardTransaction::DATA_AMOUNT_KEY => $giftcardAmount,
                    GiftcardTransaction::DATA_INCREMENT_ID_KEY => $order->getIncrementId(),
                    GiftcardRedeem::DATA_GIFTCARD_MAGENTO_ID => $appliedGiftCard->getId(),
                    GiftcardRedeem::DATA_GIFTCARD_IS_REFUND => $refund
                ],
                GiftcardRedeem::getTypeCode()
            );
        }

        $job = $this->jobBuilder->create(false);

        $this->jobDigest->setJob($job)->execute();
    }

    /**
     * @param OrderInterface $order
     * @param AppliedGiftCardInterface[] $appliedGiftCards
     * @param float $refundAmount
     * @return bool
     */
    public function refundGiftcardTransaction(OrderInterface $order, array $appliedGiftCards, float $refundAmount): bool
    {
        $this->logger = $this->leatConnector->getLogger(ApplicationService::LOGGER_PURPOSE);

        try {
            // Log refund transaction
            $this->logger->log(
                'Refunding prepaid transaction',
                true,
                [
                    'order_id' => $order->getIncrementId(),
                    'amount' => $refundAmount
                ]
            );

            $cardsForRefund = [];

            foreach ($appliedGiftCards as $card) {
                if ($refundAmount <= 0) {
                    break;
                }
                $cardForRefund = clone $card;
                $cardAmount = $cardForRefund->getBaseAppliedAmount() - $cardForRefund->getBaseRefundedAmount();

                $refundableCardAmount = min($cardAmount, $refundAmount);
                $refundAmount -= $refundableCardAmount;
                $cardForRefund->setBaseAppliedAmount($refundableCardAmount);
                $cardForRefund->setAppliedAmount($refundableCardAmount);

                $this->logger->log(sprintf(
                    'setting giftcard amount for %s to %s out of %s. Total credit refund remaining: %s',
                    $card->getGiftCardCode(),
                    $refundableCardAmount,
                    $card->getBaseAppliedAmount(),
                    $refundAmount
                ));

                $cardsForRefund[] = $cardForRefund;
            }

            if (empty($cardsForRefund)) {
                return false;
            }

            $this->createGiftcardTransaction($order, $cardsForRefund, true);
        } catch (\Exception $e) {
            $this->logger->log(
                "Error refunding giftcard transaction",
                false,
                [
                    'order_id' => $order->getIncrementId(),
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]
            );
            return false;
        }

        return true;
    }
}
