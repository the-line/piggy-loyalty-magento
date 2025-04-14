<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Api\Data;

use Magento\Framework\DataObject;

interface RequestInterface
{
    const TYPE_CODE = 'type_code';
    const REQUEST_ID = 'request_id';
    const JOB_ID = 'job_id';
    const PAYLOAD = 'payload';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    const IS_SYNCED = 'is_synced';
    const ATTEMPT = 'attempt';
    const LATEST_FAIL_REASON = 'latest_fail_reason';

    const RESULT = 'result';

    /**
     * Get request_id
     * @return int|null
     */
    public function getRequestId(): ?int;

    /**
     * Set request_id
     * @param int $requestId
     * @return static
     */
    public function setRequestId(int $requestId): static;

    /**
     * Get job_id
     * @return int|null
     */
    public function getJobId(): ?int;

    /**
     * Set job_id
     * @param int $jobId
     * @return static
     */
    public function setJobId(int $jobId): static;

    /**
     * Get type
     * @return string|null
     */
    public function getTypeCode(): ?string;

    /**
     * Set type
     * @param string $type
     * @return static
     */
    public function setTypeCode($typeCode): static;

    /**
     * Get payload
     * @return array|null
     */
    public function getPayload(): ?array;

    /**
     * Set payload
     * @param array $payload
     * @return static
     */
    public function setPayload(array $payload): static;

    /**
     * Get created_at
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Set created_at
     * @param string $createdAt
     * @return static
     */
    public function setCreatedAt(string $createdAt): static;

    /**
     * Get updated_at
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * Set updated_at
     * @param string $updatedAt
     * @return static
     */
    public function setUpdatedAt(string $updatedAt): static;


    /**
     * Get is_synced
     * @return bool|null
     */
    public function getIsSynced(): bool;

    /**
     * Set is_synced
     * @param bool $isSynced
     * @return static
     */
    public function setIsSynced(bool $isSynced): static;

    /**
     * Get attempt
     * @return int|null
     */
    public function getAttempt(): ?int;

    /**
     * Set attempt
     * @param int $attempt
     * @return static
     */
    public function setAttempt(int $attempt): static;

    /**
     * Get latest_fail_reason
     * @return string|null
     */
    public function getLatestFailReason(): ?string;

    /**
     * Set latest_fail_reason
     * @param string $latestFailReason
     * @return static
     */
    public function setLatestFailReason(string $latestFailReason): static;

    /**
     * Get result
     * @return string|array|null
     */
    public function getResult(): mixed;

    /**
     * Set result
     * @param string|DataObject|array $result
     * @return static
     */
    public function setResult(mixed $result): static;
}
