<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Model;

use Leat\AsyncQueue\Api\Data\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObject;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;

class Request extends AbstractModel implements RequestInterface
{
    /**
     * @var Json
     */
    private Json $jsonSerialzer;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param Json $jsonSerialzer
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        Json $jsonSerialzer,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
        $this->jsonSerialzer = $jsonSerialzer;
    }


    /**
     * @inheritDoc
     */
    // phpcs:ignore
    public function _construct()
    {
        $this->_init(\Leat\AsyncQueue\Model\ResourceModel\Request::class);
    }

    /**
     * @inheritDoc
     */
    public function getRequestId(): ?int
    {
        return (int) $this->getData(self::REQUEST_ID);
    }

    /**
     * @inheritDoc
     */
    public function setRequestId($requestId): static
    {
        return $this->setData(self::REQUEST_ID, (int) $requestId);
    }

    /**
     * @inheritDoc
     */
    public function getJobId(): ?int
    {
        return (int) $this->getData(self::JOB_ID);
    }

    /**
     * @inheritDoc
     */
    public function setJobId($jobId): static
    {
        return $this->setData(self::JOB_ID, (int) $jobId);
    }

    /**
     * @inheritDoc
     */
    public function getTypeCode(): ?string
    {
        return $this->getData(self::TYPE_CODE);
    }

    /**
     * @inheritDoc
     */
    public function setTypeCode($typeCode): static
    {
        return $this->setData(self::TYPE_CODE, $typeCode);
    }

    /**
     * @inheritDoc
     */
    public function getPayload(): ?array
    {
        return $this->jsonSerialzer->unserialize($this->getData(self::PAYLOAD) ?? []);
    }

    /**
     * @inheritDoc
     */
    public function setPayload(array $payload): static
    {
        return $this->setData(self::PAYLOAD, $this->jsonSerialzer->serialize($payload ?? []));
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt): static
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedAt(string $updatedAt): static
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    /**
     * @inheritDoc
     */
    public function getIsSynced(): bool
    {
        return (bool) $this->getData(self::IS_SYNCED);
    }

    /**
     * @inheritDoc
     */
    public function setIsSynced(bool $isSynced): static
    {
        return $this->setData(self::IS_SYNCED, $isSynced);
    }

    /**
     * @inheritDoc
     */
    public function getAttempt(): ?int
    {
        return (int) $this->getData(self::ATTEMPT);
    }

    /**
     * @inheritDoc
     */
    public function setAttempt(int $attempt): static
    {
        return $this->setData(self::ATTEMPT, (int) $attempt);
    }

    /**
     * @inheritDoc
     */
    public function getLatestFailReason(): ?string
    {
        return $this->getData(self::LATEST_FAIL_REASON);
    }

    /**
     * @inheritDoc
     */
    public function setLatestFailReason(mixed $latestFailReason): static
    {
        return $this->setData(self::LATEST_FAIL_REASON, $latestFailReason);
    }

    /**
     * @inheritDoc
     */
    public function getResult(): mixed
    {
        $result = $this->getData(self::RESULT);
        if (is_string($result) && json_decode($result, true) && json_last_error() === JSON_ERROR_NONE) {
            return $this->jsonSerialzer->unserialize($result);
        }

        return $result;
    }

    /**
     * @param string $result
     * @return $this
     */
    public function setResult(mixed $result): static
    {
        if (is_array($result)) {
            $result = $this->jsonSerialzer->serialize($result);
        } elseif ($result instanceof DataObject) {
            $result = $this->jsonSerialzer->serialize($result->getData());
        } elseif (is_object($result)) {
            $result = null;
        }

        return $this->setData(self::RESULT, $result);
    }

    /**
     * Create a hash by serializing the request data.
     * - Only static data is used to create the hash to ensure that the hash is the same for all requests.
     *
     * @return string
     */
    public function getTransactionHash(): string
    {
        $data = [
            'request_id' => $this->getRequestId(),
            'job_id' => $this->getJobId(),
            'type_code' => $this->getTypeCode(),
            'payload' => $this->getPayload(),
            'created_at' => $this->getCreatedAt(),
        ];

        return md5(serialize($data));
    }
}
