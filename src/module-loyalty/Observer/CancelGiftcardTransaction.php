<?php
declare(strict_types=1);

namespace Leat\Loyalty\Observer;

use Leat\Loyalty\Api\AppliedGiftCardRepositoryInterface;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\GiftCard\ApplicationService;
use Leat\Loyalty\Model\GiftCard\TransactionService;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;

class CancelGiftcardTransaction implements ObserverInterface
{
    public function __construct(
        protected AppliedGiftCardRepositoryInterface $appliedGiftCardRepository,
        protected TransactionService $giftcardTransactionService,
        protected Connector $leatConnector,
    ) {
    }

    public function execute(Observer $observer)
    {
        $logger = $this->leatConnector->getLogger(ApplicationService::LOGGER_PURPOSE);

        try {
            /** @var OrderInterface $order */
            $order = $observer->getData('order');

            if (!$order) {
                return;
            }

            $appliedCards = $this->appliedGiftCardRepository->getByOrderId((int) $order->getId());

            if (empty($appliedCards)) {
                return;
            }

            $logger->log('Cancelling giftcard transaction for order: ' . $order->getIncrementId());
            $this->giftcardTransactionService->createGiftcardTransaction($order, $appliedCards, true);
        } catch (\Exception $e) {
            $logger->log(
                'Error in CancelGiftcardTransaction observer: ' . $e->getMessage(),
                context: [
                    'exception' => $e->getTraceAsString(),
                    'order_id' => $order->getIncrementId() ?? null
                ]
            );
        }
    }
}
