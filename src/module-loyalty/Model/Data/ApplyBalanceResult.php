<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\Data;

use Leat\Loyalty\Api\Data\ApplyBalanceResultInterface;
use Magento\Framework\DataObject;

/**
 * Apply balance result data model
 */
class ApplyBalanceResult extends DataObject implements ApplyBalanceResultInterface
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
    public function setSuccess(bool $success): ApplyBalanceResultInterface
    {
        return $this->setData('success', $success);
    }

    /**
     * @inheritDoc
     */
    public function getBalanceAmount(): float
    {
        return (float)$this->getData('balance_amount');
    }

    /**
     * @inheritDoc
     */
    public function setBalanceAmount(float $amount): ApplyBalanceResultInterface
    {
        return $this->setData('balance_amount', $amount);
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
    public function setErrorMessage(?string $errorMessage): ApplyBalanceResultInterface
    {
        return $this->setData('error_message', $errorMessage);
    }
}
