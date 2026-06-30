<template>
    <div
        v-if="visible"
        class="a11y-shortcuts-overlay"
        role="dialog"
        aria-modal="true"
        :aria-label="'键盘快捷键帮助'"
        @click.self="close"
    >
        <FocusTrap :active="visible" @deactivate="close">
            <div class="a11y-shortcuts-panel" role="document" tabindex="-1">
                <div class="shortcuts-header">
                    <h2 id="shortcuts-title">键盘快捷键</h2>
                    <el-button
                        :icon="Close"
                        circle
                        size="small"
                        @click="close"
                        :aria-label="'关闭快捷键帮助'"
                    />
                </div>
                <div class="shortcuts-body">
                    <div v-for="(group, gIdx) in shortcutGroups" :key="gIdx" class="shortcut-group">
                        <h3 class="shortcut-group-title">{{ group.label }}</h3>
                        <div v-for="(sc, idx) in group.shortcuts" :key="idx" class="shortcut-row">
                            <kbd class="shortcut-key">{{ formatKey(sc) }}</kbd>
                            <span class="shortcut-desc">{{ sc.description || sc.label }}</span>
                        </div>
                    </div>
                </div>
                <div class="shortcuts-footer">
                    <p class="text-muted">按 <kbd>Shift</kbd> + <kbd>?</kbd> 打开此面板</p>
                </div>
            </div>
        </FocusTrap>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { KEYBOARD_SHORTCUTS } from '@/utils/a11y';
import { Close } from '@element-plus/icons-vue';
import FocusTrap from '@/components/FocusTrap.vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    customShortcuts: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const shortcutGroups = computed(() => {
    const groups = [
        {
            label: '全局快捷键',
            shortcuts: Object.values(KEYBOARD_SHORTCUTS),
        },
    ];
    if (props.customShortcuts.length) {
        groups.push({
            label: '页面快捷键',
            shortcuts: props.customShortcuts,
        });
    }
    return groups;
});

function formatKey(sc) {
    const parts = [];
    if (sc.ctrl) parts.push('Ctrl');
    if (sc.alt) parts.push('Alt');
    if (sc.shift) parts.push('Shift');
    if (sc.key === ' ') parts.push('Space');
    else parts.push(sc.key.toUpperCase());
    return parts.join(' + ');
}

function close() {
    emit('close');
}
</script>

<style scoped>
.a11y-shortcuts-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.a11y-shortcuts-panel {
    background: #fff;
    border-radius: 8px;
    max-width: 520px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.15);
}

.shortcuts-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #ebeef5;
}

.shortcuts-header h2 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.shortcuts-body {
    padding: 12px 20px;
}

.shortcut-group {
    margin-bottom: 16px;
}

.shortcut-group:last-child {
    margin-bottom: 0;
}

.shortcut-group-title {
    font-size: 13px;
    color: #909399;
    margin: 0 0 8px;
    font-weight: 600;
}

.shortcut-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 0;
}

.shortcut-key {
    display: inline-block;
    padding: 2px 8px;
    background: #f5f7fa;
    border: 1px solid #e4e7ed;
    border-radius: 4px;
    font-size: 12px;
    font-family: inherit;
    min-width: 60px;
    text-align: center;
    line-height: 1.6;
}

.shortcut-desc {
    font-size: 13px;
    color: #303133;
}

.shortcuts-footer {
    padding: 10px 20px;
    border-top: 1px solid #ebeef5;
}

.text-muted {
    color: #909399;
    font-size: 12px;
    margin: 0;
}

.text-muted kbd {
    padding: 1px 4px;
    background: #f5f7fa;
    border: 1px solid #e4e7ed;
    border-radius: 2px;
    font-size: 11px;
    font-family: inherit;
}
</style>
