<template>
    <el-card shadow="never" class="quick-start-panel">
        <template #header>
            <div class="card-header">
                <div class="header-left">
                    <span>快速启动</span>
                    <el-tag v-if="quickStart.total > 0" size="small" :type="quickStart.progress_pct === 100 ? 'success' : 'warning'">
                        {{ quickStart.completed }}/{{ quickStart.total }}
                    </el-tag>
                </div>
                <el-progress
                    v-if="quickStart.total > 0"
                    :percentage="quickStart.progress_pct"
                    :stroke-width="4"
                    :width="80"
                    type="circle"
                    :color="quickStart.progress_pct === 100 ? '#67C23A' : '#409EFF'"
                />
            </div>
        </template>

        <div v-if="!quickStart.items?.length" class="empty-state">
            暂无可用的快速启动项目
        </div>

        <div v-else class="quick-start-list">
            <div
                v-for="item in quickStart.items"
                :key="item.item_key"
                class="quick-start-item"
                :class="{ completed: item.is_completed }"
            >
                <div class="item-check">
                    <el-checkbox
                        :model-value="item.is_completed"
                        @change="() => handleComplete(item)"
                        :disabled="item.is_completed"
                    />
                </div>
                <div class="item-content">
                    <div class="item-title">{{ item.title }}</div>
                    <div class="item-desc">{{ item.description }}</div>
                </div>
                <div class="item-action">
                    <el-button
                        v-if="item.action_url && !item.is_completed"
                        text
                        size="small"
                        type="primary"
                        @click="$router.push(item.action_url)"
                    >
                        {{ item.action_label || '前往' }}
                    </el-button>
                    <el-icon v-if="item.is_completed" color="#67C23A" :size="18"><CircleCheck /></el-icon>
                </div>
            </div>
        </div>
    </el-card>
</template>

<script setup>
import { reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { CircleCheck } from '@element-plus/icons-vue';
import onboardingApi from '@/api/onboarding';

const quickStart = reactive({
    items: [],
    total: 0,
    completed: 0,
    progress_pct: 0,
});

async function fetchQuickStart() {
    try {
        const res = await onboardingApi.quickStartItems();
        if (res.success) {
            Object.assign(quickStart, res.data || {});
        }
    } catch { /* ignore */ }
}

async function handleComplete(item) {
    try {
        const res = await onboardingApi.completeQuickStartItem(item.item_key);
        if (res.success) {
            item.is_completed = true;
            quickStart.completed = (quickStart.completed || 0) + 1;
            quickStart.progress_pct = quickStart.total > 0
                ? Math.round((quickStart.completed / quickStart.total) * 100)
                : 0;
            ElMessage.success(`「${item.title}」已完成`);
        }
    } catch {
        ElMessage.error('操作失败');
    }
}

onMounted(() => {
    fetchQuickStart();
});
</script>

<style scoped>
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.header-left {
    display: flex;
    align-items: center;
    gap: 8px;
}
.header-left span { font-weight: 600; font-size: 14px; }

.quick-start-list {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.quick-start-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 8px;
    border-radius: 6px;
    transition: background 0.2s;
}
.quick-start-item:hover {
    background: var(--el-fill-color-lighter);
}
.quick-start-item.completed {
    opacity: 0.7;
}

.item-check { flex-shrink: 0; }

.item-content { flex: 1; min-width: 0; }
.item-title {
    font-size: 14px;
    font-weight: 500;
    color: var(--el-text-color-primary);
}
.quick-start-item.completed .item-title {
    text-decoration: line-through;
    color: var(--el-text-color-secondary);
}
.item-desc {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-top: 2px;
}

.item-action { flex-shrink: 0; }

.empty-state {
    text-align: center;
    padding: 24px 0;
    color: var(--el-text-color-placeholder);
    font-size: 13px;
}

:deep(.el-card__body) { padding: 16px; }
</style>
