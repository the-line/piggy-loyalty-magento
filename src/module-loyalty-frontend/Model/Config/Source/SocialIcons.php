<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class SocialIcons implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'copy', 'label' => __('Copy Link')],
            ['value' => 'twitter', 'label' => __('Twitter/X')],
            ['value' => 'whatsapp', 'label' => __('WhatsApp')],
            ['value' => 'email', 'label' => __('Email')],
            ['value' => 'sms', 'label' => __('SMS')],
        ];
    }
}
