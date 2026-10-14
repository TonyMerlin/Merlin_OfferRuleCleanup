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
    private const RETENTION_MONTHS = 6;
    private const RULE_NAME_PATTERN = '^Make an Offer #[0-9]+$';
    private const RULE_DESCRIPTION = 'Auto Generation Coupon';

    public function __construct(
        private ResourceConnection $resource,
        private DateTime $date,
        private RuleRepositoryInterface $ruleRepository
    ) {
    }

    public function cutoffDate(): string
    {
        $today = new \DateTimeImmutable($this->date->gmtDate('Y-m-d'), new \DateTimeZone('UTC'));
        $targetMonth = $today->modify('first day of this month')
            ->sub(new \DateInterval('P' . self::RETENTION_MONTHS . 'M'));
        $day = min((int)$today->format('j'), (int)$targetMonth->format('t'));

        return $targetMonth->setDate(
            (int)$targetMonth->format('Y'),
            (int)$targetMonth->format('n'),
            $day
        )->format('Y-m-d');
    }

    public function count(string $cutoffDate): int
    {
        $connection = $this->resource->getConnection();
        $select = $this->candidateSelect($cutoffDate)->reset(Select::COLUMNS)->columns('COUNT(*)');

        return (int)$connection->fetchOne($select);
    }

    /** @return array<int, array<string, mixed>> */
    public function sample(string $cutoffDate, int $limit = 20): array
    {
        $select = $this->candidateSelect($cutoffDate)
            ->columns(['rule_id', 'name', 'to_date'])
            ->order('rule_id ASC')
            ->limit($limit);

        return $this->resource->getConnection()->fetchAll($select);
    }

    /** @return int[] */
    public function nextIds(string $cutoffDate, int $afterId, int $limit): array
    {
        $select = $this->candidateSelect($cutoffDate)
            ->columns('rule_id')
            ->where('rule_id > ?', $afterId)
            ->order('rule_id ASC')
            ->limit($limit);

        return array_map('intval', $this->resource->getConnection()->fetchCol($select));
    }

    public function deleteIfStillExpired(int $ruleId, string $cutoffDate): bool
    {
        $select = $this->candidateSelect($cutoffDate)
            ->reset(Select::COLUMNS)
            ->columns('rule_id')
            ->where('rule_id = ?', $ruleId);

        if (!$this->resource->getConnection()->fetchOne($select)) {
            return false;
        }

        $this->ruleRepository->deleteById($ruleId);

        return true;
    }

    private function candidateSelect(string $cutoffDate): Select
    {
        return $this->resource->getConnection()->select()
            ->from($this->resource->getTableName('salesrule'), [])
            ->where('name REGEXP ?', self::RULE_NAME_PATTERN)
            ->where('description = ?', self::RULE_DESCRIPTION)
            ->where('coupon_type = ?', Rule::COUPON_TYPE_SPECIFIC)
            ->where('simple_action = ?', RuleInterface::DISCOUNT_ACTION_FIXED_AMOUNT_FOR_CART)
            ->where('to_date <= ?', $cutoffDate);
    }
}
