<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Model\Queue\Request;

use Leat\AsyncQueue\Model\Job;
use Leat\AsyncQueue\Model\Request;

interface TypeInterface
{
    /**
     * Type code for the class
     * @return string
     */
    public static function getTypeCode(): string;

    /**
     * @param Job|null $job
     * @param Request|null $request
     * @return $this
     */
    public function beforeExecute(Job $job = null, Request $request = null): static;

    /**
     * @return $this
     */
    public function afterExecute(): static;

    /**
     * @param $payload
     * @return $this
     */
    public function unpack($payload): static;

    /**
     * @return array
     */
    public function getPayload(): array;
}
