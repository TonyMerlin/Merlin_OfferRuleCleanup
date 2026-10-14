<?php
declare(strict_types=1);

namespace Merlin\OfferRuleCleanup\Controller\Adminhtml\Rules;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Merlin\OfferRuleCleanup\Model\ExpiredOfferRules;
use Psr\Log\LoggerInterface;

class Batch extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_SalesRule::quote';
    private const BATCH_SIZE = 50;

    public function __construct(
        Context $context,
        private JsonFactory $jsonFactory,
        private ExpiredOfferRules $rules,
        private LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $afterId = filter_var($this->getRequest()->getParam('after_id', 0), FILTER_VALIDATE_INT);
        if ($afterId === false || $afterId < 0) {
            return $this->jsonFactory->create()->setData(['error' => (string)__('Invalid rule cursor.')]);
        }

        $today = $this->rules->today();
        $ids = $this->rules->nextIds($today, $afterId, self::BATCH_SIZE);
        $deleted = 0;
        $failedIds = [];
        foreach ($ids as $id) {
            try {
                if ($this->rules->deleteIfStillExpired($id, $today)) {
                    ++$deleted;
                }
            } catch (\Exception $exception) {
                $failedIds[] = $id;
                $this->logger->error('Could not delete expired Make an Offer rule', [
                    'rule_id' => $id,
                    'exception' => $exception,
                ]);
            }
        }

        $done = count($ids) < self::BATCH_SIZE;

        return $this->jsonFactory->create()->setData([
            'deleted' => $deleted,
            'failed_ids' => $failedIds,
            'last_id' => $ids ? end($ids) : $afterId,
            'done' => $done,
            'remaining' => $done ? $this->rules->count($today) : null,
        ]);
    }
}
