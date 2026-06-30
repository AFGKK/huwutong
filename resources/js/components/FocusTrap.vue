<template>
    <div ref="container" v-bind="$attrs">
        <slot />
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { useFocusManager } from '@/composables/useA11y';

const props = defineProps({
    active: {
        type: Boolean,
        default: false,
    },
    initialFocus: {
        type: [String, Object],
        default: null,
    },
    returnFocus: {
        type: Boolean,
        default: true,
    },
    escapeDeactivates: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['deactivate']);

const container = ref(null);
const { trapFocus, focusElement } = useFocusManager();
let cleanupTrap = null;
let previouslyFocused = null;

function activate() {
    if (!container.value) return;

    previouslyFocused = document.activeElement;

    const opts = {};
    if (props.initialFocus) {
        opts.initialFocus = typeof props.initialFocus === 'string'
            ? container.value.querySelector(props.initialFocus)
            : props.initialFocus;
    }
    if (!props.returnFocus) {
        opts.returnFocusTo = false;
    }

    cleanupTrap = trapFocus(container.value, opts);
}

function deactivate() {
    if (cleanupTrap) {
        cleanupTrap();
        cleanupTrap = null;
    }
}

function handleEscape(e) {
    if (props.active && props.escapeDeactivates && e.key === 'Escape') {
        emit('deactivate');
    }
}

watch(() => props.active, (val) => {
    if (val) {
        activate();
    } else {
        deactivate();
    }
});

onMounted(() => {
    document.addEventListener('keydown', handleEscape);
    if (props.active) {
        activate();
    }
});

onUnmounted(() => {
    deactivate();
    document.removeEventListener('keydown', handleEscape);
});
</script>
