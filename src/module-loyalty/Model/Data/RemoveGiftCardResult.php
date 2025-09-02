<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\Data;

use Leat\Loyalty\Api\Data\RemoveGiftCardResultInterface;
use Magento\Framework\DataObject;

/**
 * Remove gift card result data model
 */
class RemoveGiftCardResult extends DataObject implements RemoveGiftCardResultInterface
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
    public function setSuccess(bool $success): RemoveGiftCardResultInterface
    {
        return $this->setData('success', $success);
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): ?string
    {
        return $this->getData('message');
    }

    /**
     * @inheritDoc
     */
    public function setMessage(?string $message): RemoveGiftCardResultInterface
    {
        return $this->setData('message', $message);
    }
}
