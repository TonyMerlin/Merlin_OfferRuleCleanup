<?php
declare(strict_types=1);

namespace Merlin\OfferRuleCleanup\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\SalesRule\Model\Rule;

class ExpiredOfferRules
{
    private const RULE_NAME_PATTERN = '^Make an Offer #[0-9]+$';
    private const RULE_DESCRIPTION = 'Auto Generation Coupon';

    public function __construct(
        private ResourceConnection $resource,
        private DateTime $date,
        private RuleRepositoryInterface $ruleRepository
    ) {
    }

    public function today(): string
    {
        return $this->date->gmtDate('Y-m-d');
    }

    public function count(string $today): int
    {
        $connection = $this->resource->getConnection();
        $select = $this->candidateSelect($today)->reset(Select::COLUMNS)->columns('COUNT(*)');

        return (int)$connection->fetchOne($select);
    }

    /** @return array<int, array<string, mixed>> */
    public function sample(string $today, int $limit = 20): array
    {
        $select = $this->candidateSelect($today)
            ->columns(['rule_id', 'name', 'to_date'])
            ->order('rule_id ASC')
            ->limit($limit);

        return $this->resource->getConnection()->fetchAll($select);
    }

    /** @return int[] */
    public function nextIds(string $today, int $afterId, int $limit): array
    {
        $select = $this->candidateSelect($today)
            ->columns('rule_id')
            ->where('rule_id > ?', $afterId)
            ->order('rule_id ASC')
            ->limit($limit);

        return array_map('intval', $this->resource->getConnection()->fetchCol($select));
    }

    public function deleteIfStillExpired(int $ruleId, string $today): bool
    {
        $select = $this->candidateSelect($today)
            ->reset(Select::COLUMNS)
            ->columns('rule_id')
            ->where('rule_id = ?', $ruleId);

        if (!$this->resource->getConnection()->fetchOne($select)) {
            return false;
        }

        $this->ruleRepository->deleteById($ruleId);

        return true;
    }

    private function candidateSelect(string $today): Select
    {
        return $this->resource->getConnection()->select()
            ->from($this->resource->getTableName('salesrule'), [])
            ->where('name REGEXP ?', self::RULE_NAME_PATTERN)
            ->where('description = ?', self::RULE_DESCRIPTION)
            ->where('coupon_type = ?', Rule::COUPON_TYPE_SPECIFIC)
            ->where('use_auto_generation = ?', 1)
            ->where('uses_per_coupon = ?', 1)
            ->where('simple_action = ?', RuleInterface::DISCOUNT_ACTION_FIXED_AMOUNT_FOR_CART)
            ->where('to_date < ?', $today);
    }
}
