<?php
declare(strict_types=1);

namespace Leat\Loyalty\Observer;

use Leat\Loyalty\Api\AppliedGiftCardRepositoryInterface;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\GiftCard\ApplicationService;
use Leat\Loyalty\Model\GiftCard\ValidatorService;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;

class CheckGiftcardBeforeQuoteSubmit implements ObserverInterface
{
    public function __construct(
        protected AppliedGiftCardRepositoryInterface $appliedGiftCardRepository,
        protected ApplicationService $applicationService,
        protected ValidatorService $validatorService,
        protected Connector $leatConnector,
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $logger = $this->leatConnector->getLogger(ApplicationService::LOGGER_PURPOSE);

        try {
            /** @var CartInterface $quote */
            $quote = $observer->getData('quote');
            /** @var OrderInterface $order */
            $order = $observer->getData('order');

            if (!$quote || !$order) {
                return;
            }

            $quoteId = (int) $quote->getId();
            $storeId = (int) $quote->getStoreId();
            $appliedCards = $this->appliedGiftCardRepository->getByQuoteId((int)$quote->getId());

            if (empty($appliedCards)) {
                return;
            }

            foreach ($appliedCards as $appliedCard) {
                $giftcard = $this->validatorService->getCard($appliedCard->getGiftCardCode(), $storeId);
                if (!$giftcard || !$this->validatorService->isValid($giftcard, $storeId)) {
                    $this->applicationService->removeFromQuote($quoteId, (int) $appliedCard->getId());
                    throw new LocalizedException(__('Gift card %1 is no longer valid.', $appliedCard->getGiftCardCode()));
                }

                if ($this->validatorService->getAvailableBalance($giftcard, $storeId) < $appliedCard->getBaseAppliedAmount()) {
                    throw new LocalizedException(__('Insufficient balance on gift card %1.', $appliedCard->getGiftCardCode()));
                }

                $appliedCard->setLeatGiftcardUuid($giftcard->getUuid());
                $this->appliedGiftCardRepository->save($appliedCard);
            }
        } catch (LocalizedException $e) {
            $logger->log('checkout failure: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
        }
    }
}
