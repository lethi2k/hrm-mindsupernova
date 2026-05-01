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
  <div class="orangehrm-background-container">
    <div class="orangehrm-card-container">
      <oxd-text tag="h6" class="orangehrm-main-title">
        {{ $t('time.select_employee') }}
      </oxd-text>
      <oxd-divider />
      <oxd-form @submit-valid="viewTimesheet">
        <oxd-form-row>
          <oxd-grid :cols="2" class="orangehrm-full-width-grid">
            <oxd-grid-item>
              <employee-autocomplete
                v-model="employee"
                :rules="rules.employee"
                :params="{
                  includeEmployees: 'currentAndPast',
                }"
              />
            </oxd-grid-item>
            <oxd-grid-item>
              <oxd-label :label="monthLabel" />
              <div class="month-year-picker">
                <oxd-input-field
                  v-model="selectedMonth"
                  type="select"
                  :options="months"
                  :rules="rules.selectedMonth"
                />
                <oxd-input-field
                  v-model="selectedYear"
                  type="input"
                  :rules="rules.selectedYear"
                  placeholder="Year"
                />
              </div>
            </oxd-grid-item>
            <oxd-grid-item>
              <oxd-input-field
                v-model="logTimeStatus"
                type="select"
                :options="logTimeStatusOptions"
                :label="logTimeStatusLabel"
              />
            </oxd-grid-item>
          </oxd-grid>
        </oxd-form-row>

        <oxd-divider />
        <oxd-form-actions>
          <required-text />
          <submit-button :label="$t('general.view')" />
        </oxd-form-actions>
      </oxd-form>
    </div>
    <br />

    <timesheet-pending-actions
      :filter-date="appliedDate"
      :filter-emp-number="appliedEmpNumber"
      :filter-has-logged-time="appliedHasLoggedTime"
    ></timesheet-pending-actions>
  </div>
</template>

<script>
import {
  required,
  shouldNotExceedCharLength,
  validSelection,
} from '@/core/util/validation/rules';
import {navigate} from '@ohrm/core/util/helper/navigation';
import useLocale from '@/core/util/composable/useLocale';
import EmployeeAutocomplete from '@/core/components/inputs/EmployeeAutocomplete';
import TimesheetPendingActions from '@/orangehrmTimePlugin/components/TimesheetPendingActions.vue';

export default {
  components: {
    'employee-autocomplete': EmployeeAutocomplete,
    'timesheet-pending-actions': TimesheetPendingActions,
  },

  setup() {
    const {locale} = useLocale();
    return {locale};
  },

  data() {
    return {
      employee: null,
      selectedMonth: null,
      selectedYear: String(new Date().getFullYear()),
      logTimeStatus: null,
      appliedDate: null,
      appliedEmpNumber: null,
      appliedHasLoggedTime: true,
      rules: {
        employee: [shouldNotExceedCharLength(100), validSelection],
        selectedMonth: [required],
        selectedYear: [required, (v) => /^\d{4}$/.test(v) || 'Invalid year'],
      },
    };
  },
  computed: {
    monthLabel() {
      const translated = this.$t('general.month');
      return translated === 'general.month' ? 'Month' : translated;
    },
    logTimeStatusLabel() {
      return 'Log Time Status';
    },
    logTimeStatusOptions() {
      return [
        {id: 'logged', label: 'Logged'},
        {id: 'not-logged', label: 'Not Logged'},
      ];
    },
    months() {
      return Array.from({length: 12}, (_, i) => ({
        id: i + 1,
        label: this.locale.localize.month(i, {width: 'wide'}),
      }));
    },
    selectedDate() {
      const month = this.selectedMonth?.id;
      const year = parseInt(this.selectedYear);
      if (!month || !year || !/^\d{4}$/.test(this.selectedYear)) return null;
      return `${year}-${String(month).padStart(2, '0')}-01`;
    },
  },
  mounted() {
    const now = new Date();
    this.selectedMonth = this.months[now.getMonth()];
    this.selectedYear = String(now.getFullYear());
    this.logTimeStatus = this.logTimeStatusOptions[0];
    const query = new URLSearchParams(window.location.search);
    const queryDate = query.get('date');
    if (queryDate) {
      const d = new Date(queryDate);
      if (!isNaN(d.getTime())) {
        this.selectedMonth = this.months[d.getMonth()];
        this.selectedYear = String(d.getFullYear());
      }
    }
    this.appliedDate = this.selectedDate;
    const empNumber = query.get('empNumber');
    this.appliedEmpNumber = empNumber ? Number(empNumber) : null;
    const hasLoggedTime = query.get('hasLoggedTime');
    if (hasLoggedTime === 'false') {
      this.logTimeStatus = this.logTimeStatusOptions[1];
      this.appliedHasLoggedTime = false;
    } else {
      this.logTimeStatus = this.logTimeStatusOptions[0];
      this.appliedHasLoggedTime = true;
    }
  },

  methods: {
    viewTimesheet() {
      if (!this.selectedDate) {
        return;
      }
      navigate(
        '/time/viewEmployeeTimesheet',
        {},
        {
          date: this.selectedDate,
          empNumber: this.employee?.id ?? undefined,
          hasLoggedTime:
            this.logTimeStatus?.id === 'not-logged' ? 'false' : 'true',
        },
      );
    },
  },
};
</script>

<style scoped>
.month-year-picker {
  display: flex;
  gap: 8px;
  align-items: flex-start;
}
.month-year-picker > * {
  flex: 1;
}
</style>
