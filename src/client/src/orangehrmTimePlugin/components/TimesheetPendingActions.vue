<!--
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
 -->

<template>
  <div class="orangehrm-paper-container">
    <div class="orangehrm-header-container">
      <oxd-text tag="h6" class="orangehrm-main-title">
        {{ $t('time.timesheets_pending_action') }}
      </oxd-text>
      <oxd-text type="subtitle-2">
        {{ `${standardWorkingDaysLabel}: ${currentMonthWorkdays}` }}
      </oxd-text>
      <oxd-text type="subtitle-2">
        {{ `${standardWorkingHoursLabel}: ${currentMonthWorkHours}:00` }}
      </oxd-text>
    </div>
    <table-header
      :selected="0"
      :total="displayTotal"
      :loading="isLoading"
    ></table-header>
    <div class="orangehrm-container">
      <oxd-card-table
        v-model:order="sortDefinition"
        :headers="headers"
        :items="sortedDisplayItems"
        :selectable="false"
        :clickable="false"
        :loading="isLoading"
        row-decorator="oxd-table-decorator-card"
      />
    </div>
    <div class="orangehrm-bottom-container">
      <oxd-pagination
        v-if="showPaginator"
        v-model:current="currentPage"
        :length="pages"
      />
    </div>
  </div>
</template>

<script>
import {computed, ref, watch} from 'vue';
import usePaginate from '@ohrm/core/util/composable/usePaginate';
import {APIService} from '@/core/util/services/api.service';
import {navigate} from '@ohrm/core/util/helper/navigation';
import usei18n from '@/core/util/composable/usei18n';
import useDateFormat from '@/core/util/composable/useDateFormat';
import {formatDate, parseDate} from '@/core/util/helper/datefns';
import useLocale from '@/core/util/composable/useLocale';

export default {
  name: 'TimesheetPendingActions',
  props: {
    filterDate: {
      type: String,
      required: false,
      default: null,
    },
    filterEmpNumber: {
      type: Number,
      required: false,
      default: null,
    },
    filterHasLoggedTime: {
      type: Boolean,
      required: false,
      default: null,
    },
  },

  setup(props) {
    const STANDARD_WORKDAY_SECONDS = 8 * 3600;
    const http = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/time/employees/timesheets/list',
    );
    const detailsHttp = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/time',
    );
    const {$t} = usei18n();
    const {jsDateFormat} = useDateFormat();
    const {locale} = useLocale();
    const currentMonthWorkdays = computed(() => {
      const selectedDate = props.filterDate
        ? parseDate(props.filterDate, 'yyyy-MM-dd')
        : null;
      const baseDate = selectedDate ?? new Date();
      const currentDate = new Date(
        baseDate.getFullYear(),
        baseDate.getMonth(),
        1,
      );
      const month = currentDate.getMonth();
      let workdays = 0;
      while (currentDate.getMonth() === month) {
        const day = currentDate.getDay();
        if (day !== 0 && day !== 6) {
          workdays += 1;
        }
        currentDate.setDate(currentDate.getDate() + 1);
      }
      return workdays;
    });
    const currentMonthWorkHours = computed(
      () => currentMonthWorkdays.value * 8,
    );
    const getStatusLabel = (status) => {
      const statuses = {
        Submitted: $t('time.submitted'),
        Rejected: $t('leave.rejected'),
        'Not Submitted': $t('time.not_submitted'),
        Approved: $t('time.approved'),
      };
      return statuses[status] ?? status;
    };
    const getDurationInSeconds = (total) => {
      const hours = Number(total?.hours ?? 0);
      const minutes = Number(total?.minutes ?? 0);
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
    const formatSecondsToDecimalHours = (seconds) => {
      return (seconds / 3600).toFixed(2);
    };
    const getEntryDurationInSeconds = (duration) => {
      if (!duration || typeof duration !== 'string') {
        return 0;
      }
      const [hours = '0', minutes = '0', seconds = '0'] = duration.split(':');
      return (
        Number(hours) * 3600 + Number(minutes) * 60 + Number(seconds || 0)
      );
    };
    const calculateOvertime = (rows) => {
      if (!Array.isArray(rows)) {
        return '-';
      }
      let overtimeSeconds = 0;
      rows.forEach((row) => {
        Object.entries(row?.dates ?? {}).forEach(([date, entry]) => {
          const dateObj = parseDate(date, 'yyyy-MM-dd');
          if (!dateObj) {
            return;
          }
          const dayOfWeek = dateObj.getDay();
          const workedSeconds = getEntryDurationInSeconds(entry?.duration);
          if (dayOfWeek === 0 || dayOfWeek === 6) {
            // Weekend logged time is treated as overtime.
            overtimeSeconds += workedSeconds;
            return;
          }
          overtimeSeconds += Math.max(0, workedSeconds - STANDARD_WORKDAY_SECONDS);
        });
      });
      return formatSecondsToHoursLabel(overtimeSeconds);
    };
    const calculateRegularDuration = (columns) => {
      if (!columns) {
        return '-';
      }
      let regularSeconds = 0;
      Object.entries(columns).forEach(([date, column]) => {
        const dateObj = parseDate(date, 'yyyy-MM-dd');
        if (!dateObj) {
          return;
        }
        const dayOfWeek = dateObj.getDay();
        if (dayOfWeek === 0 || dayOfWeek === 6) {
          return;
        }
        const workedSeconds = getDurationInSeconds(column?.total);
        regularSeconds += Math.min(workedSeconds, STANDARD_WORKDAY_SECONDS);
      });
      return formatSecondsToDecimalHours(regularSeconds);
    };
    const calculateLeaveHours = (columns) => {
      if (!columns) {
        return '-';
      }
      let leaveSeconds = 0;
      Object.entries(columns).forEach(([date, column]) => {
        const dateObj = parseDate(date, 'yyyy-MM-dd');
        if (!dateObj) {
          return;
        }
        const dayOfWeek = dateObj.getDay();
        if (dayOfWeek === 0 || dayOfWeek === 6) {
          return;
        }
        const workedSeconds = getDurationInSeconds(column?.total);
        leaveSeconds += Math.max(0, STANDARD_WORKDAY_SECONDS - workedSeconds);
      });
      return formatSecondsToDecimalHours(leaveSeconds);
    };

    const actionsNormalizer = (data) => {
      return data.map((item) => {
        const startDate = formatDate(parseDate(item.startDate), jsDateFormat, {
          locale,
        });
        const endDate = formatDate(parseDate(item.endDate), jsDateFormat, {
          locale,
        });
        const empName = `${item.employee?.firstName} ${item.employee?.middleName} ${item.employee?.lastName}`;
        if (item.employee?.terminationId) {
          empName + ` (${$t('general.past_employee')})`;
        }
        return {
          id: item.id,
          startDate: item.startDate,
          empNumber: item.employee.empNumber,
          period: `${startDate} - ${endDate}`,
          employee: empName,
          status: getStatusLabel(item.status?.name),
          projectCount: '-',
          totalDuration: '-',
          overtime: '-',
          leaveHours: '-',
        };
      });
    };

    const query = ref({
      date: props.filterDate,
      empNumber: props.filterEmpNumber,
      hasLoggedTime:
        props.filterHasLoggedTime === null
          ? undefined
          : String(props.filterHasLoggedTime),
    });
    const sortDefinition = ref({
      projectCount: 'DEFAULT',
    });

    const {
      showPaginator,
      currentPage,
      total,
      pages,
      pageSize,
      response,
      isLoading,
      execQuery,
    } = usePaginate(http, {
      query,
      normalizer: actionsNormalizer,
      prefetch: false,
    });
    watch(
      () => [
        props.filterDate,
        props.filterEmpNumber,
        props.filterHasLoggedTime,
      ],
      ([filterDate, filterEmpNumber, filterHasLoggedTime]) => {
        query.value = {
          date: filterDate,
          empNumber: filterEmpNumber,
          hasLoggedTime:
            filterHasLoggedTime === null
              ? undefined
              : String(filterHasLoggedTime),
        };
        currentPage.value = 1;
        execQuery();
      },
      {immediate: true},
    );

    watch(
      () => response.value?.data,
      async (timesheets) => {
        if (!timesheets?.length) {
          return;
        }
        await Promise.allSettled(
          timesheets.map(async (item) => {
            try {
              const apiResponse = await detailsHttp.request({
                method: 'GET',
                url: `/api/v2/time/employees/timesheets/${item.id}/entries`,
              });
              const columns = apiResponse.data?.meta?.columns;
              const rows = apiResponse.data?.data;
              const sum = apiResponse.data?.meta?.sum;
              const totalLoggedSeconds = getDurationInSeconds(sum);
              const uniqueProjectIds = new Set(
                (rows ?? []).map((row) => row?.project?.id).filter(Boolean),
              );
              item.projectCount = uniqueProjectIds.size;
              item.totalDuration = calculateRegularDuration(columns);
              item.overtime = calculateOvertime(rows);
              item.leaveHours = calculateLeaveHours(columns);
              if (totalLoggedSeconds <= 0) {
                item.totalDuration = '0.00';
              }
            } catch (error) {
              item.totalDuration = '-';
              item.overtime = '-';
              item.leaveHours = '-';
            }
          }),
        );
      },
      {
        immediate: true,
      },
    );

    const displayItems = computed(() => response.value?.data ?? []);
    const sortedDisplayItems = computed(() => {
      const order = sortDefinition.value.projectCount;
      if (order !== 'ASC' && order !== 'DESC') {
        return displayItems.value;
      }
      return [...displayItems.value].sort((a, b) => {
        const first = Number(a?.projectCount ?? 0);
        const second = Number(b?.projectCount ?? 0);
        return order === 'ASC' ? first - second : second - first;
      });
    });

    const displayTotal = computed(() => total.value ?? 0);

    return {
      http,
      showPaginator,
      currentPage,
      isLoading,
      total,
      pages,
      pageSize,
      execQuery,
      items: response,
      displayItems,
      sortedDisplayItems,
      displayTotal,
      currentMonthWorkdays,
      currentMonthWorkHours,
      sortDefinition,
    };
  },
  data() {
    return {
      headers: [
        {
          name: 'employee',
          slot: 'title',
          title: this.$t('general.employee_name'),
          style: {flex: '26%'},
        },
        {
          name: 'period',
          title: this.$t('time.timesheet_period'),
          style: {flex: '16%'},
        },
        {
          name: 'projectCount',
          title: 'Project Count',
          sortField: 'projectCount',
          style: {flex: '10%'},
        },
        {
          name: 'status',
          title:
            this.$t('general.status') === 'general.status'
              ? 'Status'
              : this.$t('general.status'),
          style: {flex: '10%'},
        },
        {
          name: 'totalDuration',
          title: 'Working Hours',
          style: {flex: '12%'},
        },
        {
          name: 'overtime',
          title: 'Overtime',
          style: {flex: '8%'},
        },
        {
          name: 'leaveHours',
          title: 'Leave Hours',
          style: {flex: '8%'},
        },
        {
          name: 'actions',
          slot: 'footer',
          title: this.$t('general.actions'),
          style: {flex: '10%'},
          cellType: 'oxd-table-cell-actions',
          cellConfig: {
            view: {
              onClick: this.onClickView,
              component: 'oxd-button',
              props: {
                label: this.$t('general.view'),
                displayType: 'text',
                size: 'medium',
              },
            },
          },
        },
      ],
    };
  },
  computed: {
    standardWorkingDaysLabel() {
      const translated = this.$t('time.standard_working_days_month');
      return translated === 'time.standard_working_days_month'
        ? 'Standard Working Days (Month)'
        : translated;
    },
    standardWorkingHoursLabel() {
      const translated = this.$t('time.standard_working_hours_month');
      return translated === 'time.standard_working_hours_month'
        ? 'Standard Working Hours (Month)'
        : translated;
    },
  },

  methods: {
    onClickView(item) {
      navigate(
        '/time/viewTimesheet/employeeId/{empNumber}',
        {empNumber: item.empNumber},
        {startDate: item.startDate},
      );
    },
  },
};
</script>

<style lang="scss" scoped>
::v-deep(.card-footer-slot) {
  .oxd-table-cell-actions {
    justify-content: flex-end;
  }
  .oxd-table-cell-actions > * {
    margin: 0 !important;
  }
}
</style>
