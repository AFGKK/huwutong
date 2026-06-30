<template>
    <div class="translation-engine">
        <el-card shadow="hover" class="mb-4">
            <template #header>
                <div class="card-header">
                    <span><el-icon><Promotion /></el-icon> LLM 翻译引擎</span>
                    <el-tag type="success" size="small" v-if="memoryStats">记忆效率: {{ memoryStats.memory_efficiency }}%</el-tag>
                </div>
            </template>

            <el-alert
                title="翻译引擎使用 AI 大模型自动翻译缺失的翻译条目。每翻译一条都会产生 LLM API 调用费用，建议先选择特定语言和命名空间进行翻译。"
                type="info"
                show-icon
                :closable="false"
                class="mb-4"
            />

            <el-form :model="form" label-width="120px">
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="目标语言">
                            <el-select v-model="form.locale" placeholder="选择语言" clearable filterable style="width: 100%">
                                <el-option
                                    v-for="l in languages"
                                    :key="l.locale"
                                    :label="`${l.name} (${l.locale})`"
                                    :value="l.locale"
                                    :disabled="l.is_default"
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="命名空间">
                            <el-select v-model="form.namespace_id" placeholder="全部命名空间" clearable filterable style="width: 100%">
                                <el-option
                                    v-for="ns in namespaces"
                                    :key="ns.id"
                                    :label="ns.label"
                                    :value="ns.id"
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8" class="flex-center">
                        <el-button
                            type="primary"
                            @click="handleBatchTranslate"
                            :loading="translating"
                            :disabled="!form.locale"
                            size="default"
                        >
                            <el-icon><Promotion /></el-icon>
                            {{ translating ? '翻译中...' : '开始批量翻译' }}
                        </el-button>
                    </el-col>
                </el-row>
            </el-form>
        </el-card>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ dashboardStats?.total_auto_translated || 0 }}</div>
                    <div class="stat-label">AI 翻译条目</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ dashboardStats?.total_missing || 0 }}</div>
                    <div class="stat-label">待翻译条目</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ memoryStats?.unique_source_texts || 0 }}</div>
                    <div class="stat-label">翻译记忆条目</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ dashboardStats?.total_published || 0 }}</div>
                    <div class="stat-label">已发布翻译</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 翻译结果 -->
        <el-card shadow="hover" v-if="translateResult">
            <template #header>
                <div class="card-header">
                    <span>翻译结果</span>
                    <el-button size="small" @click="translateResult = null">清除</el-button>
                </div>
            </template>
            <el-result
                :icon="translateResult.failed > 0 ? 'warning' : 'success'"
                :title="translateResult.message"
                :sub-title="`已翻译: ${translateResult.translated} | 失败: ${translateResult.failed} | 跳过: ${translateResult.skipped}`"
            >
                <template #extra>
                    <el-button type="primary" @click="refreshDashboard">刷新面板</el-button>
                </template>
            </el-result>
        </el-card>

        <!-- 各语言翻译进度 -->
        <el-card shadow="hover" class="mt-4" v-if="perLanguage?.length">
            <template #header>
                <div class="card-header">
                    <span>各语言翻译进度</span>
                </div>
            </template>
            <el-table :data="perLanguage" stripe size="small" max-height="400">
                <el-table-column label="语言" min-width="120">
                    <template #default="{ row }">
                        {{ row.name }} <span class="text-muted">({{ row.native_name }})</span>
                    </template>
                </el-table-column>
                <el-table-column label="总数" prop="total" width="80" />
                <el-table-column label="已发布" prop="published" width="80" />
                <el-table-column label="缺失" prop="missing" width="80" />
                <el-table-column label="进度" width="180">
                    <template #default="{ row }">
                        <el-progress :percentage="row.progress" :color="progressColor(row.progress)" />
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="160">
                    <template #default="{ row }">
                        <el-button
                            size="small"
                            type="primary"
                            plain
                            :disabled="row.missing === 0 || row.locale === defaultLocale"
                            @click="quickTranslate(row.locale)"
                        >
                            翻译缺失
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Promotion } from '@element-plus/icons-vue';
import i18nApi from '@/api/i18n';

const emit = defineEmits(['refresh']);

const form = ref({ locale: '', namespace_id: null });
const languages = ref([]);
const namespaces = ref([]);
const dashboardStats = ref(null);
const perLanguage = ref([]);
const memoryStats = ref(null);
const translating = ref(false);
const translateResult = ref(null);
const defaultLocale = ref('');

function progressColor(pct) {
    if (pct >= 80) return '#67c23a';
    if (pct >= 50) return '#e6a23c';
    return '#f56c6c';
}

async function loadData() {
    try {
        const [dashRes, memoryRes] = await Promise.all([
            i18nApi.getDashboard(),
            i18nApi.getMemoryStats(),
        ]);

        const dashData = dashRes.data.data;
        dashboardStats.value = dashData.stats;
        languages.value = dashData.languages || [];
        namespaces.value = dashData.namespaces || [];
        perLanguage.value = dashData.per_language || [];

        memoryStats.value = memoryRes.data.data;

        // Set default locale
        const defaultLang = languages.value.find(l => l.is_default);
        if (defaultLang) {
            defaultLocale.value = defaultLang.locale;
        }
    } catch (e) {
        ElMessage.error('加载翻译引擎数据失败');
    }
}

async function handleBatchTranslate() {
    if (!form.value.locale) {
        ElMessage.warning('请选择目标语言');
        return;
    }

    if (form.value.locale === defaultLocale.value) {
        ElMessage.info('源语言无需翻译');
        return;
    }

    try {
        await ElMessageBox.confirm(
            `确定要使用 AI 翻译引擎批量翻译 "${form.value.locale}" 语言的缺失条目吗？`,
            '确认批量翻译',
            {
                confirmButtonText: '开始翻译',
                cancelButtonText: '取消',
                type: 'info',
            }
        );
    } catch {
        return;
    }

    translating.value = true;
    translateResult.value = null;

    try {
        const { data } = await i18nApi.engineTranslateMissing({
            locale: form.value.locale,
            namespace_id: form.value.namespace_id || undefined,
        });
        translateResult.value = data.data;
        ElMessage.success(data.message || '批量翻译完成');
        emit('refresh');
        loadData();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '批量翻译失败');
    } finally {
        translating.value = false;
    }
}

async function quickTranslate(locale) {
    form.value.locale = locale;
    form.value.namespace_id = null;
    await handleBatchTranslate();
}

async function refreshDashboard() {
    translateResult.value = null;
    await loadData();
}

onMounted(() => loadData());
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header span {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
}

.flex-center {
    display: flex;
    align-items: center;
    padding-top: 4px;
}

.stat-card {
    text-align: center;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #409eff;
    line-height: 1.2;
}

.stat-label {
    font-size: 13px;
    color: #909399;
    margin-top: 4px;
}

.text-muted {
    color: #909399;
    font-size: 12px;
}
</style>
