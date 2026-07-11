<template>
    <div class="points-daily-page">
        <!-- 积分概览 -->
        <el-card shadow="never" class="points-summary-card">
            <div class="points-summary-row">
                <div class="points-balance-block">
                    <div class="points-balance-label">当前积分</div>
                    <div class="points-balance-value">🪙 {{ pointsBalance }}</div>
                </div>
                <el-button type="primary" plain @click="openPointsHistory">交易记录</el-button>
            </div>
        </el-card>

        <!-- 每日签到 / 今日任务 -->
        <el-card shadow="never" class="daily-card" style="margin-top:16px">
            <template #header>
                <div class="daily-card-header">
                    <span>📅 每日签到</span>
                    <span class="daily-card-sub">完成今日任务赚取积分</span>
                </div>
            </template>
            <div class="my-section-header">🎯 今日任务</div>
            <div class="daily-task-item" v-for="task in dailyTasks" :key="task.key">
                <div class="daily-task-info">
                    <span class="daily-task-icon">{{ task.done ? '✅' : '⭕' }}</span>
                    <span class="daily-task-label" :class="{ 'task-done': task.done }">{{ task.label }}</span>
                    <span class="daily-task-reward">+{{ task.reward }}分</span>
                </div>
                <div class="daily-task-bar-wrap">
                    <div class="daily-task-bar" :style="{ width: (task.progress / task.total * 100) + '%' }"></div>
                </div>
                <div class="daily-task-progress">{{ task.progress }}/{{ task.total }}</div>
            </div>
            <div class="daily-summary">
                🎉 今日已得 <strong>{{ dailyEarned }}</strong> / <strong>{{ dailyMax }}</strong> 积分
            </div>
        </el-card>

        <PointsHistory v-model="pointsHistoryVisible" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import apiClient from '@/api/client'
import PointsHistory from '@/components/PointsHistory.vue'

const pointsBalance = ref(0)
const pointsHistoryVisible = ref(false)

const dailyTasks = ref([
    { key: 'read', label: '浏览3篇文章', reward: 5, total: 3, progress: 0, done: false },
    { key: 'comment', label: '发表1条评论', reward: 3, total: 1, progress: 0, done: false },
    { key: 'share', label: '分享1次内容', reward: 2, total: 1, progress: 0, done: false },
    { key: 'tip', label: '打赏1次', reward: 5, total: 1, progress: 0, done: false },
])
const dailyMax = 15
const dailyEarned = computed(() =>
    dailyTasks.value.filter(t => t.done).reduce((sum, t) => sum + t.reward, 0)
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
