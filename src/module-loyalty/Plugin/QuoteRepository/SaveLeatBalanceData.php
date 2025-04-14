<?php

declare(strict_types=1);

namespace Leat\Loyalty\Plugin\QuoteRepository;

use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\Logger;
use Leat\Loyalty\Model\Order\LeatBalanceRepository;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartSearchResultsInterface;

/**
 * Plugin to save and load leat balance data for quotes
 */
class SaveLeatBalanceData
{
    private Logger $logger;
    /**
     * @param Connector $leatConnector
     */
    public function __construct(
        private readonly Connector $leatConnector
    ) {
        $this->logger = $this->leatConnector->getLogger(LeatBalanceRepository::LOGGER_PURPOSE);
    }

    /**
     * Save leat balance data to quote
     *
     * @param CartRepositoryInterface $subject
     * @param CartInterface $quote
     * @return void
     */
    public function beforeSave(
        CartRepositoryInterface $subject,
        CartInterface $quote
    ): void {
        try {
            $extensionAttributes = $quote->getExtensionAttributes();
            if ($extensionAttributes === null) {
                return;
            }

            if ($extensionAttributes->getLeatLoyaltyBalanceAmount() !== null) {
                $quote->setData('leat_loyalty_balance_amount', $extensionAttributes->getLeatLoyaltyBalanceAmount());
            }
        } catch (\Exception $e) {
            $this->logger->log(
                'Error saving leat balance data: ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString()
            );
        }
    }

    /**
     * Add leat balance data to quote extension attributes after get
     *
     * @param CartRepositoryInterface $subject
     * @param CartInterface $quote
     * @return CartInterface
     */
    public function afterGet(
        CartRepositoryInterface $subject,
        CartInterface $quote
    ): CartInterface {
        try {
            $this->loadLeatBalanceData($quote);
        } catch (\Exception $e) {
            $this->logger->log('Error loading leat balance data: ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
        }
        return $quote;
    }

    /**
     * Add leat balance data to quotes in collection
     *
     * @param CartRepositoryInterface $subject
     * @param CartSearchResultsInterface $searchResult
     * @return CartSearchResultsInterface
     */
    public function afterGetList(
        CartRepositoryInterface $subject,
        CartSearchResultsInterface $searchResult
    ): CartSearchResultsInterface {
        try {
            foreach ($searchResult->getItems() as $quote) {
                $this->loadLeatBalanceData($quote);
            }
        } catch (\Exception $e) {
            $this->logger->log('Error loading leat balance data for list: ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
        }
        return $searchResult;
    }

    /**
     * Load leat balance data from quote and set to extension attributes
     *
     * @param CartInterface $quote
     * @return void
     */
    private function loadLeatBalanceData(CartInterface $quote): void
    {
        $balanceAmount = $quote->getData('leat_loyalty_balance_amount');
        if ($balanceAmount === null) {
            return;
        }

        $extensionAttributes = $quote->getExtensionAttributes();
        if ($extensionAttributes !== null) {
            $extensionAttributes->setLeatLoyaltyBalanceAmount((float)$balanceAmount);
        }
    }
}
