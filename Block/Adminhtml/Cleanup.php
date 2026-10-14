<?php
declare(strict_types=1);

namespace Merlin\OfferRuleCleanup\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Merlin\OfferRuleCleanup\Model\ExpiredOfferRules;

class Cleanup extends Template
{
    public function __construct(
        Context $context,
        private ExpiredOfferRules $rules,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getExpiredCount(): int
    {
        return $this->rules->count($this->getCutoffDate());
    }

    public function getCutoffDate(): string
    {
        return $this->rules->cutoffDate();
    }

    public function getCleanupUrl(): string
    {
        return $this->getUrl('merlin_offer_rule_cleanup/rules/batch');
    }
}
