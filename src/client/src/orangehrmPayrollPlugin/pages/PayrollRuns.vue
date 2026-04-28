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
                  step="1"
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
      :style="{maxWidth: '780px', width: '100%'}"
      @update:show="closeSendDialog"
    >
      <div class="orangehrm-modal-header">
        <oxd-text type="card-title">{{ $t('payroll.send_payslips') }}</oxd-text>
      </div>

      <oxd-divider />

      <!-- Format selection -->
      <div class="send-section">
        <oxd-text tag="p" class="send-section-label">{{
          $t('payroll.send_payslip_format')
        }}</oxd-text>
        <div class="payroll-format-row">
          <label class="payroll-format-option">
            <input v-model="sendFormat" type="radio" value="xlsx" />
            Excel (XLSX)
          </label>
          <label class="payroll-format-option">
            <input v-model="sendFormat" type="radio" value="pdf" />
            PDF
          </label>
        </div>
      </div>

      <oxd-divider />

      <!-- Email preview -->
      <div class="send-section">
        <oxd-text tag="p" class="send-section-label">Xem trước email</oxd-text>
        <div v-if="emailConfigLoading" class="send-loading">Đang tải...</div>
        <div v-else class="email-preview-box">
          <div class="email-preview-row">
            <span class="email-preview-key">Tiêu đề:</span>
            <span class="email-preview-val">{{
              emailConfig.defaultSubject || '(chưa cấu hình)'
            }}</span>
          </div>
          <div class="email-preview-row email-preview-body-row">
            <span class="email-preview-key">Nội dung:</span>
            <pre class="email-preview-body">{{
              emailConfig.defaultBody || '(chưa cấu hình)'
            }}</pre>
          </div>
          <p class="email-preview-hint">
            Biến: <code>&#123;&#123;companyName&#125;&#125;</code>
            <code>&#123;&#123;employeeName&#125;&#125;</code>
            <code>&#123;&#123;yearMonth&#125;&#125;</code>
            <code>&#123;&#123;netSalary&#125;&#125;</code>
          </p>
        </div>
      </div>

      <oxd-divider />

      <!-- Test email -->
      <div class="send-section">
        <oxd-text tag="p" class="send-section-label"
          >Gửi email thử nghiệm</oxd-text
        >
        <div class="test-email-row">
          <input
            v-model="testEmailAddr"
            type="email"
            placeholder="Nhập địa chỉ email thử nghiệm..."
            class="oxd-input oxd-input--active test-email-input"
          />
          <oxd-button
            display-type="secondary"
            :label="testEmailSending ? 'Đang gửi...' : 'Gửi thử'"
            :disabled="testEmailSending || !testEmailAddr"
            @click="sendTestEmail"
          />
        </div>
        <div
          v-if="testEmailResult"
          :class="[
            'test-email-result',
            testEmailResult.ok ? 'test-email-ok' : 'test-email-err',
          ]"
        >
          {{ testEmailResult.msg }}
        </div>
      </div>

      <oxd-divider />

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

    const emailConfig = ref({defaultSubject: '', defaultBody: ''});
    const emailConfigLoading = ref(false);
    const testEmailAddr = ref('');
    const testEmailSending = ref(false);
    const testEmailResult = ref(null);

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

      emailConfig,
      emailConfigLoading,
      testEmailAddr,
      testEmailSending,
      testEmailResult,

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

    async openSendDialog(row) {
      if (!row.reviewConfirmed) {
        return;
      }
      this.pendingSendRunId = row.id;
      this.sendFormat = 'xlsx';
      this.testEmailAddr = '';
      this.testEmailResult = null;
      this.showSendDialog = true;
      await this.fetchEmailConfig();
    },

    async fetchEmailConfig() {
      this.emailConfigLoading = true;
      this.emailConfig = {defaultSubject: '', defaultBody: ''};
      try {
        const http = new APIService(
          window.appGlobal.baseUrl,
          `/api/v2/payroll/runs/${this.pendingSendRunId}/operations`,
        );
        const res = await http.create({action: 'getEmailConfig'});
        const d = res?.data?.data ?? {};
        this.emailConfig = {
          defaultSubject: d.defaultSubject ?? '',
          defaultBody: d.defaultBody ?? '',
        };
      } finally {
        this.emailConfigLoading = false;
      }
    },

    closeSendDialog() {
      this.showSendDialog = false;
      this.pendingSendRunId = null;
      this.testEmailResult = null;
    },

    async confirmSend() {
      if (!this.pendingSendRunId) {
        return;
      }
      const id = this.pendingSendRunId;
      this.showSendDialog = false;
      this.pendingSendRunId = null;
      this.testEmailResult = null;
      await this.doOp(id, 'send', {fileFormat: this.sendFormat});
    },

    async sendTestEmail() {
      if (!this.testEmailAddr || !this.pendingSendRunId) return;
      this.testEmailSending = true;
      this.testEmailResult = null;
      try {
        const http = new APIService(
          window.appGlobal.baseUrl,
          `/api/v2/payroll/runs/${this.pendingSendRunId}/operations`,
        );
        await http.create({
          action: 'testEmail',
          fileFormat: this.sendFormat,
          testEmailAddr: this.testEmailAddr,
        });
        this.testEmailResult = {
          ok: true,
          msg: `Email thử nghiệm đã được xếp hàng gửi đến ${this.testEmailAddr}`,
        };
      } catch (e) {
        const msg =
          e?.response?.data?.error?.message ??
          e?.message ??
          'Lỗi không xác định';
        this.testEmailResult = {ok: false, msg: `Gửi thất bại: ${msg}`};
      } finally {
        this.testEmailSending = false;
      }
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
      row.actualSalary = Math.round(actualSalary);
      row.totalSalary = Math.round(actualSalary + allowance);
    },

    formatMoney(value) {
      const number = Math.round(Number(value ?? 0));
      return number.toLocaleString('vi-VN');
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

/* Send payslips dialog */
.send-section {
  padding: 1rem 1.5rem;
}

.send-section-label {
  font-weight: 700;
  font-size: 0.75rem;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 0.75rem;
}

.send-loading {
  color: #64728c;
  font-size: 0.875rem;
  padding: 0.5rem 0;
}

/* Format radio buttons */
.payroll-format-row {
  display: flex;
  gap: 1rem;
  margin: 0.25rem 0 0;
}

.payroll-format-option {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 1.25rem;
  border: 2px solid #e8eaef;
  border-radius: 0.5rem;
  cursor: pointer;
  font-size: 0.9rem;
  color: #374151;
  font-weight: 500;
  transition: border-color 0.15s, background 0.15s;
  user-select: none;

  &:has(input:checked) {
    border-color: #f97316;
    background: #fff7ed;
    color: #c2410c;
  }

  input[type='radio'] {
    accent-color: #f97316;
    width: 16px;
    height: 16px;
  }
}

/* Email preview */
.email-preview-box {
  background: #f8fafc;
  border-radius: 0.625rem;
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.email-preview-row {
  display: flex;
  gap: 0;
  font-size: 0.875rem;
  border-bottom: 1px solid #e2e8f0;

  &:last-of-type {
    border-bottom: none;
  }
}

.email-preview-key {
  flex-shrink: 0;
  width: 80px;
  padding: 0.6rem 0.75rem;
  font-weight: 600;
  font-size: 0.8rem;
  color: #64728c;
  background: #f1f5f9;
  border-right: 1px solid #e2e8f0;
  display: flex;
  align-items: flex-start;
  padding-top: 0.7rem;
}

.email-preview-val {
  flex: 1;
  padding: 0.65rem 0.875rem;
  color: #1e293b;
  font-weight: 500;
}

.email-preview-body {
  flex: 1;
  margin: 0;
  padding: 0.65rem 0.875rem;
  font-family: inherit;
  font-size: 0.875rem;
  color: #374151;
  white-space: pre-wrap;
  word-break: break-word;
  line-height: 1.6;
}

.email-preview-hint {
  padding: 0.5rem 0.75rem;
  margin: 0;
  font-size: 0.76rem;
  color: #94a3b8;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
}

.email-preview-hint code {
  background: #e2e8f0;
  border-radius: 3px;
  padding: 1px 5px;
  margin: 0 2px;
  font-size: 0.76rem;
  color: #475569;
}

/* Test email */
.test-email-row {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.test-email-input {
  flex: 1;
  height: 38px;
  padding: 0 0.875rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-family: inherit;
  color: #1e293b;
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;

  &:focus {
    border-color: #f97316;
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.12);
  }

  &::placeholder {
    color: #cbd5e1;
  }
}

.test-email-result {
  margin-top: 0.75rem;
  font-size: 0.875rem;
  padding: 0.625rem 0.875rem;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.test-email-ok {
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
}

.test-email-err {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}
</style>
