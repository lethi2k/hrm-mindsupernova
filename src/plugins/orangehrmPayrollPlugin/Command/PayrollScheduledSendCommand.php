<?php

/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software: you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with OrangeHRM.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace OrangeHRM\Payroll\Command;

use OrangeHRM\Payroll\Service\PayrollService;
use OrangeHRM\Framework\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PayrollScheduledSendCommand extends Command
{
    public function getCommandName(): string
    {
        return 'orangehrm:payroll-scheduled-send';
    }

    protected function configure(): void
    {
        $this->setDescription('Run scheduled auto-send of review-confirmed payslips when the configured day/hour is reached');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $svc = new PayrollService();
        $n = $svc->scheduledAutoSendForPreviousMonthIfDue();
        if ($n > 0) {
            $output->writeln('Auto-send was triggered for a review-confirmed run.');
        }
        $synced = $svc->syncMailQueueToLogs();
        if ($synced > 0) {
            $output->writeln("Queue sync: $synced");
        }
        return self::SUCCESS;
    }
}
