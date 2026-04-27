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

namespace OrangeHRM\Tests\Payroll\Service;

use DateTime;
use Doctrine\ORM\EntityManager;
use OrangeHRM\Core\Service\DateTimeHelperService;
use OrangeHRM\Entity\PayrollConfig;
use OrangeHRM\Entity\PayrollRun;
use OrangeHRM\Entity\PayrollPayslip;
use OrangeHRM\Payroll\Dao\PayrollDao;
use OrangeHRM\Payroll\Service\PayrollService;
use OrangeHRM\Tests\Util\TestCase;
use Exception;

/**
 * @group Payroll
 * @group Service
 */
class PayrollServiceTest extends TestCase
{
    public function testCreateRunInvalidYearMonth(): void
    {
        $service = new PayrollService();
        $this->expectException(Exception::class);
        $service->createRun('2025-13', 1);
    }

    public function testCreateRunRejectsDuplicate(): void
    {
        $existing = new PayrollRun();
        $dao = $this->createMock(PayrollDao::class);
        $dao->method('getRunByYearMonth')
            ->with('2025-01')
            ->willReturn($existing);
        $em = $this->createMock(EntityManager::class);
        $em->expects($this->never())->method('flush');
        $dt = $this->createMock(DateTimeHelperService::class);
        $dt->method('getNow')->willReturn(new DateTime('2025-01-15T12:00:00'));

        $service = $this->getMockBuilder(PayrollService::class)
            ->onlyMethods(['getPayrollDao', 'getEntityManager', 'getDateTimeHelper'])
            ->getMock();
        $service->method('getPayrollDao')->willReturn($dao);
        $service->method('getEntityManager')->willReturn($em);
        $service->method('getDateTimeHelper')->willReturn($dt);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('already exists');
        $service->createRun('2025-01', 1);
    }

    public function testSendPayslipsRequiresReviewConfirmed(): void
    {
        $run = new PayrollRun();
        $run->setId(1);
        $run->setStatus(PayrollRun::STATUS_DRAFT);
        $dao = $this->createMock(PayrollDao::class);
        $dao->method('getRun')->with(1)->willReturn($run);
        $em = $this->createMock(EntityManager::class);
        $em->expects($this->never())->method('flush');

        $service = $this->getMockBuilder(PayrollService::class)
            ->onlyMethods(['getPayrollDao', 'getEntityManager'])
            ->getMock();
        $service->method('getPayrollDao')->willReturn($dao);
        $service->method('getEntityManager')->willReturn($em);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('review and confirm');
        $service->sendPayslips(1, PayrollPayslip::FORMAT_XLSX, 1);
    }

    public function testScheduledAutoSendReturnsZeroWhenScheduleDisabled(): void
    {
        $config = new PayrollConfig();
        $config->setId(1);
        $config->setScheduleEnabled(false);
        $dao = $this->createMock(PayrollDao::class);
        $dao->expects($this->never())->method('getRunByYearMonth');
        $em = $this->createMock(EntityManager::class);
        $dt = $this->createMock(DateTimeHelperService::class);
        $dt->method('getNow')->willReturn(new DateTime('2025-03-05T10:00:00'));

        $service = $this->getMockBuilder(PayrollService::class)
            ->onlyMethods(['getPayrollDao', 'getEntityManager', 'getDateTimeHelper', 'getOrCreateConfig'])
            ->getMock();
        $service->method('getOrCreateConfig')->willReturn($config);
        $service->method('getPayrollDao')->willReturn($dao);
        $service->method('getEntityManager')->willReturn($em);
        $service->method('getDateTimeHelper')->willReturn($dt);

        $this->assertSame(0, $service->scheduledAutoSendForPreviousMonthIfDue());
    }
}
