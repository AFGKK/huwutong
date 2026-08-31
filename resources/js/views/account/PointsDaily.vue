<template>
    <div class="points-daily-page">
        <el-card shadow="never" class="points-summary-card">
            <div class="points-summary-row">
                <div class="points-balance-block">
                    <div class="points-balance-label">{{ t('points_daily.balance') }}</div>
                    <div class="points-balance-value">{{ pointsBalance }}</div>
                </div>
                <el-button type="primary" plain @click="openPointsHistory">{{ t('points_daily.history') }}</el-button>
            </div>
        </el-card>

        <el-card shadow="never" class="daily-card" style="margin-top:16px">
            <template #header>
                <div class="daily-card-header">
                    <span>{{ t('points_daily.checkin') }}</span>
                    <span class="daily-card-sub">{{ t('points_daily.checkin_sub') }}</span>
                </div>
            </template>
            <div class="my-section-header">{{ t('points_daily.today_tasks') }}</div>
            <div class="daily-task-item" v-for="task in dailyTasks" :key="task.key">
                <div class="daily-task-info">
                    <span class="daily-task-icon">{{ task.done ? '✓' : '○' }}</span>
                    <span class="daily-task-label" :class="{ 'task-done': task.done }">{{ task.label }}</span>
                    <span class="daily-task-reward">{{ t('points_daily.reward', { n: task.reward }) }}</span>
                </div>
                <div class="daily-task-bar-wrap">
                    <div class="daily-task-bar" :style="{ width: (task.progress / task.total * 100) + '%' }"></div>
                </div>
                <div class="daily-task-progress">{{ task.progress }}/{{ task.total }}</div>
            </div>
            <div class="daily-summary">
                {{ t('points_daily.earned_summary') }} <strong>{{ dailyEarned }}</strong> / <strong>{{ dailyMax }}</strong>
            </div>
        </el-card>

        <PointsHistory v-model="pointsHistoryVisible" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import apiClient from '@/api/client'
import PointsHistory from '@/components/PointsHistory.vue'

const { t } = useI18n()
const pointsBalance = ref(0)
const pointsHistoryVisible = ref(false)

const dailyTasks = computed(() => [
    { key: 'read', label: t('points_daily.tasks.read'), reward: 5, total: 3, progress: taskProgress.value.read, done: taskProgress.value.read >= 3 },
    { key: 'comment', label: t('points_daily.tasks.comment'), reward: 3, total: 1, progress: taskProgress.value.comment, done: taskProgress.value.comment >= 1 },
    { key: 'share', label: t('points_daily.tasks.share'), reward: 2, total: 1, progress: taskProgress.value.share, done: taskProgress.value.share >= 1 },
    { key: 'tip', label: t('points_daily.tasks.tip'), reward: 5, total: 1, progress: taskProgress.value.tip, done: taskProgress.value.tip >= 1 },
])
const taskProgress = ref({ read: 0, comment: 0, share: 0, tip: 0 })
const dailyMax = 15
const dailyEarned = computed(() =>
    dailyTasks.value.filter(task => task.done).reduce((sum, task) => sum + task.reward, 0)
)

async function loadPointsBalance() {
    try {
        const res = await apiClient.get('/points/balance')
        pointsBalance.value = res.data?.data?.balance || 0
    } catch {
        pointsBalance.value = 0
    }
}

function openPointsHistory() {
    pointsHistoryVisible.value = true
}

onMounted(loadPointsBalance)
</script>

<style scoped>
.points-summary-card :deep(.el-card__body) { padding: 20px 24px; }
.points-summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}
.points-balance-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.points-balance-value { font-size: 28px; font-weight: 700; color: #e6a23c; }

.daily-card-header {
    display: flex;
    align-items: baseline;
    gap: 10px;
    font-weight: 600;
}
.daily-card-sub { font-size: 12px; color: #909399; font-weight: 400; }

.my-section-header {
    font-size: 13px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 10px;
    padding-bottom: 6px;
    border-bottom: 1px solid #f0f0f0;
}
.daily-task-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
    font-size: 13px;
}
.daily-task-info {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
    flex: 1;
}
.daily-task-icon { font-size: 14px; flex-shrink: 0; }
.daily-task-label { flex: 1; }
.task-done { text-decoration: line-through; color: #909399; }
.daily-task-reward { font-size: 11px; color: #e6a23c; font-weight: 600; flex-shrink: 0; }
.daily-task-bar-wrap {
    width: 60px;
    height: 6px;
    background: #f0f0f0;
    border-radius: 3px;
    overflow: hidden;
    flex-shrink: 0;
}
.daily-task-bar {
    height: 100%;
    background: linear-gradient(90deg, #e6a23c, #f56c6c);
    border-radius: 3px;
    transition: width 0.3s;
}
.daily-task-progress { font-size: 11px; color: #909399; min-width: 28px; text-align: right; }
.daily-summary {
    text-align: center;
    font-size: 12px;
    color: #909399;
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px solid #f0f0f0;
}
.daily-summary strong { color: #e6a23c; }
</style>
