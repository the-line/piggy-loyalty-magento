<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\Data;

use Leat\Loyalty\Api\Data\ApplyBalanceResultInterface;
use Leat\Loyalty\Api\Data\ApplyGiftCardResultInterface;
use Magento\Framework\DataObject;

/**
 * Apply balance result data model
 */
class ApplyGiftCardResult extends DataObject implements ApplyGiftCardResultInterface
{
    /**
     * @inheritDoc
     */
    public function getSuccess(): bool
    {
        return (bool)$this->getData('success');
    }

    /**
     * @inheritDoc
     */
    public function setSuccess(bool $success): ApplyGiftCardResultInterface
    {
        return $this->setData('success', $success);
    }

    /**
     * @inheritDoc
     */
    public function getErrorMessage(): ?string
    {
        return $this->getData('error_message');
    }

    /**
     * @inheritDoc
     */
    public function setErrorMessage(?string $errorMessage): ApplyGiftCardResultInterface
    {
        return $this->setData('error_message', $errorMessage);
    }

    /**
     * @inheritDoc
     */
    public function getAppliedCard(): ?\Leat\Loyalty\Api\Data\AppliedGiftCardDetailsInterface
    {
        return $this->getData('applied_card');
    }

    /**
     * @inheritDoc
     */
    public function setAppliedCard(?\Leat\Loyalty\Api\Data\AppliedGiftCardDetailsInterface $cardDetails): ApplyGiftCardResultInterface
    {
        return $this->setData('applied_card', $cardDetails);
    }
}
