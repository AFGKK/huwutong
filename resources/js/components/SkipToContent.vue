<template>
    <a
        class="skip-to-content"
        :href="target"
        @click.prevent="handleSkip"
        @focus="visible = true"
        @blur="visible = false"
        :class="{ 'skip-visible': visible }"
    >
        <slot>{{ t('a11y.skip_to_content') }}</slot>
    </a>
</template>

<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    target: {
        type: String,
        default: '#main-content',
    },
});

const visible = ref(false);

function handleSkip() {
    const el = document.querySelector(props.target);
    if (el) {
        el.setAttribute('tabindex', '-1');
        el.focus({ preventScroll: false });
        el.scrollIntoView({ behavior: 'smooth' });
    }
}
</script>

<style scoped>
.skip-to-content {
    position: fixed;
    top: -100%;
    left: 8px;
    z-index: 10000;
    padding: 8px 16px;
    background: #0f172a;
    color: #fff;
    border-radius: 0 0 4px 4px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    outline: 2px solid #fff;
    outline-offset: 2px;
    transition: top 0.1s ease-in;
}

.skip-to-content.skip-visible,
.skip-to-content:focus {
    top: 0;
}
</style>
