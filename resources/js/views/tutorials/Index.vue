<template>
    <div class="tutorials-page">
        <div class="page-header">
            <div class="header-left">
                <h2>入门教程</h2>
                <span class="header-subtitle">交互式学习指南，帮助您快速上手</span>
            </div>
            <el-button @click="fetchTutorials">
                <el-icon><Refresh /></el-icon> 刷新
            </el-button>
        </div>

        <el-row :gutter="16">
            <el-col v-for="item in tutorials" :key="item.tutorial?.id" :span="12" class="mb-4">
                <el-card shadow="hover" class="tutorial-card" @click="openTutorial(item.tutorial)">
                    <div class="tutorial-header">
                        <div class="tutorial-icon">
                            <el-icon :size="32" color="#409EFF"><Reading /></el-icon>
                        </div>
                        <div class="tutorial-meta">
                            <h3>{{ item.tutorial?.title }}</h3>
                            <el-tag size="small" type="info">{{ categoryLabel(item.tutorial?.category) }}</el-tag>
                        </div>
                    </div>
                    <p class="tutorial-desc">{{ item.tutorial?.description }}</p>

                    <div class="tutorial-footer">
                        <div class="step-count">
                            {{ item.tutorial?.steps?.length || 0 }} 个步骤
                        </div>
                        <div v-if="item.progress" class="progress-info">
                            <el-progress
                                v-if="item.progress.is_completed"
                                :percentage="100"
                                :stroke-width="4"
                                :width="40"
                                type="circle"
                                color="#67C23A"
                            />
                            <span v-else class="step-progress">
                                第 {{ (item.progress.current_step || 0) + 1 }} / {{ item.tutorial?.steps?.length || 0 }} 步
                            </span>
                        </div>
                        <div v-else class="progress-info">
                            <span class="not-started">未开始</span>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 教程详情弹窗 -->
        <el-dialog
            v-model="showTutorialDialog"
            :title="activeTutorial?.title || '教程'"
            width="650px"
            :close-on-click-modal="false"
        >
            <template v-if="activeTutorial">
                <div class="tutorial-steps">
                    <div class="step-counter">
                        <el-tag type="primary">
                            第 {{ currentTutorialStep + 1 }} / {{ activeTutorial.steps?.length || 0 }} 步
                        </el-tag>
                        <el-progress
                            :percentage="tutorialProgressPct"
                            :stroke-width="4"
                            style="flex:1; max-width: 300px"
                        />
                    </div>

                    <div class="step-content">
                        <div class="step-title">
                            <el-icon :size="24" color="#409EFF"><Reading /></el-icon>
                            <h3>{{ activeStep?.title }}</h3>
                        </div>
                        <div class="step-body">
                            <p>{{ activeStep?.content }}</p>
                        </div>
                    </div>

                    <div class="step-nav">
                        <el-button
                            :disabled="currentTutorialStep <= 0"
                            @click="prevTutorialStep"
                        >
                            上一步
                        </el-button>
                        <el-button
                            v-if="currentTutorialStep < (activeTutorial.steps?.length || 1) - 1"
                            type="primary"
                            @click="nextTutorialStep"
                        >
                            下一步
                        </el-button>
                        <el-button
                            v-else
                            type="success"
                            @click="completeTutorial"
                        >
                            {{ isTutorialCompleted ? '已完成' : '完成教程' }}
                        </el-button>
                    </div>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, Reading } from '@element-plus/icons-vue';
import onboardingApi from '@/api/onboarding';

const tutorials = ref([]);
const showTutorialDialog = ref(false);
const activeTutorial = ref(null);
const currentTutorialStep = ref(0);
const isTutorialCompleted = ref(false);
const tutorialProgress = ref(null);

const activeStep = computed(() => {
    if (!activeTutorial.value?.steps) return null;
    return activeTutorial.value.steps[currentTutorialStep.value] || null;
});

const tutorialProgressPct = computed(() => {
    if (!activeTutorial.value?.steps?.length) return 0;
    return Math.round(((currentTutorialStep.value + 1) / activeTutorial.value.steps.length) * 100);
});

async function fetchTutorials() {
    try {
        const res = await onboardingApi.tutorials();
        if (res.success) {
            tutorials.value = res.data || [];
        }
    } catch {
        ElMessage.error('加载教程失败');
    }
}

async function openTutorial(tutorial) {
    if (!tutorial) return;

    // 查找该教程的用户进度
    const tutItem = tutorials.value.find(t => t.tutorial?.id === tutorial.id);

    activeTutorial.value = tutorial;
    currentTutorialStep.value = tutItem?.progress?.current_step || 0;
    isTutorialCompleted.value = tutItem?.progress?.is_completed || false;
    tutorialProgress.value = tutItem?.progress;
    showTutorialDialog.value = true;
}

async function nextTutorialStep() {
    if (!activeTutorial.value) return;

    const nextStep = currentTutorialStep.value + 1;
    currentTutorialStep.value = nextStep;

    // 更新后端进度
    try {
        await onboardingApi.updateTutorialProgress(activeTutorial.value.id, nextStep);
    } catch { /* ignore */ }
}

async function prevTutorialStep() {
    if (currentTutorialStep.value > 0) {
        currentTutorialStep.value--;
    }
}

async function completeTutorial() {
    if (!activeTutorial.value || isTutorialCompleted.value) return;

    try {
        const lastStep = (activeTutorial.value.steps?.length || 1) - 1;
        await onboardingApi.updateTutorialProgress(activeTutorial.value.id, lastStep);
        isTutorialCompleted.value = true;
        ElMessage.success('教程已完成！');

        // 刷新列表
        await fetchTutorials();
    } catch {
        ElMessage.error('保存进度失败');
    }
}

function categoryLabel(cat) {
    const map = {
        getting_started: '快速入门',
        core_features: '核心功能',
        integration: '集成开发',
        advanced: '高级进阶',
    };
    return map[cat] || cat || '通用';
}

onMounted(() => {
    fetchTutorials();
});
</script>

<style scoped>
.tutorials-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.mb-4 { margin-bottom: 16px; }

.tutorial-card {
    cursor: pointer;
    transition: all 0.2s;
    height: 100%;
}
.tutorial-card:hover {
    border-color: var(--el-color-primary);
}

.tutorial-header {
    display: flex;
    gap: 14px;
    margin-bottom: 12px;
}
.tutorial-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--el-color-primary-light-9);
    border-radius: 12px;
    flex-shrink: 0;
}
.tutorial-meta h3 {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 4px;
}

.tutorial-desc {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    line-height: 1.5;
    margin: 0 0 16px;
}

.tutorial-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 12px;
    border-top: 1px solid var(--el-border-color-lighter);
}
.step-count { font-size: 13px; color: var(--el-text-color-secondary); }
.progress-info { display: flex; align-items: center; }
.step-progress { font-size: 13px; color: var(--el-color-primary); }
.not-started { font-size: 13px; color: var(--el-text-color-placeholder); }

/* Dialog */
.tutorial-steps { min-height: 300px; }

.step-counter {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}

.step-content {
    margin-bottom: 24px;
}

.step-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
}
.step-title h3 { margin: 0; font-size: 18px; font-weight: 600; }

.step-body p {
    font-size: 15px;
    line-height: 1.8;
    color: var(--el-text-color-regular);
    margin: 0;
}

.step-nav {
    display: flex;
    justify-content: space-between;
    padding-top: 20px;
    border-top: 1px solid var(--el-border-color-lighter);
}

:deep(.el-card__body) { padding: 20px; }
</style>
