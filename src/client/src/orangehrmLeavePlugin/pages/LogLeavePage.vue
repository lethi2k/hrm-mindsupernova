<template>
  <div class="orangehrm-background-container">
    <div class="orangehrm-card-container">
      <oxd-text tag="h6" class="orangehrm-main-title">Log Leave</oxd-text>
      <oxd-divider />
      <oxd-form ref="formRef" :loading="isLoading" @submit-valid="onSave">
        <oxd-form-row>
          <oxd-grid :cols="1" class="orangehrm-full-width-grid">
            <oxd-grid-item>
              <oxd-input-field
                v-model="leave.type"
                type="select"
                :rules="rules.type"
                :options="leaveTypes"
                :label="$t('leave.leave_type')"
                required
              />
            </oxd-grid-item>
          </oxd-grid>
        </oxd-form-row>
        <oxd-form-row>
          <oxd-grid :cols="4" class="orangehrm-full-width-grid">
            <oxd-grid-item>
              <date-input
                v-model="leave.fromDate"
                :label="$t('general.from_date')"
                :rules="rules.fromDate"
                :years="yearsArray"
                required
              />
            </oxd-grid-item>
            <oxd-grid-item>
              <date-input
                v-model="leave.toDate"
                :label="$t('general.to_date')"
                :rules="rules.toDate"
                :years="yearsArray"
                required
              />
            </oxd-grid-item>
          </oxd-grid>
        </oxd-form-row>

        <oxd-form-row v-if="appliedLeaveDuration == 1">
          <oxd-grid :cols="4" class="orangehrm-full-width-grid">
            <leave-duration-input
              v-model:duration="leave.duration.type"
              v-model:fromTime="leave.duration.fromTime"
              v-model:toTime="leave.duration.toTime"
              :label="$t('general.duration')"
              :work-shift="workShift"
            ></leave-duration-input>
          </oxd-grid>
        </oxd-form-row>

        <oxd-form-row v-if="appliedLeaveDuration > 1">
          <oxd-grid :cols="4" class="orangehrm-full-width-grid">
            <oxd-grid-item>
              <oxd-input-field
                v-model="leave.partialOptions"
                type="select"
                :label="$t('leave.partial_days')"
                :options="partialOptions"
              />
            </oxd-grid-item>
            <leave-duration-input
              v-if="showDuration"
              v-model:duration="leave.duration.type"
              v-model:fromTime="leave.duration.fromTime"
              v-model:toTime="leave.duration.toTime"
              :partial="true"
              :label="$t('general.duration')"
              :work-shift="workShift"
            ></leave-duration-input>
            <leave-duration-input
              v-if="showStartDay"
              v-model:duration="leave.duration.type"
              v-model:fromTime="leave.duration.fromTime"
              v-model:toTime="leave.duration.toTime"
              :partial="true"
              :label="$t('leave.start_day')"
              :work-shift="workShift"
            ></leave-duration-input>
            <leave-duration-input
              v-if="showEndDay"
              v-model:duration="leave.endDuration.type"
              v-model:fromTime="leave.endDuration.fromTime"
              v-model:toTime="leave.endDuration.toTime"
              :partial="true"
              :label="$t('leave.end_day')"
              :work-shift="workShift"
            ></leave-duration-input>
          </oxd-grid>
        </oxd-form-row>
        <oxd-form-row>
          <oxd-grid :cols="2" class="orangehrm-full-width-grid">
            <oxd-grid-item>
              <oxd-input-field
                v-model="leave.comment"
                type="textarea"
                :label="$t('general.comments')"
                :rules="rules.comment"
              />
            </oxd-grid-item>
          </oxd-grid>
        </oxd-form-row>
        <oxd-divider />
        <oxd-form-actions>
          <required-text />
          <submit-button :label="$t('general.apply')" />
        </oxd-form-actions>
      </oxd-form>
    </div>
  </div>
</template>

<script>
import {
  required,
  validDateFormat,
  shouldNotExceedCharLength,
  endDateShouldBeAfterStartDate,
} from '@/core/util/validation/rules';
import {yearRange} from '@ohrm/core/util/helper/year-range';
import {diffInDays} from '@ohrm/core/util/helper/datefns';
import {APIService} from '@ohrm/core/util/services/api.service';
import LeaveDurationInput from '@/orangehrmLeavePlugin/components/LeaveDurationInput';
import useLeaveValidators from '@/orangehrmLeavePlugin/util/composable/useLeaveValidators';
import useForm from '@ohrm/core/util/composable/useForm';
import useDateFormat from '@/core/util/composable/useDateFormat';

const leaveModel = {
  type: null,
  fromDate: null,
  toDate: null,
  comment: '',
  partialOptions: null,
  duration: {
    type: {id: 1, label: 'Full Day', key: 'full_day'},
    fromTime: null,
    toTime: null,
  },
  endDuration: {
    type: null,
    fromTime: null,
    toTime: null,
  },
};

export default {
  name: 'LogLeavePage',
  components: {
    'leave-duration-input': LeaveDurationInput,
  },
  props: {
    leaveTypes: {
      type: Array,
      default: () => [],
    },
    workShift: {
      type: Object,
      default: () => ({}),
    },
  },
  setup() {
    const http = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/leave/leave-requests',
    );
    const {serializeBody} = useLeaveValidators(http);
    const {formRef, reset} = useForm();
    const {userDateFormat} = useDateFormat();
    return {
      http,
      reset,
      formRef,
      serializeBody,
      userDateFormat,
    };
  },
  data() {
    return {
      isLoading: false,
      leave: {...leaveModel},
      rules: {
        type: [required],
        fromDate: [required, validDateFormat(this.userDateFormat)],
        toDate: [
          required,
          validDateFormat(this.userDateFormat),
          endDateShouldBeAfterStartDate(
            () => this.leave.fromDate,
            this.$t('general.to_date_should_be_after_from_date'),
            {allowSameDate: true},
          ),
        ],
        comment: [shouldNotExceedCharLength(250)],
      },
      yearsArray: [...yearRange()],
      partialOptions: [
        {id: 1, label: this.$t('leave.all_days'), key: 'all'},
        {id: 2, label: this.$t('leave.start_day_only'), key: 'start'},
        {id: 3, label: this.$t('leave.end_day_only'), key: 'end'},
        {id: 4, label: this.$t('leave.start_and_end_day'), key: 'start_end'},
      ],
    };
  },
  computed: {
    appliedLeaveDuration() {
      return diffInDays(this.leave.fromDate, this.leave.toDate);
    },
    showDuration() {
      const id = this.leave.partialOptions?.id;
      return id && id === 1;
    },
    showStartDay() {
      const id = this.leave.partialOptions?.id;
      return id && (id === 2 || id === 4);
    },
    showEndDay() {
      const id = this.leave.partialOptions?.id;
      return id && (id === 3 || id === 4);
    },
  },
  watch: {
    appliedLeaveDuration: function (duration) {
      if (duration === 1) {
        this.leave.duration.type = {id: 1, label: 'Full Day', key: 'full_day'};
      } else {
        this.leave.duration.type = null;
      }
    },
    'leave.fromDate': function (fromDate) {
      if (!fromDate || this.leave.toDate) return;
      this.leave.toDate = fromDate;
    },
  },
  methods: {
    onSave() {
      this.isLoading = true;
      this.http
        .create(this.serializeBody(this.leave))
        .then(() => {
          this.$toast.saveSuccess();
          this.reset();
          this.leave = {...leaveModel};
        })
        .catch((error) => {
          // Error toast is handled by global API interceptor.
          this.http.setAbort();
          return error;
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
  },
};
</script>
