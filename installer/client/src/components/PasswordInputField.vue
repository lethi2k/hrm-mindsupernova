<template>
  <div class="orangehrm-password-input-field">
    <oxd-input-field
      v-bind="$attrs"
      :type="passwordType"
      :model-value="modelValue"
      class="orangehrm-password-input-field__input"
      @update:model-value="$emit('update:modelValue', $event)"
    />
    <button
      type="button"
      class="orangehrm-password-input-field__toggle"
      :disabled="disabled"
      :style="{top: `${toggleTop}px`}"
      :aria-label="toggleLabel"
      :title="toggleLabel"
      @click="togglePasswordVisibility"
    >
      <span
        class="orangehrm-password-input-field__icon"
        :class="{
          '--visible': isPasswordVisible,
        }"
      >
        <oxd-icon name="eye-fill" />
      </span>
    </button>
  </div>
</template>

<script>
import {OxdIcon} from '@ohrm/oxd';

export default {
  name: 'PasswordInputField',
  components: {
    'oxd-icon': OxdIcon,
  },
  inheritAttrs: false,
  props: {
    modelValue: {
      type: String,
      default: '',
    },
  },
  emits: ['update:modelValue'],
  data() {
    return {
      isPasswordVisible: false,
      toggleTop: 0,
    };
  },
  computed: {
    passwordType() {
      return this.isPasswordVisible ? 'input' : 'password';
    },
    disabled() {
      return Boolean(this.$attrs.disabled);
    },
    toggleLabel() {
      return this.isPasswordVisible ? 'Hide password' : 'Show password';
    },
  },
  mounted() {
    this.updateTogglePosition();
    window.addEventListener('resize', this.updateTogglePosition);
  },
  beforeUnmount() {
    window.removeEventListener('resize', this.updateTogglePosition);
  },
  methods: {
    updateTogglePosition() {
      this.$nextTick(() => {
        const input = this.$el?.querySelector('.oxd-input');
        if (!input) {
          return;
        }
        const hostRect = this.$el.getBoundingClientRect();
        const inputRect = input.getBoundingClientRect();
        this.toggleTop = inputRect.top - hostRect.top + inputRect.height / 2;
      });
    },
    togglePasswordVisibility() {
      if (this.disabled) {
        return;
      }
      this.isPasswordVisible = !this.isPasswordVisible;
    },
  },
};
</script>

<style lang="scss" scoped>
.orangehrm-password-input-field {
  position: relative;

  &__toggle {
    position: absolute;
    right: 0.75rem;
    transform: translateY(-50%);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: none;
    background: transparent;
    cursor: pointer;
    color: $oxd-primary-font-color;

    &:disabled {
      cursor: not-allowed;
      opacity: 0.55;
    }
  }

  &__icon {
    position: relative;
    display: inline-flex;
    align-items: center;

    &.--visible::after {
      content: '';
      position: absolute;
      left: -1px;
      top: 50%;
      width: 18px;
      border-top: 2px solid currentColor;
      transform: rotate(-35deg);
      transform-origin: center;
    }
  }

  ::v-deep(.orangehrm-password-input-field__input .oxd-input) {
    padding-right: 2.75rem;
  }
}
</style>
