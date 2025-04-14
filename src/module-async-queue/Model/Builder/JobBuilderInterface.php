<?php

namespace Leat\AsyncQueue\Model\Builder;

use Leat\AsyncQueue\Api\Data\JobInterface;

interface JobBuilderInterface
{
    /**
     * Start the initialization of a new job
     *
     * @return self
     */
    public function newJob(): self;

    /**
     * Add a request to the job that is being built
     *
     * @param array $payload
     * @param string $typeCode
     * @return self
     */
    public function addRequest(array $payload, string $typeCode): self;

    /**
     * Finalize the job and requests and save them to the database,
     *
     * @return JobInterface|null
     */
    public function create(): ?JobInterface;
}
