<?php


declare(strict_types=1);

namespace Leat\AsyncQueue\Model\Queue\Request;

use Leat\AsyncQueue\Model\Connector\AbstractConnector;
use Leat\AsyncQueue\Model\Connector\ConnectorPool;
use Leat\AsyncQueue\Model\Job;
use Leat\AsyncQueue\Model\Request;
use Exception;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

abstract class GenericType extends DataObject implements TypeInterface
{
    protected const string CONNECTOR_CODE = 'connector';
    protected const string TYPE_CODE = 'type_code';

    public function __construct(
        protected ConnectorPool $connectorPool,
        protected StoreManagerInterface $storeManager,
        array $data = [],
    ) {
        parent::__construct($data);
    }

    public static function getTypeCode(): string
    {
        return static::TYPE_CODE;
    }

    /**
     * @return string
     */
    public static function getConnectorType(): string
    {
        return static::CONNECTOR_CODE;
    }

    /**
     * Get the connector object related to the request
     *
     * @return AbstractConnector
     * @throws Exception
     */
    public function getConnector(): AbstractConnector
    {
        if (!($connector = $this->connectorPool->getConnector(static::getConnectorType()))) {
            throw new LocalizedException(__("Connector for request not found"));
        }

        return $connector;
    }

    /**
     * Handle any pre-execution logic
     *
     * @param Job|null $job
     * @param Request|null $request
     * @return $this
     */
    public function beforeExecute(Job $job = null, Request $request = null): static
    {
        $this->setData('job', $job);
        $this->setData('request', $request);
        $this->setData('result', $this->execute());

        return $this->afterExecute();
    }

    /**
     * Exceptions are handled in the jobDigestor the ensure requests are skipped and handled where needed.
     *
     * @return mixed
     */
    abstract protected function execute(): mixed;

    /**
     * Handle any post-execution logic
     *
     * @return $this
     */
    public function afterExecute(): static
    {
        return $this;
    }

    /**
     * @param $payload
     * @return $this
     */
    public function unpack($payload): static
    {
        $this->unsetData();
        $this->setData($payload);
        return $this;
    }

    /**
     * @return array
     */
    public function getPayload(): array
    {
        return $this->toArray();
    }

    /**
     * Return the store id.
     * - Prioritize RequestStoreId, as this is manually set (where required).
     * - If no store id is set, use the store id from the job.
     * - Otherwise, use the default store id retrieved from the store manager (current instance of Magento)
     *
     * @return int
     * @throws NoSuchEntityException
     */
    public function getStoreId(): mixed
    {
        $jobStoreId = $this->getJob()?->getStoreId();
        $requestStoreId = $this->getData('store_id');
        $defaultStoreId = $this->storeManager->getStore()->getId();

        return ($requestStoreId ?? $jobStoreId) ?? $defaultStoreId;
    }
}
