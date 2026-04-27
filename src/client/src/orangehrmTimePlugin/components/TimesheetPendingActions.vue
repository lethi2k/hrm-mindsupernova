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
        :headers="headers"
        :items="displayItems"
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
    const employeeHttp = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/pim/employees',
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
    const getMonthRange = (dateString) => {
      const selectedDate = dateString
        ? parseDate(dateString, 'yyyy-MM-dd')
        : null;
      const baseDate = selectedDate ?? new Date();
      const monthStart = new Date(
        baseDate.getFullYear(),
        baseDate.getMonth(),
        1,
      );
      const monthEnd = new Date(
        baseDate.getFullYear(),
        baseDate.getMonth() + 1,
        0,
      );
      return {monthStart, monthEnd};
    };
    const getMonthPeriodLabel = (dateString) => {
      const {monthStart, monthEnd} = getMonthRange(dateString);
      return `${formatDate(monthStart, jsDateFormat, {
        locale,
      })} - ${formatDate(monthEnd, jsDateFormat, {locale})}`;
    };
    const formatEmployeeName = (employee) => {
      return [
        employee?.firstName ?? '',
        employee?.middleName ?? '',
        employee?.lastName ?? '',
      ]
        .join(' ')
        .replace(/\s+/g, ' ')
        .trim();
    };
    const calculateOvertime = (columns) => {
      if (!columns) {
        return '-';
      }
      let weightedOvertimeSeconds = 0;
      Object.entries(columns).forEach(([date, column]) => {
        const dateObj = parseDate(date, 'yyyy-MM-dd');
        if (!dateObj) {
          return;
        }
        const dayOfWeek = dateObj.getDay();
        const workedSeconds = getDurationInSeconds(column?.total);
        if (dayOfWeek === 0 || dayOfWeek === 6) {
          weightedOvertimeSeconds += workedSeconds * 2;
          return;
        }
        weightedOvertimeSeconds +=
          Math.max(0, workedSeconds - STANDARD_WORKDAY_SECONDS) * 1.5;
      });
      return formatSecondsToHoursLabel(weightedOvertimeSeconds);
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
    const notLoggedItems = ref([]);

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
              const sum = apiResponse.data?.meta?.sum;
              const totalLoggedSeconds = getDurationInSeconds(sum);
              item.totalDuration = calculateRegularDuration(columns);
              item.overtime = calculateOvertime(columns);
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

    const loadNotLoggedEmployees = async () => {
      if (props.filterHasLoggedTime !== false || !props.filterDate) {
        notLoggedItems.value = [];
        return;
      }

      const [employeesResponse, loggedResponse] = await Promise.all([
        employeeHttp.getAll({
          limit: 200,
          offset: 0,
          includeEmployees: 'currentAndPast',
        }),
        http.getAll({
          date: props.filterDate,
          hasLoggedTime: 'true',
          limit: 200,
          offset: 0,
        }),
      ]);

      const loggedEmpNumbers = new Set(
        (loggedResponse?.data?.data ?? []).map(
          (item) => item.employee?.empNumber,
        ),
      );

      const leaveHours = formatSecondsToDecimalHours(
        currentMonthWorkHours.value * 3600,
      );

      notLoggedItems.value = (employeesResponse?.data?.data ?? [])
        .filter((employee) => !loggedEmpNumbers.has(employee.empNumber))
        .map((employee) => ({
          id: `not-logged-${employee.empNumber}`,
          startDate: props.filterDate,
          empNumber: employee.empNumber,
          period: getMonthPeriodLabel(props.filterDate),
          employee: formatEmployeeName(employee),
          status: $t('time.not_submitted'),
          totalDuration: '0.00',
          overtime: '00:00',
          leaveHours,
        }));
    };

    watch(
      () => [props.filterDate, props.filterHasLoggedTime],
      () => {
        loadNotLoggedEmployees();
      },
      {immediate: true},
    );

    const displayItems = computed(() => {
      if (props.filterHasLoggedTime === false) {
        return notLoggedItems.value;
      }
      return response.value?.data ?? [];
    });

    const displayTotal = computed(() => displayItems.value.length);

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
      displayTotal,
      currentMonthWorkdays,
      currentMonthWorkHours,
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
          style: {flex: '20%'},
        },
        {
          name: 'status',
          title: this.$t('general.status'),
          style: {flex: '12%'},
        },
        {
          name: 'totalDuration',
          title: 'Working Hours',
          style: {flex: '14%'},
        },
        {
          name: 'overtime',
          title: 'Overtime',
          style: {flex: '10%'},
        },
        {
          name: 'leaveHours',
          title: 'Leave Hours',
          style: {flex: '10%'},
        },
        {
          name: 'actions',
          slot: 'footer',
          title: this.$t('general.actions'),
          style: {flex: '16%'},
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
