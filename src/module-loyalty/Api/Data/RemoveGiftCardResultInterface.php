<?php

declare(strict_types=1);

namespace Leat\Loyalty\Api\Data;

/**
 * Interface for remove gift card result data
 * @api
 */
interface RemoveGiftCardResultInterface
{
    /**
     * Get success status
     *
     * @return bool
     */
    public function getSuccess(): bool;

    /**
     * Set success status
     *
     * @param bool $success
     * @return $this
     */
    public function setSuccess(bool $success): self;

    /**
     * Get message (can be error or success)
     *
     * @return string|null
     */
    public function getMessage(): ?string;

    /**
     * Set message
     *
     * @param string|null $message
     * @return $this
     */
    public function setMessage(?string $message): self;
}
