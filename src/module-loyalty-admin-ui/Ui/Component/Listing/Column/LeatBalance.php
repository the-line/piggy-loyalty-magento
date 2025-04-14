<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Ui\Component\Listing\Column;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Class LoyaltyBalanceBalance - Grid column for prepaid balance amount
 */
class LeatBalance extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param PriceCurrencyInterface $priceFormatter
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly PriceCurrencyInterface $priceFormatter,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $fieldName = $this->getData('name');

                // Use the field name from the configuration, which might be different than the DB column
                if (isset($item[$fieldName])) {
                    $balanceAmount = (float)$item[$fieldName];
                } elseif (isset($item['leat_loyalty_balance_amount'])) {
                    $balanceAmount = (float)$item['leat_loyalty_balance_amount'];
                } else {
                    $balanceAmount = 0;
                }

                // Only display if there's an amount
                if ($balanceAmount > 0) {
                    $storeId = $item['store_id'] ?? null;
                    $item[$fieldName] = $this->priceFormatter
                        ->format($balanceAmount, false, PriceCurrencyInterface::DEFAULT_PRECISION, $storeId);

                    // Add a CSS class for styling
                    $item[$fieldName . '_css_class'] = 'leat-balance-amount';
                } else {
                    $item[$fieldName] = '-';
                }
            }
        }

        return $dataSource;
    }
}
