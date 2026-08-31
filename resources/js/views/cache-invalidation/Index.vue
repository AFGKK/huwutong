<template>
    <div class="cache-invalidation-page">
        <div class="page-header">
            <div>
                <h2>{{ t('cache_invalidation_page.title') }}</h2>
                <p class="text-muted">{{ t('cache_invalidation_page.subtitle') }}</p>
            </div>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading" :icon="Refresh">{{ t('actions.refresh') }}</el-button>
                <el-button type="primary" @click="showPushForm = true" :icon="Promotion">{{ t('cache_invalidation_page.manual_push') }}</el-button>
            </div>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t('cache_invalidation_page.stats.pending') }}</div><div class="metric-value warning">{{ stats.total_pending }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t('cache_invalidation_page.stats.published') }}</div><div class="metric-value success">{{ stats.total_published }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t('cache_invalidation_page.stats.failed') }}</div><div class="metric-value danger">{{ stats.total_failed }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t('cache_invalidation_page.stats.webhooks') }}</div><div class="metric-value">{{ stats.webhook_count }}</div></el-card></el-col>
        </el-row>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header><span><el-icon><Histogram /></el-icon> {{ t('cache_invalidation_page.by_type') }}</span></template>
                    <div class="dist-chart">
                        <div v-for="(count, type) in stats.by_type" :key="type" class="dist-row">
                            <span class="dist-label">{{ typeLabels[type] || type }}</span>
                            <el-progress :percentage="calcPct(count, stats)" :stroke-width="16">
                                <span class="dist-count">{{ count }}</span>
                            </el-progress>
                        </div>
                    </div>
                    <el-empty v-if="!Object.keys(stats.by_type || {}).length" :description="t('messages.no_data')" />
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header><span><el-icon><Connection /></el-icon> {{ t('cache_invalidation_page.by_channel') }}</span></template>
                    <div class="dist-chart">
                        <div v-for="(count, ch) in stats.by_channel" :key="ch" class="dist-row">
                            <span class="dist-label">{{ channelLabels[ch] || ch }}</span>
                            <el-progress :percentage="calcPct(count, stats)" :stroke-width="16">
                                <span class="dist-count">{{ count }}</span>
                            </el-progress>
                        </div>
                    </div>
                    <el-empty v-if="!Object.keys(stats.by_channel || {}).length" :description="t('messages.no_data')" />
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="mb-4">
            <template #header>
                <div class="card-header">
                    <span><el-icon><Link /></el-icon> {{ t('cache_invalidation_page.webhook_title') }}</span>
                    <el-button size="small" type="primary" @click="showWebhookForm = true">{{ t('cache_invalidation_page.add_webhook') }}</el-button>
                </div>
            </template>
            <el-table :data="webhooks" stripe v-loading="whLoading" size="small">
                <el-table-column prop="url" label="URL" min-width="300" show-overflow-tooltip />
                <el-table-column :label="t('cache_invalidation_page.cols.subscribed')" min-width="200">
                    <template #default="{row}">
                        <el-tag v-for="tp in (row.subscribed_types || [])" :key="tp" size="small" style="margin:2px">{{ typeLabels[tp] || tp }}</el-tag>
                        <span v-if="!row.subscribed_types?.length" class="text-muted">{{ t('cache_invalidation_page.all_types') }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('cache_invalidation_page.cols.active')" width="70">
                    <template #default="{row}"><el-icon :color="row.is_active ? '#67c23a' : '#c0c4cc'"><CircleCheck /></el-icon></template>
                </el-table-column>
                <el-table-column :label="t('cache_invalidation_page.cols.actions')" width="120" fixed="right">
                    <template #default="{row}">
                        <el-popconfirm :title="t('cache_invalidation_page.confirm_delete')" @confirm="deleteWebhook(row)">
                            <template #reference><el-button size="small" type="danger">{{ t('actions.delete') }}</el-button></template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!webhooks.length && !whLoading" :description="t('cache_invalidation_page.empty_webhooks')" />
        </el-card>

        <el-dialog v-model="showPushForm" :title="t('cache_invalidation_page.push_title')" width="500px">
            <el-form :model="pushForm" label-width="120px">
                <el-form-item :label="t('cache_invalidation_page.form.type')" :rules="[{required:true}]">
                    <el-select v-model="pushForm.type" style="width:100%">
                        <el-option v-for="(label, key) in typeLabels" :key="key" :label="label" :value="key" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('cache_invalidation_page.form.key')"><el-input v-model="pushForm.invalidation_key" :placeholder="t('cache_invalidation_page.form.key_ph')" /></el-form-item>
                <el-form-item :label="t('cache_invalidation_page.form.tenant')"><el-input-number v-model="pushForm.tenant_id" :min="0" style="width:100%" :placeholder="t('cache_invalidation_page.form.tenant_ph')" /></el-form-item>
                <el-form-item :label="t('cache_invalidation_page.form.context')"><el-input v-model="pushForm.context_text" type="textarea" :rows="3" :placeholder="t('cache_invalidation_page.form.context_ph')" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showPushForm = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="submitPush" :loading="pushLoading">{{ t('cache_invalidation_page.push') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="showWebhookForm" :title="t('cache_invalidation_page.webhook_dialog')" width="500px">
            <el-form :model="whForm" label-width="100px">
                <el-form-item label="URL" :rules="[{required:true}]"><el-input v-model="whForm.url" placeholder="https://your-server.com/cache/invalidation" /></el-form-item>
                <el-form-item :label="t('cache_invalidation_page.form.secret')"><el-input v-model="whForm.secret" :placeholder="t('cache_invalidation_page.form.secret_ph')" /></el-form-item>
                <el-form-item :label="t('cache_invalidation_page.form.subscribe')"><el-checkbox-group v-model="whForm.subscribed_types">
                    <el-checkbox v-for="(label, key) in typeLabels" :key="key" :label="key">{{ label }}</el-checkbox>
                </el-checkbox-group></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showWebhookForm = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="submitWebhook" :loading="whSaving">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Refresh, Promotion, Histogram, Connection, Link, CircleCheck } from '@element-plus/icons-vue'
import cacheInvalidationApi from '@/api/cacheInvalidation'

const { t } = useI18n()

const loading = ref(false)
const whLoading = ref(false)
const pushLoading = ref(false)
const whSaving = ref(false)
const showPushForm = ref(false)
const showWebhookForm = ref(false)

const stats = reactive({ total_pending: 0, total_published: 0, total_failed: 0, webhook_count: 0, by_type: {}, by_channel: {} })
const webhooks = ref([])
const pushForm = reactive({ type: 'license_status', invalidation_key: '', tenant_id: 0, context_text: '' })
const whForm = reactive({ url: '', secret: '', subscribed_types: [] })

const typeLabels = computed(() => ({
    license_status: t('cache_invalidation_page.types.license_status'),
    feature_flag: t('cache_invalidation_page.types.feature_flag'),
    product_config: t('cache_invalidation_page.types.product_config'),
    heartbeat: t('cache_invalidation_page.types.heartbeat'),
}))
const channelLabels = computed(() => ({
    reverb: 'WebSocket',
    webhook: 'Webhook',
    sse: 'SSE',
}))

onMounted(loadAll)

async function loadAll() {
    loading.value = true
    try { await Promise.all([loadStats(), loadWebhooks()]) } finally { loading.value = false }
}
async function loadStats() {
    try { const r = await cacheInvalidationApi.stats(); Object.assign(stats, r.data?.data || {}) } catch {}
}
async function loadWebhooks() {
    whLoading.value = true
    try { const r = await cacheInvalidationApi.listWebhooks(); webhooks.value = r.data?.data || [] } finally { whLoading.value = false }
}

function calcPct(count, s) {
    const total = (s.total_pending || 0) + (s.total_published || 0) + (s.total_failed || 0)
    return total > 0 ? Math.round((count / total) * 100) : 0
}

async function submitPush() {
    pushLoading.value = true
    try {
        const data = {
            type: pushForm.type,
            invalidation_key: pushForm.invalidation_key || `${pushForm.type}:manual-${Date.now()}`,
            tenant_id: pushForm.tenant_id || undefined,
            context: pushForm.context_text ? JSON.parse(pushForm.context_text) : { reason: 'manual' },
        }
        await cacheInvalidationApi.invalidate(data)
        ElMessage.success(t('cache_invalidation_page.messages.pushed'))
        showPushForm.value = false
        loadStats()
    } catch { ElMessage.error(t('cache_invalidation_page.messages.push_failed')) } finally { pushLoading.value = false }
}

async function submitWebhook() {
    whSaving.value = true
    try {
        await cacheInvalidationApi.storeWebhook(whForm)
        ElMessage.success(t('cache_invalidation_page.messages.created'))
        showWebhookForm.value = false
        loadWebhooks()
    } catch { ElMessage.error(t('cache_invalidation_page.messages.create_failed')) } finally { whSaving.value = false }
}

async function deleteWebhook(row) {
    await cacheInvalidationApi.destroyWebhook(row.id)
    ElMessage.success(t('cache_invalidation_page.messages.deleted'))
    loadWebhooks()
}
</script>

<style scoped>
.cache-invalidation-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.metric-card { padding: 8px; }
.metric-card .metric-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.metric-card .metric-value { font-size: 20px; font-weight: 700; }
.success { color: #67c23a; } .warning { color: #e6a23c; } .danger { color: #f56c6c; }
.text-muted { color: #c0c4cc; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.dist-chart { padding: 8px 0; }
.dist-row { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
.dist-label { min-width: 130px; font-size: 13px; }
.dist-count { font-size: 12px; font-weight: 600; }
</style>
