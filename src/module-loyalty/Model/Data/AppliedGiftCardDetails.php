<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\Data;

use Leat\Loyalty\Api\Data\AppliedGiftCardDetailsInterface;
use Magento\Framework\DataObject;

class AppliedGiftCardDetails extends DataObject implements AppliedGiftCardDetailsInterface
{
    /**
     * @inheritDoc
     */
    public function getId(): ?int
    {
        return $this->getData(self::KEY_ID) === null ? null : (int)$this->getData(self::KEY_ID);
    }

    /**
     * @inheritDoc
     */
    public function setId(int $id): AppliedGiftCardDetailsInterface
    {
        return $this->setData(self::KEY_ID, $id);
    }

    /**
     * @inheritDoc
     */
    public function getMaskedCode(): ?string
    {
        return $this->getData(self::KEY_MASKED_CODE);
    }

    /**
     * @inheritDoc
     */
    public function setMaskedCode(string $maskedCode): AppliedGiftCardDetailsInterface
    {
        return $this->setData(self::KEY_MASKED_CODE, $maskedCode);
    }

    /**
     * @inheritDoc
     */
    public function getAppliedAmountFormatted(): ?string
    {
        return $this->getData(self::KEY_APPLIED_AMOUNT_FORMATTED);
    }

    /**
     * @inheritDoc
     */
    public function setAppliedAmountFormatted(string $amountFormatted): AppliedGiftCardDetailsInterface
    {
        return $this->setData(self::KEY_APPLIED_AMOUNT_FORMATTED, $amountFormatted);
    }
}
