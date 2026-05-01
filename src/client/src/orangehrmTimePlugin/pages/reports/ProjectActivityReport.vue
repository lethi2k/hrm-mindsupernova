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
  <reports-table
    module="time"
    name="project"
    :prefetch="project !== null"
    :filters="serializedFilters"
    :column-count="6"
  >
    <template #default="{generateReport}">
      <oxd-table-filter :filter-title="$t('time.project_report')">
        <oxd-form @submit-valid="generateReport">
          <oxd-form-row>
            <oxd-grid :cols="2" class="orangehrm-full-width-grid">
              <oxd-grid-item>
                <project-autocomplete
                  v-model="filters.project"
                  :rules="rules.project"
                  :label="$t('time.project_name')"
                  required
                />
              </oxd-grid-item>
            </oxd-grid>
          </oxd-form-row>

          <oxd-form-row>
            <oxd-grid :cols="4" class="orangehrm-full-width-grid">
              <oxd-grid-item>
                <oxd-input-field
                  v-model="filters.selectedMonth"
                  type="select"
                  :options="months"
                  :rules="rules.selectedMonth"
                  :label="$t('time.project_date_range')"
                />
              </oxd-grid-item>
              <oxd-grid-item>
                <oxd-input-field
                  v-model="filters.selectedYear"
                  type="input"
                  label="&nbsp"
                  :rules="rules.selectedYear"
                  placeholder="Year"
                />
              </oxd-grid-item>
              <oxd-grid-item class="orangehrm-switch-filter --span-column-2">
                <oxd-text class="orangehrm-switch-filter-text" tag="p">
                  {{ $t('time.only_include_approved_timesheets') }}
                </oxd-text>
                <oxd-switch-input v-model="filters.includeTimesheet" />
              </oxd-grid-item>
            </oxd-grid>
          </oxd-form-row>

          <oxd-divider />

          <oxd-form-actions>
            <required-text />
            <oxd-button
              type="submit"
              display-type="secondary"
              :label="$t('general.view')"
            />
          </oxd-form-actions>
        </oxd-form>
      </oxd-table-filter>
      <br />
    </template>

    <template #footer="{data}">
      {{ $t('time.total_duration') }}:
      {{ data.meta ? data.meta.sum.label : '0.00' }}
    </template>
  </reports-table>
</template>

<script>
import {computed, ref} from 'vue';
import {required, validSelection} from '@/core/util/validation/rules';
import ReportsTable from '@/core/components/table/ReportsTable';
import ProjectAutocomplete from '@/orangehrmTimePlugin/components/ProjectAutocomplete.vue';
import useLocale from '@/core/util/composable/useLocale';
import {OxdSwitchInput} from '@ohrm/oxd';

const defaultFilters = {
  project: null,
  selectedMonth: null,
  selectedYear: null,
  includeTimesheet: false,
};

export default {
  components: {
    'reports-table': ReportsTable,
    'oxd-switch-input': OxdSwitchInput,
    'project-autocomplete': ProjectAutocomplete,
  },

  props: {
    project: {
      type: Object,
      required: false,
      default: null,
    },
    fromDate: {
      type: String,
      required: false,
      default: null,
    },
    toDate: {
      type: String,
      required: false,
      default: null,
    },
    includeTimesheet: {
      type: Boolean,
      default: false,
    },
  },

  setup(props) {
    const {locale} = useLocale();
    const months = Array.from({length: 12}, (_, i) => ({
      id: i + 1,
      label: locale.localize.month(i, {width: 'wide'}),
    }));

    let selectedMonth = null;
    let selectedYear = String(new Date().getFullYear());
    if (props.fromDate) {
      const parsedDate = new Date(props.fromDate);
      if (!isNaN(parsedDate.getTime())) {
        selectedMonth = months[parsedDate.getMonth()];
        selectedYear = String(parsedDate.getFullYear());
      }
    }
    if (!selectedMonth) {
      const now = new Date();
      selectedMonth = months[now.getMonth()];
      selectedYear = String(now.getFullYear());
    }

    const filters = ref({
      ...defaultFilters,
      selectedMonth,
      selectedYear,
      includeTimesheet: props.includeTimesheet,
      ...(props.project && {project: props.project}),
    });

    const rules = {
      project: [required, validSelection],
      selectedMonth: [required, validSelection],
      selectedYear: [
        required,
        (value) => /^\d{4}$/.test(value) || 'Invalid year',
      ],
    };

    const serializedFilters = computed(() => {
      const month = filters.value.selectedMonth?.id;
      const year = parseInt(filters.value.selectedYear);
      let fromDate = null;
      let toDate = null;

      if (month && year && /^\d{4}$/.test(filters.value.selectedYear)) {
        const lastDay = new Date(year, month, 0).getDate();
        fromDate = `${year}-${String(month).padStart(2, '0')}-01`;
        toDate = `${year}-${String(month).padStart(2, '0')}-${String(
          lastDay,
        ).padStart(2, '0')}`;
      }

      return {
        projectId: filters.value.project?.id,
        fromDate,
        toDate,
        includeTimesheet: filters.value.includeTimesheet
          ? 'onlyApproved'
          : 'all',
      };
    });

    return {
      rules,
      filters,
      months,
      serializedFilters,
    };
  },
};
</script>

<style src="./time-reports.scss" lang="scss" scoped></style>
