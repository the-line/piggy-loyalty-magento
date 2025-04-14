<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Api\Data;

interface JobInterface
{

    const RELATION_ID = 'relation_id';
    const SOURCE_ID = 'source_id';
    const STORE_ID = 'store_id';
    const JOB_ID = 'job_id';
    const UPDATED_AT = 'updated_at';
    const COMPLETED = 'completed';
    const CREATED_AT = 'created_at';

    /**
     * Get job_id
     * @return int
     */
    public function getJobId(): int;

    /**
     * Set job_id
     * @param int $jobId
     * @return static
     */
    public function setJobId(int $jobId): static;

    /**
     * Get relation_id
     * @return string|null
     */
    public function getRelationId(): ?string;

    /**
     * Set relation_id
     * @param string $relationId
     * @return static
     */
    public function setRelationId(string $relationId): static;

    /**
     * Get source_id
     * @return string|null
     */
    public function getSourceId(): ?string;

    /**
     * Set source_id
     * @param string $sourceId
     * @return static
     */
    public function setSourceId(string $sourceId): static;


    /**
     * Get store_id
     * @return int|null
     */
    public function getStoreId(): ?int;

    /**
     * Set store_id
     * @param int $storeId
     * @return static
     */
    public function setStoreId(int $storeId): static;

    /**
     * Get completed
     * @return bool|null
     */
    public function getCompleted(): bool;

    /**
     * Set completed
     * @param bool $completed
     * @return static
     */
    public function setCompleted(bool $completed): static;

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
}
