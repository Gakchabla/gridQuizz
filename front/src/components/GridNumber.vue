<script setup>
defineProps({
  number: { type: Number, required: true },
  color: { type: String, required: true },
  disabled: { type: Boolean, default: false },
})
defineEmits(['click'])
</script>

<template>
  <button
    type="button"
    class="grid-number"
    :class="{ 'is-disabled': disabled, 'is-rainbow': color === 'rainbow' }"
    :style="color === 'rainbow' ? {} : { backgroundColor: color }"
    :disabled="disabled"
    @click="$emit('click')"
  >
    <span v-if="disabled">✓</span>
    <span v-else>{{ number }}</span>
  </button>
</template>

<style scoped>
.grid-number {
  aspect-ratio: 1;
  font-size: 1.75rem;
  font-weight: 800;
  color: #fff;
  border: none;
  border-radius: var(--radius-sm);
  cursor: pointer;
  box-shadow:
    0 6px 14px rgba(0, 0, 0, 0.35),
    inset 0 1px 0 rgba(255, 255, 255, 0.15);
  transition:
    transform 0.12s ease,
    box-shadow 0.12s ease,
    filter 0.2s ease;
}

.grid-number:hover:not(:disabled) {
  transform: translateY(-3px) scale(1.03);
  filter: brightness(1.1);
}

.grid-number:active:not(:disabled) {
  transform: translateY(0) scale(0.97);
}

.grid-number.is-disabled {
  cursor: default;
  opacity: 0.35;
  box-shadow: none;
}
</style>
