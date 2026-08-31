<template>
    <div class="translation-engine">
        <el-card shadow="hover" class="mb-4">
            <template #header>
                <div class="card-header">
                    <span><el-icon><Promotion /></el-icon> {{ t('translation_engine_page.title') }}</span>
                    <el-tag type="success" size="small" v-if="memoryStats">{{ t('translation_engine_page.memory_efficiency', { pct: memoryStats.memory_efficiency }) }}</el-tag>
                </div>
            </template>

            <el-alert
                :title="t('translation_engine_page.alert')"
                type="info"
                show-icon
                :closable="false"
                class="mb-4"
            />

            <el-form :model="form" label-width="120px">
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="t('translation_engine_page.target_locale')">
                            <el-select v-model="form.locale" :placeholder="t('translation_engine_page.select_locale')" clearable filterable style="width: 100%">
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
                        <el-form-item :label="t('translation_engine_page.namespace')">
                            <el-select v-model="form.namespace_id" :placeholder="t('translation_engine_page.all_namespaces')" clearable filterable style="width: 100%">
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
                            {{ translating ? t('translation_engine_page.translating') : t('translation_engine_page.start_batch') }}
                        </el-button>
                    </el-col>
                </el-row>
            </el-form>
        </el-card>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ dashboardStats?.total_auto_translated || 0 }}</div>
                    <div class="stat-label">{{ t('translation_engine_page.stats.ai_translated') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ dashboardStats?.total_missing || 0 }}</div>
                    <div class="stat-label">{{ t('translation_engine_page.stats.missing') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ memoryStats?.unique_source_texts || 0 }}</div>
                    <div class="stat-label">{{ t('translation_engine_page.stats.memory') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ dashboardStats?.total_published || 0 }}</div>
                    <div class="stat-label">{{ t('translation_engine_page.stats.published') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" v-if="translateResult">
            <template #header>
                <div class="card-header">
                    <span>{{ t('translation_engine_page.result_title') }}</span>
                    <el-button size="small" @click="translateResult = null">{{ t('translation_engine_page.clear') }}</el-button>
                </div>
            </template>
            <el-result
                :icon="translateResult.failed > 0 ? 'warning' : 'success'"
                :title="translateResult.message"
                :sub-title="t('translation_engine_page.result_sub', { translated: translateResult.translated, failed: translateResult.failed, skipped: translateResult.skipped })"
            >
                <template #extra>
                    <el-button type="primary" @click="refreshDashboard">{{ t('translation_engine_page.refresh_panel') }}</el-button>
                </template>
            </el-result>
        </el-card>

        <el-card shadow="hover" class="mt-4" v-if="perLanguage?.length">
            <template #header>
                <div class="card-header">
                    <span>{{ t('translation_engine_page.progress_title') }}</span>
                </div>
            </template>
            <el-table :data="perLanguage" stripe size="small" max-height="400">
                <el-table-column :label="t('translation_engine_page.cols.language')" min-width="120">
                    <template #default="{ row }">
                        {{ row.name }} <span class="text-muted">({{ row.native_name }})</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('translation_engine_page.cols.total')" prop="total" width="80" />
                <el-table-column :label="t('translation_engine_page.cols.published')" prop="published" width="80" />
                <el-table-column :label="t('translation_engine_page.cols.missing')" prop="missing" width="80" />
                <el-table-column :label="t('translation_engine_page.cols.progress')" width="180">
                    <template #default="{ row }">
                        <el-progress :percentage="row.progress" :color="progressColor(row.progress)" />
                    </template>
                </el-table-column>
                <el-table-column :label="t('translation_engine_page.cols.actions')" width="160">
                    <template #default="{ row }">
                        <el-button
                            size="small"
                            type="primary"
                            plain
                            :disabled="row.missing === 0 || row.locale === defaultLocale"
                            @click="quickTranslate(row.locale)"
                        >
                            {{ t('translation_engine_page.translate_missing') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Promotion } from '@element-plus/icons-vue'
import i18nApi from '@/api/i18n'

const { t } = useI18n()
const emit = defineEmits(['refresh'])

const form = ref({ locale: '', namespace_id: null })
const languages = ref([])
const namespaces = ref([])
const dashboardStats = ref(null)
const perLanguage = ref([])
const memoryStats = ref(null)
const translating = ref(false)
const translateResult = ref(null)
const defaultLocale = ref('')

function progressColor(pct) {
    if (pct >= 80) return '#67c23a'
    if (pct >= 50) return '#e6a23c'
    return '#f56c6c'
}

async function loadData() {
    try {
        const [dashRes, memoryRes] = await Promise.all([
            i18nApi.getDashboard(),
            i18nApi.getMemoryStats(),
        ])

        const dashData = dashRes.data.data
        dashboardStats.value = dashData.stats
        languages.value = dashData.languages || []
        namespaces.value = dashData.namespaces || []
        perLanguage.value = dashData.per_language || []

        memoryStats.value = memoryRes.data.data

        const defaultLang = languages.value.find(l => l.is_default)
        if (defaultLang) {
            defaultLocale.value = defaultLang.locale
        }
    } catch (e) {
        ElMessage.error(t('translation_engine_page.messages.load_failed'))
    }
}

async function handleBatchTranslate() {
    if (!form.value.locale) {
        ElMessage.warning(t('translation_engine_page.messages.select_locale'))
        return
    }

    if (form.value.locale === defaultLocale.value) {
        ElMessage.info(t('translation_engine_page.messages.source_skip'))
        return
    }

    try {
        await ElMessageBox.confirm(
            t('translation_engine_page.messages.confirm_batch', { locale: form.value.locale }),
            t('translation_engine_page.messages.confirm_title'),
            {
                confirmButtonText: t('translation_engine_page.start_translate'),
                cancelButtonText: t('actions.cancel'),
                type: 'info',
            }
        )
    } catch {
        return
    }

    translating.value = true
    translateResult.value = null

    try {
        const { data } = await i18nApi.engineTranslateMissing({
            locale: form.value.locale,
            namespace_id: form.value.namespace_id || undefined,
        })
        translateResult.value = data.data
        ElMessage.success(data.message || t('translation_engine_page.messages.batch_done'))
        emit('refresh')
        loadData()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('translation_engine_page.messages.batch_failed'))
    } finally {
        translating.value = false
    }
}

async function quickTranslate(locale) {
    form.value.locale = locale
    form.value.namespace_id = null
    await handleBatchTranslate()
}

async function refreshDashboard() {
    translateResult.value = null
    await loadData()
}

onMounted(() => loadData())
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
    color: #0f172a;
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
