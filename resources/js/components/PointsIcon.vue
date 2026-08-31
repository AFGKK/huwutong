<template>
  <span class="points-icon" :style="{ width: size + 'px', height: size + 'px' }" :aria-label="ariaLabel">
    <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <ellipse cx="12" cy="18" rx="9" ry="5" fill="#f59e0b" stroke="#d97706" stroke-width="0.8" />
      <ellipse cx="12" cy="17.5" rx="8" ry="4.2" fill="url(#coinGrad3)" />
      <ellipse cx="12" cy="12" rx="9" ry="5" fill="#f59e0b" stroke="#d97706" stroke-width="0.8" />
      <ellipse cx="12" cy="11.5" rx="8" ry="4.2" fill="url(#coinGrad2)" />
      <ellipse cx="12" cy="6" rx="9" ry="5" fill="#fbbf24" stroke="#d97706" stroke-width="0.8" />
      <ellipse cx="12" cy="5.5" rx="8" ry="4.2" fill="url(#coinGrad1)" />
      <text x="12" y="6.8" text-anchor="middle" font-size="5" font-weight="700" fill="#92400e" font-family="sans-serif">P</text>
      <ellipse cx="8" cy="4" rx="2.5" ry="1.2" fill="white" opacity="0.25" transform="rotate(-15 8 4)" />
      <defs>
        <linearGradient id="coinGrad1" x1="4" y1="1.5" x2="20" y2="9.5">
          <stop offset="0%" stop-color="#fcd34d" />
          <stop offset="50%" stop-color="#fbbf24" />
          <stop offset="100%" stop-color="#f59e0b" />
        </linearGradient>
        <linearGradient id="coinGrad2" x1="4" y1="7.5" x2="20" y2="15.5">
          <stop offset="0%" stop-color="#fbbf24" />
          <stop offset="50%" stop-color="#f59e0b" />
          <stop offset="100%" stop-color="#d97706" />
        </linearGradient>
        <linearGradient id="coinGrad3" x1="4" y1="13.5" x2="20" y2="21.5">
          <stop offset="0%" stop-color="#f59e0b" />
          <stop offset="50%" stop-color="#d97706" />
          <stop offset="100%" stop-color="#b45309" />
        </linearGradient>
      </defs>
    </svg>
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  size: { type: Number, default: 18 },
  label: { type: String, default: '' },
})

const { t } = useI18n()
const ariaLabel = computed(() => props.label || t('points.label'))
</script>

<style scoped>
.points-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  vertical-align: middle;
}
.points-icon svg {
  display: block;
}
</style>
