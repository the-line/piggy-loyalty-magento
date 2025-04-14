<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\ResourceModel\SalesRule;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ExtensionAttributes extends AbstractDb
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init('leat_loyalty_salesrule_extension', 'entity_id');
    }

    /**
     * Get extension attributes by rule ID
     *
     * @param int $ruleId
     * @return array|false
     */
    public function getByRuleId(int $ruleId)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('rule_id = ?', $ruleId);

        return $connection->fetchRow($select);
    }

    /**
     * Get sales rule ID by reward UUID
     *
     * @param string $rewardUuid
     * @return int|null
     */
    public function getSalesRuleIdByRewardUuid(string $rewardUuid): ?int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['rule_id'])
            ->where('reward_uuid = ?', $rewardUuid);

        $ruleId = $connection->fetchOne($select);
        return $ruleId ? (int)$ruleId : null;
    }
}
