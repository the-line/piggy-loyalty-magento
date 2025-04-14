<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\ResourceModel\QuoteItem;

use Leat\Loyalty\Model\Connector;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\DB\Adapter\DeadlockException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

class ExtensionAttributes extends AbstractDb
{
    /**
     * @param Connector $leatConnector
     * @param Context $context
     * @param string|null $connectionName
     */
    public function __construct(
        private Connector $leatConnector,
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init('leat_loyalty_quote_item_extension', 'entity_id');
    }

    /**
     * Check if a quote item exists
     *
     * @param int $itemId
     * @return bool
     */
    public function quoteItemExists(int $itemId): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from('quote_item', ['item_id'])
            ->where('item_id = ?', $itemId);

        return (bool)$connection->fetchOne($select);
    }

    /**
     * Get extension attributes by item ID
     *
     * @param int $itemId
     * @return array|null
     */
    public function getByItemId(int $itemId): ?array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('item_id = ?', $itemId);

        $data = $connection->fetchRow($select);
        return $data ?: null;
    }

    /**
     * Delete extension attributes by item ID
     *
     * @param int $itemId
     * @return int The number of rows affected
     */
    public function deleteByItemId(int $itemId): int
    {
        $connection = $this->getConnection();
        return $connection->delete(
            $this->getMainTable(),
            ['item_id = ?' => $itemId]
        );
    }

    /**
     * @inheritdoc
     */
    public function save(\Magento\Framework\Model\AbstractModel $object)
    {
        $logger = $this->leatConnector->getLogger('reward');
        // Get the item ID from the model
        $itemId = $object->getItemId();

        // Validate that the referenced quote item exists before saving
        if ($itemId && !$this->quoteItemExists($itemId)) {
            $logger->debug(sprintf(
                'Attempted to save extension attributes for non-existent quote item %d. Operation aborted.',
                $itemId
            ));
            return $this;
        }

        try {
            return parent::save($object);
        } catch (DuplicateException $e) {
            // Handle duplicate entry errors more gracefully
            $logger->log(sprintf(
                'Duplicate entry when saving extension attributes for item %d: %s',
                $itemId,
                $e->getMessage()
            ));
            throw new LocalizedException(__('Extension attributes for this item already exist.'));
        } catch (DeadlockException $e) {
            // Handle database deadlocks
            $logger->log(sprintf(
                'Deadlock detected when saving extension attributes for item %d: %s',
                $itemId,
                $e->getMessage()
            ));
            throw new LocalizedException(__('Database conflict detected. Please try again.'));
        } catch (\Exception $e) {
            // Check if it's a foreign key constraint violation
            if (strpos($e->getMessage(), 'FOREIGN KEY') !== false) {
                $logger->log(sprintf(
                    'Foreign key constraint violation when saving extension attributes for item %d: %s',
                    $itemId,
                    $e->getMessage()
                ));
                // Do not rethrow - just log and continue
                return $this;
            }

            // Rethrow other exceptions
            throw $e;
        }
    }
}
