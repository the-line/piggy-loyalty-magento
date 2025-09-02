<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Model\Order\Pdf\Total;

use Magento\Sales\Api\Data\CreditmemoInterface;

class LeatGiftcardRefunded extends LeatBalance
{
    /**
     * @return bool
     */
    public function canDisplay(): bool
    {
        return parent::canDisplay() && $this->getSource() && $this->getSource() instanceof CreditmemoInterface;
    }

    /**
     * @return float|int|null
     */
    public function getAmount()
    {
        $result = parent::getAmount();
        if ($result) {
            return abs($result);
        }

        return $result;
    }
}
