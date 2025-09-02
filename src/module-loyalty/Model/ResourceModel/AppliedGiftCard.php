<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class AppliedGiftCard extends AbstractDb
{
    public const TABLE_NAME = 'leat_applied_gift_cards';
    public const ID_FIELD_NAME = 'entity_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE_NAME, self::ID_FIELD_NAME);
    }
}
