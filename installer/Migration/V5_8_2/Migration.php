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

namespace OrangeHRM\Installer\Migration\V5_8_2;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Types\Types;
use OrangeHRM\Installer\Util\V1\AbstractMigration;
use OrangeHRM\Installer\Util\V1\LangStringHelper;

class Migration extends AbstractMigration
{
    private ?LangStringHelper $langStringHelper = null;

    public function up(): void
    {
        if (!$this->getSchemaHelper()->columnExists('ohrm_mail_queue', 'attachments')) {
            $this->getSchemaHelper()->addColumn('ohrm_mail_queue', 'attachments', Types::TEXT, ['Notnull' => false]);
        }

        if ($this->getSchemaHelper()->tableExists(['ohrm_payroll_run'])) {
            if ($this->getSchemaHelper()->columnExists('ohrm_payroll_run', 'year_month')
                && !$this->getSchemaHelper()->columnExists('ohrm_payroll_run', 'payroll_month')) {
                $this->getConnection()->executeStatement(
                    'ALTER TABLE ohrm_payroll_run CHANGE COLUMN `year_month` `payroll_month` VARCHAR(7) NOT NULL'
                );
            }
            if ($this->getSchemaHelper()->columnExists('ohrm_payroll_run', 'status')
                && !$this->getSchemaHelper()->columnExists('ohrm_payroll_run', 'run_status')) {
                $this->getConnection()->executeStatement(
                    'ALTER TABLE ohrm_payroll_run CHANGE COLUMN `status` `run_status` VARCHAR(20) NOT NULL'
                );
            }
            if (!$this->getSchemaHelper()->columnExists('ohrm_payroll_run', 'reviewed_by')) {
                $this->getSchemaHelper()->addColumn('ohrm_payroll_run', 'reviewed_by', Types::INTEGER, ['Notnull' => false]);
            }
            if (!$this->getSchemaHelper()->columnExists('ohrm_payroll_run', 'reviewed_at')) {
                $this->getSchemaHelper()->addColumn('ohrm_payroll_run', 'reviewed_at', Types::DATETIME_MUTABLE, ['Notnull' => false]);
            }
            if (!$this->getSchemaHelper()->columnExists('ohrm_payroll_run', 'approved_by')) {
                $this->getSchemaHelper()->addColumn('ohrm_payroll_run', 'approved_by', Types::INTEGER, ['Notnull' => false]);
            }
            if (!$this->getSchemaHelper()->columnExists('ohrm_payroll_run', 'approved_at')) {
                $this->getSchemaHelper()->addColumn('ohrm_payroll_run', 'approved_at', Types::DATETIME_MUTABLE, ['Notnull' => false]);
            }
        }

        if (!$this->getSchemaHelper()->tableExists(['ohrm_payroll_run'])) {
            $this->getSchemaHelper()->createTable('ohrm_payroll_run')
                ->addColumn('id', Types::INTEGER, ['Autoincrement' => true, 'Notnull' => true])
                ->addColumn('payroll_month', Types::STRING, ['Length' => 7, 'Notnull' => true])
                ->addColumn('run_status', Types::STRING, ['Length' => 20, 'Notnull' => true])
                ->addColumn('created_by', Types::INTEGER, ['Notnull' => true])
                ->addColumn('reviewed_by', Types::INTEGER, ['Notnull' => false])
                ->addColumn('reviewed_at', Types::DATETIME_MUTABLE, ['Notnull' => false])
                ->addColumn('approved_by', Types::INTEGER, ['Notnull' => false])
                ->addColumn('approved_at', Types::DATETIME_MUTABLE, ['Notnull' => false])
                ->addColumn('created_at', Types::DATETIME_MUTABLE, ['Notnull' => true])
                ->addColumn('updated_at', Types::DATETIME_MUTABLE, ['Notnull' => true])
                ->setPrimaryKey(['id'])
                ->addUniqueIndex(['payroll_month'], 'payroll_run_payroll_month_uq')
                ->create();
            $this->getSchemaHelper()->addForeignKey('ohrm_payroll_run', new ForeignKeyConstraint(
                ['created_by'],
                'ohrm_user',
                ['id'],
                'payroll_run_created_by',
                ['onDelete' => 'CASCADE']
            ));
        }

        if (!$this->getSchemaHelper()->tableExists(['ohrm_payroll_payslip'])) {
            $this->getSchemaHelper()->createTable('ohrm_payroll_payslip')
                ->addColumn('id', Types::INTEGER, ['Autoincrement' => true, 'Notnull' => true])
                ->addColumn('payroll_run_id', Types::INTEGER, ['Notnull' => true])
                ->addColumn('emp_number', Types::INTEGER, ['Notnull' => true])
                ->addColumn('net_salary', Types::STRING, ['Length' => 100, 'Notnull' => false])
                ->addColumn('allowance', Types::STRING, ['Length' => 100, 'Notnull' => false])
                ->addColumn('file_path', Types::STRING, ['Length' => 1000, 'Notnull' => false])
                ->addColumn('file_format', Types::STRING, ['Length' => 10, 'Notnull' => true])
                ->addColumn('file_checksum', Types::STRING, ['Length' => 64, 'Notnull' => false])
                ->setPrimaryKey(['id'])
                ->addUniqueIndex(['payroll_run_id', 'emp_number'], 'payroll_payslip_run_emp_uq')
                ->create();
            $this->getSchemaHelper()->addForeignKey('ohrm_payroll_payslip', new ForeignKeyConstraint(
                ['payroll_run_id'],
                'ohrm_payroll_run',
                ['id'],
                'payslip_payroll_run',
                ['onDelete' => 'CASCADE']
            ));
            $this->getSchemaHelper()->addForeignKey('ohrm_payroll_payslip', new ForeignKeyConstraint(
                ['emp_number'],
                'hs_hr_employee',
                ['emp_number'],
                'payslip_emp_number',
                ['onDelete' => 'CASCADE']
            ));
        }
        if ($this->getSchemaHelper()->tableExists(['ohrm_payroll_payslip'])
            && !$this->getSchemaHelper()->columnExists('ohrm_payroll_payslip', 'allowance')) {
            $this->getSchemaHelper()->addColumn('ohrm_payroll_payslip', 'allowance', Types::STRING, [
                'Length' => 100,
                'Notnull' => false,
            ]);
        }

        if (!$this->getSchemaHelper()->tableExists(['ohrm_payroll_email_log'])) {
            $this->getSchemaHelper()->createTable('ohrm_payroll_email_log')
                ->addColumn('id', Types::INTEGER, ['Autoincrement' => true, 'Notnull' => true])
                ->addColumn('payslip_id', Types::INTEGER, ['Notnull' => true])
                ->addColumn('recipient_email', Types::STRING, ['Length' => 200, 'Notnull' => false])
                ->addColumn('email_status', Types::STRING, ['Length' => 20, 'Notnull' => true])
                ->addColumn('attempt_count', Types::INTEGER, ['Notnull' => true, 'Default' => 0])
                ->addColumn('sent_at', Types::DATETIME_MUTABLE, ['Notnull' => false])
                ->addColumn('last_error', Types::TEXT, ['Notnull' => false])
                ->addColumn('idempotency_key', Types::STRING, ['Length' => 200, 'Notnull' => true])
                ->addColumn('mail_queue_id', Types::INTEGER, ['Notnull' => false])
                ->addColumn('triggered_by', Types::INTEGER, ['Notnull' => false])
                ->setPrimaryKey(['id'])
                ->addUniqueIndex(['payslip_id', 'idempotency_key'], 'payroll_email_idempotency_uq')
                ->addIndex(['mail_queue_id'], 'idx_payroll_email_mail_queue')
                ->create();
            $this->getSchemaHelper()->addForeignKey('ohrm_payroll_email_log', new ForeignKeyConstraint(
                ['payslip_id'],
                'ohrm_payroll_payslip',
                ['id'],
                'email_log_payslip',
                ['onDelete' => 'CASCADE']
            ));
        } elseif ($this->getSchemaHelper()->columnExists('ohrm_payroll_email_log', 'status')
            && !$this->getSchemaHelper()->columnExists('ohrm_payroll_email_log', 'email_status')) {
            $this->getConnection()->executeStatement(
                'ALTER TABLE ohrm_payroll_email_log CHANGE COLUMN `status` `email_status` VARCHAR(20) NOT NULL'
            );
        }

        if (!$this->getSchemaHelper()->tableExists(['ohrm_payroll_config'])) {
            $this->getSchemaHelper()->createTable('ohrm_payroll_config')
                ->addColumn('id', Types::INTEGER, ['Notnull' => true, 'Default' => 1])
                ->addColumn('schedule_enabled', Types::BOOLEAN, ['Notnull' => true, 'Default' => false])
                ->addColumn('schedule_day', Types::INTEGER, ['Notnull' => true, 'Default' => 1])
                ->addColumn('schedule_hour', Types::INTEGER, ['Notnull' => true, 'Default' => 2])
                ->addColumn('max_retries', Types::INTEGER, ['Notnull' => true, 'Default' => 5])
                ->addColumn('password_protected_files', Types::BOOLEAN, ['Notnull' => true, 'Default' => false])
                ->addColumn('signed_download', Types::BOOLEAN, ['Notnull' => true, 'Default' => false])
                ->addColumn('default_email_locale', Types::STRING, ['Length' => 10, 'Notnull' => true, 'Default' => 'vi_VN'])
                ->addColumn('default_subject', Types::STRING, ['Length' => 500, 'Notnull' => true])
                ->addColumn('default_body', Types::TEXT, ['Notnull' => true])
                ->setPrimaryKey(['id'])
                ->create();
            $this->getConnection()->insert('ohrm_payroll_config', [
                'id' => 1,
                'schedule_enabled' => 0,
                'schedule_day' => 1,
                'schedule_hour' => 2,
                'max_retries' => 5,
                'password_protected_files' => 0,
                'signed_download' => 0,
                'default_email_locale' => 'vi_VN',
                'default_subject' => '[{{companyName}}] Thông Báo Phiếu lương {{yearMonth}}',
                'default_body' => "Kính gửi Anh/Chị {{employeeName}},\n\nPhòng Nhân sự gửi phiếu lương tháng {{yearMonth}} ({{netSalary}}) theo file đính kèm.",
            ]);
        }

        $row = $this->getConnection()
            ->createQueryBuilder()
            ->select('id')
            ->from('ohrm_module', 'm')
            ->andWhere('m.name = :n')
            ->setParameter('n', 'payroll')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();
        if ($row === false) {
            $this->getConnection()->insert('ohrm_module', [
                'name' => 'payroll',
                'status' => 1,
                'display_name' => 'Payroll',
            ]);
        }

        if (!$this->i18nGroupExists('payroll')) {
            $this->getConnection()->insert('ohrm_i18n_group', [
                'name' => 'payroll',
                'title' => 'Payroll',
            ]);
        }

        $this->getLangStringHelper()->insertOrUpdateLangStrings(__DIR__, 'payroll');
        $this->bumpPayrollLangVersion($this->getVersion());

        $this->getDataGroupHelper()->insertApiPermissions(__DIR__ . '/permission/api.yaml');
        $this->getDataGroupHelper()->insertScreenPermissions(__DIR__ . '/permission/screens.yaml');

        $this->addPayrollMenuIfMissing();
    }

    public function getVersion(): string
    {
        return '5.8.2';
    }

    private function getLangStringHelper(): LangStringHelper
    {
        if ($this->langStringHelper === null) {
            $this->langStringHelper = new LangStringHelper($this->getConnection());
        }
        return $this->langStringHelper;
    }

    private function i18nGroupExists(string $name): bool
    {
        $q = $this->getConnection()->createQueryBuilder()
            ->select('1')
            ->from('ohrm_i18n_group', 'g')
            ->andWhere('g.name = :n')
            ->setParameter('n', $name);
        return (bool) $q->executeQuery()->fetchOne();
    }

    private function bumpPayrollLangVersion(string $version): void
    {
        $id = (int) $this->getLangStringHelper()->getGroupId('payroll');
        if ($id === 0) {
            return;
        }
        $this->getConnection()->executeStatement(
            'UPDATE ohrm_i18n_lang_string SET version = ? WHERE version IS NULL AND group_id = ?',
            [$version, $id]
        );
    }

    private function addPayrollMenuIfMissing(): void
    {
        $exists = $this->getConnection()
            ->createQueryBuilder()
            ->select('id')
            ->from('ohrm_menu_item', 'i')
            ->andWhere('i.menu_title = :t')
            ->setParameter('t', 'Payroll')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();
        if ($exists !== false) {
            return;
        }

        $dgh = $this->getDataGroupHelper();
        $moduleId = (string) $dgh->getModuleIdByName('payroll');
        $modScreen = $dgh->getScreenIdByModuleAndUrl($moduleId, 'viewPayrollModule');
        $runScreen = $dgh->getScreenIdByModuleAndUrl($moduleId, 'viewPayroll');

        $this->getConnection()->insert('ohrm_menu_item', [
            'menu_title' => 'Payroll',
            'screen_id' => $modScreen,
            'parent_id' => null,
            'level' => 1,
            'order_hint' => 1270,
            'status' => 1,
            'additional_params' => '{"icon":"admin"}',
        ]);
        $pid = (int) $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from('ohrm_menu_item', 'i')
            ->andWhere('i.menu_title = :t')
            ->setParameter('t', 'Payroll')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();
        $this->getConnection()->insert('ohrm_menu_item', [
            'menu_title' => 'Payroll Runs',
            'screen_id' => $runScreen,
            'parent_id' => $pid,
            'level' => 2,
            'order_hint' => 100,
            'status' => 1,
            'additional_params' => null,
        ]);
        $this->getConnection()->insert('ohrm_module_default_page', [
            'module_id' => $dgh->getModuleIdByName('payroll'),
            'user_role_id' => $dgh->getUserRoleIdByName('Admin'),
            'action' => 'payroll/viewPayroll',
            'priority' => 0,
        ]);
    }
}
