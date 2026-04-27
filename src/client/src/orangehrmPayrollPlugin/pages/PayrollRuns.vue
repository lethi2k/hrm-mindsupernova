<!--

/**

 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures

 * all the essential functionalities required for any enterprise.

 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com

 */

-->

<template>
  <div class="orangehrm-background-container">
    <div class="orangehrm-paper-container">
      <oxd-text tag="h5" class="orangehrm-title">
        {{ $t('payroll.payroll_runs') }}
      </oxd-text>

      <oxd-divider />

      <oxd-form @submit-valid="onCreate">
        <oxd-form-row>
          <oxd-grid :cols="2" class="orangehrm-full-width-grid">
            <oxd-grid-item>
              <oxd-input-field
                v-model="newYearMonth"
                :label="$t('payroll.year_month')"
                type="date"
                required
              />
            </oxd-grid-item>

            <oxd-grid-item class="payroll-create-action">
              <oxd-button
                class="payroll-create-button"
                display-type="secondary"
                :label="$t('payroll.create_payroll_run')"
                type="submit"
              />
            </oxd-grid-item>
          </oxd-grid>
        </oxd-form-row>
      </oxd-form>
    </div>

    <br />

    <div class="orangehrm-paper-container">
      <div v-if="isLoading" class="orangehrm-text-center">Loading</div>

      <table v-else class="orangehrm-employee-list-table">
        <thead>
          <tr>
            <th>ID</th>

            <th>YYYY-MM</th>

            <th>{{ $t('payroll.payroll_status') }}</th>

            <th>Review</th>

            <th>{{ $t('payroll.payslips') }}</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="row in items" :key="row.id">
            <td>{{ row.id }}</td>

            <td>{{ row.yearMonth }}</td>

            <td>{{ row.status }}</td>

            <td>
              <oxd-text
                class="payroll-review-text"
                :class="
                  row.reviewConfirmed
                    ? 'orangehrm-green-text'
                    : 'orangehrm-red-text'
                "
              >
                {{ row.reviewConfirmed ? 'Confirmed' : 'Pending review' }}
              </oxd-text>
            </td>

            <td>
              <div class="payroll-actions-cell">
                <oxd-button
                  :label="'Review & Confirm'"
                  size="small"
                  display-type="secondary"
                  @click="openReviewPanel(row.id)"
                />

                <oxd-button
                  :label="$t('payroll.send_payslips')"
                  size="small"
                  display-type="label"
                  :disabled="!row.reviewConfirmed"
                  @click="openSendDialog(row)"
                />

                <oxd-button
                  :label="$t('payroll.view_delivery_status')"
                  size="small"
                  display-type="label"
                  @click="loadEmailLogs(row.id)"
                />
                <oxd-button
                  :label="$t('performance.delete')"
                  size="small"
                  display-type="label"
                  @click="deleteRun(row.id)"
                />
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <br />

    <div v-if="statusRunId" class="orangehrm-paper-container">
      <oxd-text tag="h6" class="orangehrm-title">
        {{ $t('payroll.email_status') }} ({{ $t('payroll.payroll_run_id') }}:

        {{ statusRunId }})
      </oxd-text>

      <oxd-divider />

      <div v-if="logLoading" class="orangehrm-text-center">Loading</div>

      <div v-else class="payroll-log-summary">
        <oxd-text>
          {{ $t('payroll.label_sent') }}: {{ logSummary.sent }} |
          {{ $t('payroll.label_failed') }}: {{ logSummary.failed }} |
          {{ $t('payroll.label_pending') }}: {{ logSummary.pending }} |
          {{ $t('payroll.label_skipped') }}: {{ logSummary.skipped }}
        </oxd-text>
        <oxd-button
          class="orangehrm-left-space"
          display-type="secondary"
          size="small"
          :label="$t('payroll.sync_mail_status')"
          @click="syncQueue(statusRunId)"
        />
      </div>

      <table v-if="!logLoading" class="orangehrm-employee-list-table">
        <thead>
          <tr>
            <th>{{ $t('payroll.email_status') }}</th>

            <th>{{ $t('payroll.recipient_email') }}</th>

            <th>emp #</th>

            <th>{{ $t('payroll.last_error') }}</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="log in emailLogs" :key="log.id">
            <td>{{ log.status }}</td>

            <td>{{ log.recipientEmail }}</td>

            <td>{{ log.empNumber }}</td>

            <td>{{ log.lastError }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <br v-if="reviewRunId" />

    <div v-if="reviewRunId" class="orangehrm-paper-container">
      <oxd-text tag="h6" class="orangehrm-title">
        Employee Review (Total Employees: {{ reviewRows.length }})
      </oxd-text>
      <oxd-divider />
      <div v-if="reviewLoading" class="orangehrm-text-center">Loading</div>
      <div v-else class="orangehrm-container">
        <div class="payroll-review-meta">
          <oxd-text class="payroll-records-found">
            ({{ reviewRows.length }}) Records Found
          </oxd-text>
        </div>
        <table class="orangehrm-employee-list-table payroll-review-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Full Name</th>
              <th>National ID</th>
              <th>Job Title</th>
              <th>Base Salary</th>
              <th>Standard Working Days</th>
              <th>Actual Working Days</th>
              <th>Actual Salary</th>
              <th>Overtime</th>
              <th>Allowance (Project Commission)</th>
              <th>Total Salary</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in reviewRows" :key="row.id">
              <td>{{ row.employeeId || row.empNumber }}</td>
              <td>{{ row.fullName }}</td>
              <td>{{ row.nationalId || '-' }}</td>
              <td>{{ row.jobTitle || '-' }}</td>
              <td>
                {{ formatMoney(row.baseSalary) }}
              </td>
              <td>{{ row.standardWorkingDays }}</td>
              <td>{{ row.actualWorkingDays }}</td>
              <td>{{ formatMoney(row.actualSalary) }}</td>
              <td>{{ row.overtime || '00:00' }}</td>
              <td>
                <input
                  v-model.number="row.allowance"
                  type="number"
                  min="0"
                  step="0.01"
                  class="oxd-input oxd-input--active payroll-base-salary-input"
                  :disabled="
                    reviewLoading ||
                    !reviewEditing ||
                    reviewRunStatus !== 'draft'
                  "
                  @input="recalculateRow(row)"
                />
              </td>
              <td>{{ formatMoney(row.totalSalary) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="orangehrm-modal-footer">
        <oxd-button
          display-type="ghost"
          class="orangehrm-button-margin"
          :label="$t('general.cancel')"
          @click="clearReviewPanel"
        />
        <oxd-button
          display-type="secondary"
          class="orangehrm-button-margin"
          :disabled="
            reviewLoading ||
            !reviewRows.length ||
            !reviewEditing ||
            reviewRunStatus !== 'draft'
          "
          :label="'Confirm Review'"
          @click="confirmReview"
        />
        <oxd-button
          v-if="
            reviewRunConfirmed && !reviewEditing && reviewRunStatus === 'draft'
          "
          display-type="label"
          class="orangehrm-button-margin"
          :disabled="reviewLoading || !reviewRows.length"
          :label="'Edit'"
          @click="enableReviewEditing"
        />
      </div>
    </div>
  </div>

  <teleport to="#app">
    <oxd-dialog
      v-if="showSendDialog"
      :style="{maxWidth: '480px'}"
      @update:show="closeSendDialog"
    >
      <div class="orangehrm-modal-header">
        <oxd-text type="card-title">
          {{ $t('payroll.send_payslips') }}
        </oxd-text>
      </div>

      <oxd-divider />

      <oxd-text tag="p" class="orangehrm-text">
        {{ $t('payroll.send_payslip_format') }}
      </oxd-text>

      <div class="payroll-format-row">
        <label class="payroll-format-option">
          <input v-model="sendFormat" type="radio" value="xlsx" />

          {{ $t('payroll.format_xlsx') }}
        </label>

        <label class="payroll-format-option">
          <input v-model="sendFormat" type="radio" value="pdf" />

          {{ $t('payroll.format_pdf') }}
        </label>
      </div>

      <div class="orangehrm-modal-footer">
        <oxd-button
          display-type="ghost"
          class="orangehrm-button-margin"
          :label="$t('general.cancel')"
          @click="closeSendDialog"
        />

        <oxd-button
          display-type="secondary"
          class="orangehrm-button-margin"
          :label="$t('payroll.confirm_send')"
          @click="confirmSend"
        />
      </div>
    </oxd-dialog>
  </teleport>
</template>

<script>
import {ref, onMounted} from 'vue';

import {APIService} from '@/core/util/services/api.service';

import {OxdDialog} from '@ohrm/oxd';

const runsApi = new APIService(
  window.appGlobal.baseUrl,

  '/api/v2/payroll/runs',
);

const emailLogsApi = new APIService(
  window.appGlobal.baseUrl,

  '/api/v2/payroll/email-logs',
);
const employeeApi = new APIService(
  window.appGlobal.baseUrl,
  '/api/v2/pim/employees',
);

export default {
  components: {
    'oxd-dialog': OxdDialog,
  },

  setup() {
    const now = new Date();
    const currentDate = `${now.getFullYear()}-${String(
      now.getMonth() + 1,
    ).padStart(2, '0')}-01`;
    const newYearMonth = ref(currentDate);

    const items = ref([]);

    const isLoading = ref(false);

    const showSendDialog = ref(false);

    const sendFormat = ref('xlsx');

    const pendingSendRunId = ref(null);
    const reviewRunId = ref(null);
    const reviewRows = ref([]);
    const reviewLoading = ref(false);
    const reviewRunConfirmed = ref(false);
    const reviewEditing = ref(false);
    const reviewRunStatus = ref('');

    const statusRunId = ref(null);

    const emailLogs = ref([]);

    const logLoading = ref(false);

    const logSummary = ref({
      sent: 0,

      failed: 0,

      pending: 0,

      skipped: 0,
    });

    const load = () => {
      isLoading.value = true;

      runsApi

        .getAll()

        .then((res) => {
          const d = res.data || {};

          items.value = (d.data || []).map((r) => ({
            id: r.id,

            yearMonth: r.yearMonth,

            status: r.status,
            reviewConfirmed: !!r.reviewConfirmed,

            created: r.createdAt,
          }));
        })

        .finally(() => {
          isLoading.value = false;
        });
    };

    onMounted(load);

    return {
      newYearMonth,

      items,

      isLoading,

      load,

      showSendDialog,

      sendFormat,

      pendingSendRunId,
      reviewRunId,
      reviewRows,
      reviewLoading,
      reviewRunConfirmed,
      reviewEditing,
      reviewRunStatus,

      statusRunId,

      emailLogs,

      logLoading,

      logSummary,
    };
  },

  methods: {
    async onCreate() {
      const http = new APIService(
        window.appGlobal.baseUrl,

        '/api/v2/payroll/runs',
      );

      const yearMonth = this.newYearMonth?.slice(0, 7);
      const res = await http.create({yearMonth});
      const created = res?.data?.data ?? null;
      const now = new Date();
      this.newYearMonth = `${now.getFullYear()}-${String(
        now.getMonth() + 1,
      ).padStart(2, '0')}-01`;
      await this.load();
      if (created?.id) {
        await this.openReviewPanel(created.id);
      }
    },

    async doOp(id, action, extra = {}) {
      const http = new APIService(
        window.appGlobal.baseUrl,

        `/api/v2/payroll/runs/${id}/operations`,
      );

      await http.create({action, ...extra});

      this.load();
    },

    openSendDialog(row) {
      if (!row.reviewConfirmed) {
        return;
      }
      this.pendingSendRunId = row.id;

      this.sendFormat = 'xlsx';

      this.showSendDialog = true;
    },

    closeSendDialog() {
      this.showSendDialog = false;

      this.pendingSendRunId = null;
    },

    async confirmSend() {
      if (!this.pendingSendRunId) {
        return;
      }

      const id = this.pendingSendRunId;

      this.showSendDialog = false;

      this.pendingSendRunId = null;

      await this.doOp(id, 'send', {fileFormat: this.sendFormat});
    },

    async openReviewPanel(runId) {
      this.reviewRunId = runId;
      const run = this.items.find((item) => item.id === runId);
      this.reviewRunConfirmed = !!run?.reviewConfirmed;
      this.reviewRunStatus = run?.status ?? '';
      this.reviewEditing =
        !this.reviewRunConfirmed && this.reviewRunStatus === 'draft';
      await this.loadReviewRows(runId);
    },

    clearReviewPanel() {
      this.reviewRunId = null;
      this.reviewRows = [];
      this.reviewRunConfirmed = false;
      this.reviewEditing = false;
      this.reviewRunStatus = '';
    },

    async loadReviewRows(runId) {
      this.reviewLoading = true;
      try {
        const rows = await this.loadEmployeesAsReviewRows(runId);
        const snapshot = await this.loadReviewSnapshot(runId);
        this.reviewRows = rows.map((row) => ({
          ...row,
          baseSalary: Number(row.baseSalary ?? 0),
          standardWorkingDays: Number(row.standardWorkingDays ?? 22),
          actualWorkingDays: Number(row.actualWorkingDays ?? 0),
          actualSalary: Number(row.actualSalary ?? 0),
          overtime: row.overtime ?? '00:00',
          allowance: Number(
            snapshot?.[Number(row.empNumber)]?.allowance ?? row.allowance ?? 0,
          ),
          totalSalary: Number(
            snapshot?.[Number(row.empNumber)]?.netSalary ??
              row.totalSalary ??
              0,
          ),
        }));
      } catch (e) {
        this.reviewRows = await this.loadEmployeesAsReviewRows(runId);
      } finally {
        this.reviewLoading = false;
      }
    },

    async loadReviewSnapshot(runId) {
      if (!runId) {
        return {};
      }
      try {
        const http = new APIService(
          window.appGlobal.baseUrl,
          `/api/v2/payroll/runs/${runId}/operations`,
        );
        const res = await http.create({action: 'getReviewSnapshot'});
        return res?.data?.data?.snapshot ?? {};
      } catch (error) {
        return {};
      }
    },

    async loadEmployeesAsReviewRows(runId = null) {
      const parseSalaryAmount = (rawAmount) => {
        if (rawAmount === null || rawAmount === undefined) {
          return 0;
        }
        const normalized = String(rawAmount).replace(/[^0-9.-]/g, '');
        const value = Number(normalized);
        return Number.isFinite(value) ? value : 0;
      };
      const getEmployeeBaseSalary = async (empNumber) => {
        if (!empNumber) {
          return 0;
        }
        try {
          const salaryHttp = new APIService(
            window.appGlobal.baseUrl,
            `/api/v2/pim/employees/${empNumber}/salary-components`,
          );
          const salaryRes = await salaryHttp.getAll({limit: 200});
          const salaryRows = salaryRes?.data?.data ?? [];
          const preferred = salaryRows.find((row) => {
            const name = String(row?.salaryName ?? '').toLowerCase();
            return (
              name.includes('base') ||
              name.includes('basic') ||
              name.includes('luong co ban') ||
              name.includes('lương cơ bản')
            );
          });
          const candidate = preferred ?? salaryRows[0];
          return parseSalaryAmount(candidate?.amount);
        } catch (error) {
          return 0;
        }
      };
      const getActualWorkingDays = async (empNumber, yearMonth) => {
        if (!empNumber || !yearMonth) {
          return 0;
        }
        try {
          const [year, month] = yearMonth
            .split('-')
            .map((value) => Number(value));
          const lastDay = new Date(year, month, 0).getDate();
          const fromDate = `${yearMonth}-01`;
          const toDate = `${yearMonth}-${String(lastDay).padStart(2, '0')}`;
          const timesheetHttp = new APIService(
            window.appGlobal.baseUrl,
            `/api/v2/time/employees/${empNumber}/timesheets`,
          );
          const timesheetRes = await timesheetHttp.getAll({
            fromDate,
            toDate,
            limit: 1,
          });
          const timesheet = (timesheetRes?.data?.data ?? [])[0];
          if (!timesheet?.id) {
            return 0;
          }
          const entriesHttp = new APIService(
            window.appGlobal.baseUrl,
            `/api/v2/time/employees/timesheets/${timesheet.id}/entries`,
          );
          const entriesRes = await entriesHttp.getAll();
          const rows = entriesRes?.data?.data ?? [];
          const workingDates = new Set();
          rows.forEach((row) => {
            const dates = row?.dates ?? {};
            Object.values(dates).forEach((entry) => {
              const duration = String(entry?.duration ?? '').trim();
              if (
                entry?.date &&
                duration &&
                duration !== '00:00' &&
                duration !== '0:00'
              ) {
                workingDates.add(entry.date);
              }
            });
          });
          return workingDates.size;
        } catch (error) {
          return 0;
        }
      };
      const getTimesheetSummary = async (empNumber, yearMonth) => {
        if (!empNumber || !yearMonth) {
          return {actualWorkingDays: 0, overtime: '00:00'};
        }
        const STANDARD_WORKDAY_SECONDS = 8 * 3600;
        const getDurationInSeconds = (durationLabel) => {
          const [hoursRaw, minutesRaw] = String(durationLabel || '00:00').split(
            ':',
          );
          const hours = Number(hoursRaw || 0);
          const minutes = Number(minutesRaw || 0);
          return hours * 3600 + minutes * 60;
        };
        const formatSecondsToHoursLabel = (seconds) => {
          const totalMinutes = Math.round(seconds / 60);
          const hours = Math.floor(totalMinutes / 60);
          const minutes = totalMinutes % 60;
          return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(
            2,
            '0',
          )}`;
        };
        try {
          const [year, month] = yearMonth
            .split('-')
            .map((value) => Number(value));
          const lastDay = new Date(year, month, 0).getDate();
          const fromDate = `${yearMonth}-01`;
          const toDate = `${yearMonth}-${String(lastDay).padStart(2, '0')}`;
          const timesheetHttp = new APIService(
            window.appGlobal.baseUrl,
            `/api/v2/time/employees/${empNumber}/timesheets`,
          );
          const timesheetRes = await timesheetHttp.getAll({
            fromDate,
            toDate,
            limit: 1,
          });
          const timesheet = (timesheetRes?.data?.data ?? [])[0];
          if (!timesheet?.id) {
            return {actualWorkingDays: 0, overtime: '00:00'};
          }
          const entriesHttp = new APIService(
            window.appGlobal.baseUrl,
            `/api/v2/time/employees/timesheets/${timesheet.id}/entries`,
          );
          const entriesRes = await entriesHttp.getAll();
          const columns = entriesRes?.data?.meta?.columns ?? {};
          let regularSeconds = 0;
          let overtimeSeconds = 0;
          Object.entries(columns).forEach(([date, column]) => {
            const dateObj = new Date(`${date}T00:00:00`);
            const dayOfWeek = dateObj.getDay();
            const workedSeconds = getDurationInSeconds(column?.total?.label);
            if (dayOfWeek === 0 || dayOfWeek === 6) {
              overtimeSeconds += workedSeconds * 2;
              return;
            }
            regularSeconds += Math.min(workedSeconds, STANDARD_WORKDAY_SECONDS);
            overtimeSeconds +=
              Math.max(0, workedSeconds - STANDARD_WORKDAY_SECONDS) * 1.5;
          });
          return {
            actualWorkingDays: Number(
              (regularSeconds / STANDARD_WORKDAY_SECONDS).toFixed(2),
            ),
            overtime: formatSecondsToHoursLabel(overtimeSeconds),
          };
        } catch (error) {
          return {actualWorkingDays: 0, overtime: '00:00'};
        }
      };
      const run = this.items.find((item) => item.id === runId);
      const yearMonth =
        run?.yearMonth ?? String(this.newYearMonth || '').slice(0, 7);
      const requestOptions = [
        {limit: 1000, includeEmployees: 'currentAndPast'},
        {limit: 1000, includeEmployees: 'onlyCurrent'},
        {limit: 1000},
      ];
      for (const params of requestOptions) {
        try {
          const empRes = await employeeApi.getAll(params);
          const employees = empRes?.data?.data ?? [];
          if (employees.length) {
            const rows = await Promise.all(
              employees.map(async (emp, idx) => {
                const baseSalary = await getEmployeeBaseSalary(emp.empNumber);
                const summary = await getTimesheetSummary(
                  emp.empNumber,
                  yearMonth,
                );
                return {
                  id: emp.empNumber ?? idx + 1,
                  empNumber: emp.empNumber,
                  employeeId: emp.employeeId ?? `${emp.empNumber ?? ''}`,
                  fullName: [emp.lastName, emp.middleName, emp.firstName]
                    .filter(Boolean)
                    .join(' ')
                    .trim(),
                  nationalId: '',
                  jobTitle: emp.jobTitle?.title ?? '',
                  baseSalary,
                  standardWorkingDays: 22,
                  actualWorkingDays: summary.actualWorkingDays,
                  actualSalary: 0,
                  overtime: summary.overtime,
                  allowance: 0,
                  totalSalary: 0,
                };
              }),
            );
            return rows.map((row) => {
              this.recalculateRow(row);
              return row;
            });
          }
        } catch (error) {
          // try next query variant
        }
      }
      return [];
    },

    async confirmReview() {
      if (!this.reviewRunId || this.reviewRunStatus !== 'draft') {
        return;
      }
      await this.doOp(this.reviewRunId, 'confirmReview', {
        reviewRows: this.reviewRows.map((row) => ({
          empNumber: row.empNumber,
          baseSalary: row.baseSalary,
          allowance: row.allowance,
          actualSalary: row.actualSalary,
          totalSalary: row.totalSalary,
        })),
        reviewRowsJson: JSON.stringify(
          this.reviewRows.map((row) => ({
            empNumber: row.empNumber,
            baseSalary: row.baseSalary,
            allowance: row.allowance,
            actualSalary: row.actualSalary,
            totalSalary: row.totalSalary,
          })),
        ),
      });
      await this.load();
      this.reviewRunConfirmed = true;
      this.reviewEditing = false;
      await this.loadReviewRows(this.reviewRunId);
    },

    enableReviewEditing() {
      this.reviewEditing = true;
    },

    recalculateRow(row) {
      const baseSalary = Number(row.baseSalary ?? 0);
      const standardWorkingDays = Number(row.standardWorkingDays ?? 22);
      const actualWorkingDays = Number(row.actualWorkingDays ?? 0);
      const allowance = Number(row.allowance ?? 0);
      const actualSalary =
        standardWorkingDays > 0
          ? (baseSalary / standardWorkingDays) * actualWorkingDays
          : 0;
      row.actualSalary = Number(actualSalary.toFixed(2));
      row.totalSalary = Number((actualSalary + allowance).toFixed(2));
    },

    formatMoney(value) {
      const number = Number(value ?? 0);
      return number.toFixed(2);
    },

    async loadEmailLogs(runId) {
      this.statusRunId = runId;

      this.logLoading = true;

      try {
        const res = await emailLogsApi.getAll({runId, limit: 500});

        const d = res.data || {};

        const rows = d.data || [];

        this.emailLogs = rows;

        this.logSummary = {
          sent: rows.filter((l) => l.status === 'sent').length,

          failed: rows.filter((l) => l.status === 'failed').length,

          pending: rows.filter((l) => l.status === 'pending').length,

          skipped: rows.filter((l) => l.status === 'skipped').length,
        };
      } finally {
        this.logLoading = false;
      }
    },

    async syncQueue(runId) {
      const http = new APIService(
        window.appGlobal.baseUrl,

        `/api/v2/payroll/runs/${runId}/operations`,
      );

      await http.create({action: 'syncQueue'});

      if (this.statusRunId) {
        await this.loadEmailLogs(this.statusRunId);
      }

      this.load();
    },

    async deleteRun(id) {
      if (!window.confirm('Delete this payroll run?')) {
        return;
      }
      await runsApi.deleteAll({ids: [id]});
      if (this.statusRunId === id) {
        this.statusRunId = null;
        this.emailLogs = [];
      }
      if (this.reviewRunId === id) {
        this.clearReviewPanel();
      }
      await this.load();
    },
  },
};
</script>

<style lang="scss" scoped>
.orangehrm-background-container {
  margin-top: 1rem;
  font-family: var(--oxd-font-family, 'Nunito Sans', Arial, sans-serif);
}

.orangehrm-paper-container {
  margin-bottom: 1rem;
  padding: 1rem 1.25rem;
}

.orangehrm-title {
  margin-bottom: 0.5rem;
}

.payroll-create-action {
  display: flex;
  justify-content: center;
  align-items: end;
}

.payroll-create-button {
  min-width: 220px;
}

.payroll-review-text {
  display: inline-block;
  margin-top: 0.25rem;
}

.payroll-actions-cell {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin: 0.35rem 0;
}

.payroll-actions-cell :deep(.oxd-button) {
  margin-left: 0 !important;
}

.payroll-format-row {
  display: flex;
  gap: 1.5rem;
  margin: 1rem 0;
}

.orangehrm-modal-footer {
  margin: 20px;
}

.payroll-format-option {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.payroll-log-summary {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0.5rem 0 1rem;
}

.payroll-base-salary-input {
  min-width: 110px;
}

.payroll-review-table {
  min-width: 1320px;
}

.orangehrm-employee-list-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  background: #ffffff;
  border-radius: 0.75rem;
  overflow: hidden;
}

.orangehrm-employee-list-table thead th {
  background: #f6f7f9;
  border-bottom: 1px solid #e8eaef;
  color: #64728c;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  padding: 0.75rem 1rem;
  text-align: left;
  text-transform: uppercase;
}

.orangehrm-employee-list-table tbody td {
  background: #ffffff;
  border-bottom: 1px solid #f2f4f6;
  color: #64728c;
  font-size: 0.875rem;
  padding: 0.875rem 1rem;
}

.orangehrm-employee-list-table tbody tr:last-child td {
  border-bottom: none;
}

.orangehrm-employee-list-table tbody tr:hover td {
  background: #f9fafb;
}

.payroll-review-table th,
.payroll-review-table td {
  white-space: nowrap;
  vertical-align: middle;
  font-family: inherit;
}

.payroll-review-table .payroll-base-salary-input {
  font-family: inherit;
  max-width: 140px;
}

.payroll-review-meta {
  display: flex;
  align-items: center;
  margin-bottom: 0.5rem;
}

.payroll-records-found {
  color: #64728c;
  font-size: 0.875rem;
  font-weight: 600;
}

.orangehrm-button-margin {
  margin: 0px 10px;
}

.orangehrm-modal-footer {
  gap: 0.75rem;
}
</style>
