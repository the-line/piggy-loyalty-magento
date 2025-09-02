<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\ResourceModel\AppliedGiftCard;

use Leat\Loyalty\Model\Data\AppliedGiftCard;
use Leat\Loyalty\Model\ResourceModel\AppliedGiftCard as AppliedGiftCardResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = AppliedGiftCardResourceModel::ID_FIELD_NAME;

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            AppliedGiftCard::class,
            AppliedGiftCardResourceModel::class
        );
    }
}
