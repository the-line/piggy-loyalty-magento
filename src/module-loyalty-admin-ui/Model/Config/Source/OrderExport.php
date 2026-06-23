<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class OrderExport implements OptionSourceInterface
{
    public const DISABLED = 0;
    public const ENABLED = 2;
    public const ENABLED_LEGACY = 1;

    /**
     * Get options array
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [
            [
                'value' => self::DISABLED,
                'label' => __('Disabled')
            ],
            [
                'value' => self::ENABLED,
                'label' => __('Enabled | Order API')
            ],
            [
                'value' => self::ENABLED_LEGACY,
                'label' => __('Enabled | Legacy Transaction-based')
            ]
        ];

        return $options;
    }
}
