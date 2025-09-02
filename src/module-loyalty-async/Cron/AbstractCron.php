<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Cron;

use Leat\AsyncQueue\Model\Builder\JobBuilder;
use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Setup\Patch\Data\AddContactUuidCustomerAttribute;
use Magento\Customer\Model\ResourceModel\Customer\Collection;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;

abstract class AbstractCron
{
    /**
     * @var AdapterInterface
     */
    protected AdapterInterface $connection;

    public function __construct(
        protected CustomerCollectionFactory $customerCollectionFactory,
        protected JobBuilder $jobBuilder,
        protected Connector $leatConnector,
        protected RequestTypePool $leatRequestTypePool,
        protected ResourceConnection $resourceConnection,
    ) {
        $this->connection = $this->resourceConnection->getConnection();
    }

    /**
     * Ensure Leat Cron gets handled securely with logging.
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            $this->run();
        } catch (\Throwable $e) {
            $this->leatConnector->getLogger()->debug(sprintf(
                "%s threw an error: %s \n %s",
                get_class($this),
                $e->getMessage(),
                $e->getTraceAsString()
            ));
        }
    }

    /**
     * @return void
     */
    abstract public function run(): void;

    /**
     * @param int $storeId
     * @param bool $hasExistingContactUuid
     * @return Collection
     * @throws LocalizedException
     */
    public function getCustomerContactCollection(int $storeId, bool $hasExistingContactUuid = true): Collection
    {
        $leatIntegrationGroups = $this->leatConnector->getConfig()->getCustomerGroupMapping();
        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToFilter('group_id', ['in' => $leatIntegrationGroups]);
        $collection->addAttributeToFilter('store_id', $storeId);
        if ($hasExistingContactUuid) {
            $collection->addAttributeToFilter(AddContactUuidCustomerAttribute::ATTRIBUTE_CODE, ['neq' => null]);
        }

        return $collection;
    }
}
