<?php
declare(strict_types=1);

namespace Merlin\OfferRuleCleanup\Console\Command;

use Merlin\OfferRuleCleanup\Model\ExpiredOfferRules;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupCommand extends Command
{
    private const BATCH_SIZE = 100;

    public function __construct(private ExpiredOfferRules $rules, string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('merlin:offer-rules:cleanup')
            ->setDescription('Preview or delete expired Make an Offer cart price rules')
            ->addOption('execute', null, InputOption::VALUE_NONE, 'Delete the matching rules');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = $this->rules->today();
        $count = $this->rules->count($today);
        $output->writeln(sprintf('Expired Make an Offer rules before %s: %d', $today, $count));

        if (!$input->getOption('execute')) {
            foreach ($this->rules->sample($today) as $rule) {
                $output->writeln(sprintf(
                    '  #%d  %s  ended %s',
                    $rule['rule_id'],
                    $rule['name'],
                    $rule['to_date']
                ));
            }
            $output->writeln('Preview only. Add --execute to delete all matching rules.');

            return 0;
        }

        $deleted = 0;
        $failed = 0;
        $lastId = 0;
        while ($ids = $this->rules->nextIds($today, $lastId, self::BATCH_SIZE)) {
            foreach ($ids as $id) {
                $lastId = $id;
                try {
                    if ($this->rules->deleteIfStillExpired($id, $today)) {
                        ++$deleted;
                    }
                } catch (\Exception $exception) {
                    ++$failed;
                    $output->writeln(sprintf('<error>Rule #%d: %s</error>', $id, $exception->getMessage()));
                }
            }
            $output->writeln(sprintf('Processed through rule #%d; deleted %d, failed %d.', $lastId, $deleted, $failed));
        }

        $output->writeln(sprintf('Finished: %d deleted, %d failed.', $deleted, $failed));

        return $failed ? 1 : 0;
    }
}
