<script setup>
defineProps({
  color: { type: String, default: null },
})
</script>

<template>
  <span
    class="badge-dot"
    :class="{ 'is-rainbow': color === 'rainbow' }"
    :style="color === 'rainbow' ? {} : { backgroundColor: color }"
  ></span>
</template>
