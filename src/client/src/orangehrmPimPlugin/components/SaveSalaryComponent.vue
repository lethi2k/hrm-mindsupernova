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
  <div class="orangehrm-horizontal-padding orangehrm-vertical-padding">
    <oxd-text tag="h6" class="orangehrm-main-title">
      Add Salary Component
    </oxd-text>
    <oxd-divider />
    <oxd-form :loading="isLoading" @submit-valid="onSave">
      <oxd-form-row>
        <oxd-grid :cols="3" class="orangehrm-full-width-grid">
          <oxd-grid-item>
            <oxd-input-field
              v-model="salaryComponent.name"
              :label="$t('pim.salary_component')"
              :rules="rules.name"
              required
            />
          </oxd-grid-item>
          <oxd-grid-item>
            <oxd-input-field
              v-model="salaryComponent.payFrequencyId"
              type="select"
              :label="$t('pim.pay_frequency')"
              :options="payFrequencies"
            />
          </oxd-grid-item>
          <oxd-grid-item>
            <oxd-input-field
              v-model="salaryComponent.salaryAmount"
              :label="$t('general.amount')"
              :rules="rules.salaryAmount"
              required
            />
          </oxd-grid-item>
        </oxd-grid>
      </oxd-form-row>

      <oxd-form-row>
        <oxd-grid :cols="3" class="orangehrm-full-width-grid">
          <oxd-grid-item class="--span-column-2">
            <oxd-input-field
              v-model="salaryComponent.comment"
              type="textarea"
              :label="$t('general.comments')"
              :rules="rules.comment"
            />
          </oxd-grid-item>
        </oxd-grid>
      </oxd-form-row>

      <oxd-form-row class="directdeposit-form-header">
        <oxd-text class="directdeposit-form-header-text" tag="p">
          {{ $t('pim.include_direct_deposit_details') }}
        </oxd-text>
        <oxd-switch-input v-model="includeDirectDeposit" />
      </oxd-form-row>

      <oxd-form-row v-if="includeDirectDeposit">
        <oxd-grid :cols="3" class="orangehrm-full-width-grid">
          <oxd-grid-item>
            <oxd-input-field
              v-model="directDeposit.directDepositAccount"
              :label="$t('pim.account_number')"
              :rules="rules.directDepositAccount"
              required
            />
          </oxd-grid-item>
          <oxd-grid-item>
            <oxd-input-field
              v-model="directDeposit.directDepositAccountType"
              type="select"
              :label="$t('pim.account_type')"
              :rules="rules.directDepositAccountType"
              :options="accountTypes"
              required
            />
          </oxd-grid-item>
          <oxd-grid-item v-if="showOptionalAccountType">
            <oxd-input-field
              v-model="accountType"
              :label="$t('pim.please_specify')"
              :rules="rules.accountType"
              required
            />
          </oxd-grid-item>
        </oxd-grid>

        <oxd-grid :cols="3" class="orangehrm-full-width-grid">
          <oxd-grid-item>
            <oxd-input-field
              v-model="directDeposit.directDepositRoutingNumber"
              :label="$t('pim.routing_number')"
              :rules="rules.directDepositRoutingNumber"
              required
            />
          </oxd-grid-item>
          <oxd-grid-item>
            <oxd-input-field
              v-model="directDeposit.directDepositAmount"
              :label="$t('general.amount')"
              :rules="rules.directDepositAmount"
              required
            />
          </oxd-grid-item>
        </oxd-grid>
      </oxd-form-row>

      <oxd-form-actions>
        <required-text />
        <oxd-button
          type="button"
          display-type="ghost"
          :label="$t('general.cancel')"
          @click="onCancel"
        />
        <submit-button />
      </oxd-form-actions>
    </oxd-form>
  </div>
  <oxd-divider />
</template>

<script>
import {
  digitsOnlyWithDecimalPoint,
  maxCurrency,
  required,
  shouldNotExceedCharLength,
} from '@ohrm/core/util/validation/rules';
import {OxdSwitchInput} from '@ohrm/oxd';

const salComponentModel = {
  name: '',
  salaryAmount: '',
  comment: '',
  payFrequencyId: null,
};

const directDepositModel = {
  directDepositAccount: '',
  directDepositAccountType: null,
  directDepositRoutingNumber: '',
  directDepositAmount: '',
};

export default {
  name: 'SaveSalaryComponent',

  components: {
    'oxd-switch-input': OxdSwitchInput,
  },

  props: {
    http: {
      type: Object,
      required: true,
    },
    payFrequencies: {
      type: Array,
      default: () => [],
    },
    accountTypes: {
      type: Array,
      default: () => [],
    },
  },

  emits: ['close'],

  data() {
    return {
      isLoading: false,
      includeDirectDeposit: false,
      salaryComponent: {...salComponentModel},
      directDeposit: {...directDepositModel},
      accountType: '',
      rules: {
        name: [required, shouldNotExceedCharLength(100)],
        salaryAmount: [
          required,
          digitsOnlyWithDecimalPoint,
          maxCurrency(1000000000),
        ],
        comment: [shouldNotExceedCharLength(250)],
        directDepositAccount: [required, shouldNotExceedCharLength(100)],
        directDepositAccountType: [required],
        accountType: [required, shouldNotExceedCharLength(20)],
        directDepositRoutingNumber: [
          required,
          shouldNotExceedCharLength(9),
          digitsOnlyWithDecimalPoint,
        ],
        directDepositAmount: [
          required,
          digitsOnlyWithDecimalPoint,
          maxCurrency(1000000000),
        ],
      },
    };
  },

  computed: {
    showOptionalAccountType() {
      return this.directDeposit.directDepositAccountType?.id == 'OTHER';
    },
  },

  methods: {
    onSave() {
      this.isLoading = true;
      const accountType = this.showOptionalAccountType
        ? this.accountType
        : this.directDeposit.directDepositAccountType?.id;
      this.http
        .create({
          salaryComponent: this.salaryComponent.name,
          salaryAmount: this.salaryComponent.salaryAmount,
          payFrequencyId: this.salaryComponent.payFrequencyId?.id,
          comment: this.salaryComponent.comment
            ? this.salaryComponent.comment
            : null,
          addDirectDeposit: this.includeDirectDeposit,
          // Directdeposi fields
          directDepositAccount: this.includeDirectDeposit
            ? this.directDeposit.directDepositAccount
            : undefined,
          directDepositAccountType: this.includeDirectDeposit
            ? accountType
            : undefined,
          directDepositAmount: this.includeDirectDeposit
            ? this.directDeposit.directDepositAmount
            : undefined,
          directDepositRoutingNumber: this.includeDirectDeposit
            ? this.directDeposit.directDepositRoutingNumber
            : undefined,
        })
        .then(() => {
          return this.$toast.saveSuccess();
        })
        .then(() => {
          this.onCancel();
        });
    },
    onCancel() {
      this.$emit('close', true);
    },
  },
};
</script>

<style lang="scss" scoped>
.directdeposit-form-header {
  display: flex;
  padding: 1rem;
  &-text {
    font-size: 0.8rem;
    margin-right: 1rem;
  }
}
</style>
