<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model;

use Leat\Loyalty\Api\RemoveGiftCardInterface;
use Leat\Loyalty\Api\Data\RemoveGiftCardResultInterface;
use Leat\Loyalty\Api\Data\RemoveGiftCardResultInterfaceFactory;
use Leat\Loyalty\Model\GiftCard\ApplicationService;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Quote\Api\CartRepositoryInterface; // To ensure cart exists

class RemoveGiftCard implements RemoveGiftCardInterface
{
    private ApplicationService $applicationService;
    private RemoveGiftCardResultInterfaceFactory $resultFactory;
    private Connector $leatConnector; // For logger
    private CartRepositoryInterface $cartRepository;

    public function __construct(
        ApplicationService $applicationService,
        RemoveGiftCardResultInterfaceFactory $resultFactory,
        Connector $leatConnector,
        CartRepositoryInterface $cartRepository
    ) {
        $this->applicationService = $applicationService;
        $this->resultFactory = $resultFactory;
        $this->leatConnector = $leatConnector;
        $this->cartRepository = $cartRepository;
    }

    /**
     * @inheritDoc
     */
    public function remove(string $cartId, int $appliedCardId): RemoveGiftCardResultInterface
    {
        /** @var \Leat\Loyalty\Api\Data\RemoveGiftCardResultInterface $result */
        $result = $this->resultFactory->create();
        $result->setSuccess(false);
        $logger = $this->leatConnector->getLogger(ApplicationService::LOGGER_PURPOSE); // Use same logger purpose

        try {
            // Ensure cart exists (optional, but good practice for API endpoint)
            // $this->cartRepository->getActive((int)$cartId);
            // ApplicationService->removeFromQuote already validates if card belongs to quote

            $this->applicationService->removeFromQuote((int)$cartId, $appliedCardId);
            $result->setSuccess(true);
            $result->setMessage((string)__('Gift card removed successfully.'));
        } catch (NoSuchEntityException $e) {
            $logger->log(
                'Error removing giftcard (NoSuchEntity): ' . $e->getMessage(),
                [
                    'exception_trace' => $e->getTraceAsString(),
                    'cartId' => $cartId,
                    'appliedCardId' => $appliedCardId
                ]
            );
            $result->setMessage($e->getRawMessage());
        } catch (CouldNotDeleteException $e) {
            $logger->log(
                'Error removing giftcard (CouldNotDelete): ' . $e->getMessage(),
                [
                    'exception_trace' => $e->getTraceAsString(),
                    'cartId' => $cartId,
                    'appliedCardId' => $appliedCardId
                ]
            );
            $result->setMessage($e->getRawMessage());
        } catch (\Exception $e) {
            $logger->log(
                'Generic error removing giftcard: ' . $e->getMessage(),
                [
                    'exception_trace' => $e->getTraceAsString(),
                    'cartId' => $cartId,
                    'appliedCardId' => $appliedCardId
                ]
            );
            $result->setMessage((string)__('An unexpected error occurred while removing the gift card.'));
        }
        return $result;
    }
}
