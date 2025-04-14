<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Model\Order\Pdf\Total;

use Leat\Loyalty\Api\OrderLeatBalanceRepositoryInterface;
use Leat\Loyalty\Model\Config;
use Magento\Sales\Model\Order\Pdf\Total\DefaultTotal;
use Magento\Tax\Helper\Data;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\ResourceModel\Sales\Order\Tax\CollectionFactory;

/**
 * Add leat balance to PDF invoice totals
 */
class LeatBalance extends DefaultTotal
{
    /**
     * @param Config $config
     * @param OrderLeatBalanceRepositoryInterface $orderLeatBalanceRepository
     * @param Data $taxHelper
     * @param Calculation $taxCalculation
     * @param CollectionFactory $ordersFactory
     * @param array $data
     */
    public function __construct(
        private readonly Config                              $config,
        private readonly OrderLeatBalanceRepositoryInterface $orderLeatBalanceRepository,
        Data                                                 $taxHelper,
        Calculation                                          $taxCalculation,
        CollectionFactory                                    $ordersFactory,
        array                                                $data = []
    ) {
        parent::__construct($taxHelper, $taxCalculation, $ordersFactory, $data);
    }

    /**
     * Check if we can display total information in PDF
     *
     * @return bool
     */
    public function canDisplay(): bool
    {
        $order = $this->getOrder();
        if (!$order) {
            return false;
        }

        if (!$this->config->isPrepaidBalanceEnabled((int) $order->getStoreId())) {
            return false;
        }

        // Get balance amount from order
        $balanceAmount = $this->getAmount();

        if ($balanceAmount === null) {
            return false;
        }

        return true;
    }

    /**
     * Get LeatBalance amount from source
     *
     * @return float|null
     */
    public function getAmount()
    {
        $source = $this->getSource();
        $order = $this->getOrder();

        if (!$source || !$order) {
            return null;
        }

        // First try to get from invoice/source
        $balanceAmount = null;
        $sourceField = $this->getSourceField() ?: 'leat_loyalty_balance_amount';

        if ($source->getData($sourceField) !== null) {
            $balanceAmount = (float)$source->getData($sourceField);
        }

        // Then try to get from order if not found in invoice
        if ($balanceAmount === null && $order->getData($sourceField) !== null) {
            $balanceAmount = (float)$order->getData($sourceField);
        }

        // Finally try from repository if still null
        if ($balanceAmount === null) {
            $balanceAmount = $this->orderLeatBalanceRepository->getLeatBalanceAmount($order);
        }

        return $balanceAmount > 0 ? -$balanceAmount : null;
    }

    /**
     * Get leat balance total amount for display in PDF
     *
     * @return array
     */
    public function getTotalsForDisplay(): array
    {
        if (!$this->canDisplay()) {
            return [];
        }

        $amount = $this->getOrder()->formatPriceTxt((float)$this->getAmount());
        $fontSize = $this->getFontSize() ?: 7;

        return [
            [
                'amount' => $amount,
                'label' => __($this->getTitle() ?: 'Prepaid Balance') . ':',
                'font_size' => $fontSize
            ]
        ];
    }
}
