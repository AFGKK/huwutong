<template>
    <div class="domain-whitelist-container">
        <el-page-header :content="t('domain_whitelist_page.title')" @back="$router.push('/admin/dashboard')" />

        <!-- 搜索 License -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item :label="t('domain_whitelist_page.license_id')">
                    <el-input-number v-model="searchForm.license_id" :min="1" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadData" :loading="loading">{{ t('actions.search') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <template v-if="loaded">
            <!-- 统计卡片 -->
            <el-row :gutter="20" class="stat-cards">
                <el-col :span="4" v-for="card in statCards" :key="card.key">
                    <el-card shadow="hover">
                        <div class="stat-label">{{ card.label }}</div>
                        <div class="stat-value" :class="card.class">{{ card.value }}</div>
                    </el-card>
                </el-col>
            </el-row>

            <el-tabs v-model="activeTab" class="main-tabs">
                <!-- 白名单管理 -->
                <el-tab-pane :label="t('domain_whitelist_page.tabs.list')" name="list">
                    <div class="section-header">
                        <h3>{{ t('domain_whitelist_page.list_title', { id: searchForm.license_id }) }}</h3>
                        <div>
                            <el-button size="small" @click="showBatchDialog = true">{{ t('domain_whitelist_page.btn_batch_add') }}</el-button>
                            <el-button size="small" type="primary" @click="showAddDialog = true">{{ t('domain_whitelist_page.btn_add_domain') }}</el-button>
                        </div>
                    </div>

                    <el-table :data="domains" v-loading="loading" stripe>
                        <el-table-column prop="domain" :label="t('domain_whitelist_page.columns.domain')" min-width="250">
                            <template #default="{ row }">
                                <code>{{ row.domain }}</code>
                                <el-tag v-if="row.is_wildcard" size="small" type="warning" class="tag-spacer">{{ t('domain_whitelist_page.wildcard_tag') }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="scope" :label="t('domain_whitelist_page.columns.scope')" width="130">
                            <template #default="{ row }">
                                <el-tag :type="scopeTagType(row.scope)" size="small">{{ scopeLabel(row.scope) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="status" :label="t('domain_whitelist_page.columns.status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="statusTagType(row.status)" size="small">
                                    {{ statusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="notes" :label="t('domain_whitelist_page.columns.notes')" min-width="150" show-overflow-tooltip />
                        <el-table-column prop="created_at" :label="t('domain_whitelist_page.columns.created_at')" width="170" />
                        <el-table-column :label="t('domain_whitelist_page.columns.actions')" width="120" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" type="danger" plain @click="handleRemove(row)">
                                    {{ t('actions.delete') }}
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- 验证日志 -->
                <el-tab-pane :label="t('domain_whitelist_page.tabs.logs')" name="logs">
                    <el-table :data="logs" v-loading="loadingLogs" stripe>
                        <el-table-column prop="domain" :label="t('domain_whitelist_page.columns.request_domain')" min-width="220" />
                        <el-table-column prop="result" :label="t('domain_whitelist_page.columns.result')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.result === 'passed' ? 'success' : 'danger'" size="small">
                                    {{ row.result === 'passed' ? t('domain_whitelist_page.result.passed') : t('domain_whitelist_page.result.blocked') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="ip_address" :label="t('domain_whitelist_page.columns.ip')" width="140" />
                        <el-table-column prop="reason" :label="t('domain_whitelist_page.columns.reason')" min-width="250" show-overflow-tooltip />
                        <el-table-column prop="created_at" :label="t('domain_whitelist_page.columns.time')" width="170" />
                    </el-table>
                </el-tab-pane>

                <!-- 域名验证检查 -->
                <el-tab-pane :label="t('domain_whitelist_page.tabs.verify')" name="verify">
                    <el-card>
                        <template #header>{{ t('domain_whitelist_page.verify_card_title') }}</template>
                        <el-form :model="verifyForm" label-width="120px">
                            <el-form-item :label="t('domain_whitelist_page.license_id')">
                                <el-input-number v-model="verifyForm.license_id" :min="1" />
                            </el-form-item>
                            <el-form-item :label="t('domain_whitelist_page.columns.domain')">
                                <el-input v-model="verifyForm.domain" :placeholder="t('domain_whitelist_page.domain_ph')" />
                            </el-form-item>
                            <el-form-item :label="t('domain_whitelist_page.columns.scope')">
                                <el-select v-model="verifyForm.scope">
                                    <el-option
                                        v-for="opt in verifyScopeOptions"
                                        :key="opt.value"
                                        :label="opt.label"
                                        :value="opt.value"
                                    />
                                </el-select>
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="handleVerify" :loading="verifying">{{ t('domain_whitelist_page.btn_verify') }}</el-button>
                            </el-form-item>
                        </el-form>
                        <el-alert
                            v-if="verifyResult !== null"
                            :type="verifyResult.passed ? 'success' : 'error'"
                            show-icon
                        >
                            <template #title>
                                {{ verifyResult.passed ? t('domain_whitelist_page.verify_passed') : t('domain_whitelist_page.verify_failed') }}
                            </template>
                            {{ verifyResult.reason }}
                            <template v-if="verifyResult.matched">
                                <br>{{ t('domain_whitelist_page.matched_domain') }} <code>{{ verifyResult.matched }}</code>
                            </template>
                        </el-alert>
                    </el-card>
                </el-tab-pane>
            </el-tabs>
        </template>

        <!-- 添加域名 Dialog -->
        <el-dialog v-model="showAddDialog" :title="t('domain_whitelist_page.add_dialog_title')" width="500px">
            <el-form :model="addForm" label-width="120px" :rules="addRules" ref="addFormRef">
                <el-form-item :label="t('domain_whitelist_page.columns.domain')" prop="domain">
                    <el-input v-model="addForm.domain" :placeholder="t('domain_whitelist_page.domain_add_ph')" />
                    <div class="form-tip">{{ t('domain_whitelist_page.domain_wildcard_tip') }}</div>
                </el-form-item>
                <el-form-item :label="t('domain_whitelist_page.columns.scope')" prop="scope">
                    <el-select v-model="addForm.scope">
                        <el-option
                            v-for="opt in scopeOptions"
                            :key="opt.value"
                            :label="opt.label"
                            :value="opt.value"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('domain_whitelist_page.columns.notes')">
                    <el-input v-model="addForm.notes" type="textarea" :rows="2" maxlength="500" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAddDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleAdd" :loading="adding">{{ t('domain_whitelist_page.btn_add') }}</el-button>
            </template>
        </el-dialog>

        <!-- 批量添加 Dialog -->
        <el-dialog v-model="showBatchDialog" :title="t('domain_whitelist_page.batch_dialog_title')" width="550px">
            <el-form label-width="120px">
                <el-form-item :label="t('domain_whitelist_page.domain_list')">
                    <el-input
                        v-model="batchInput"
                        type="textarea"
                        :rows="6"
                        :placeholder="t('domain_whitelist_page.batch_placeholder')"
                    />
                    <div class="form-tip">{{ t('domain_whitelist_page.batch_tip') }}</div>
                </el-form-item>
                <el-form-item :label="t('domain_whitelist_page.columns.scope')">
                    <el-select v-model="batchScope">
                        <el-option
                            v-for="opt in scopeOptions"
                            :key="opt.value"
                            :label="opt.label"
                            :value="opt.value"
                        />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showBatchDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleBatchAdd" :loading="batchAdding">{{ t('domain_whitelist_page.btn_batch_submit') }}</el-button>
            </template>
            <!-- 批量结果 -->
            <el-table v-if="batchResults.length > 0" :data="batchResults" stripe size="small" max-height="300">
                <el-table-column prop="domain" :label="t('domain_whitelist_page.columns.domain')" />
                <el-table-column prop="success" :label="t('domain_whitelist_page.columns.result')" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.success ? 'success' : 'danger'" size="small">
                            {{ row.success ? t('domain_whitelist_page.result.success') : t('domain_whitelist_page.result.failed') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="status" :label="t('domain_whitelist_page.columns.status')" width="100">
                    <template #default="{ row }">
                        <span v-if="row.status">{{ row.status === 'active' ? statusLabel('active') : statusLabel('pending') }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="error" :label="t('domain_whitelist_page.columns.error')" min-width="200" show-overflow-tooltip />
            </el-table>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getDomainWhitelist, addDomain, batchAddDomains, removeDomain,
    verifyDomain, getDomainLogs, getDomainStats,
} from '@/api/domainWhitelist'

const { t } = useI18n()

const scopeKeys = ['both', 'activation', 'validation']
const statusKeys = ['active', 'pending', 'pending_remove', 'rejected', 'inactive', 'removed']

const searchForm = ref({ license_id: 1 })
const loading = ref(false)
const loaded = ref(false)
const domains = ref([])
const logs = ref([])
const loadingLogs = ref(false)
const stats = ref({ total: 0, active: 0, pending: 0, wildcard: 0, recent_passed: 0, recent_blocked: 0, max_domains: 20 })
const activeTab = ref('list')

const statCards = computed(() => [
    {
        key: 'total',
        label: t('domain_whitelist_page.stats.total_domains'),
        value: `${stats.value.active}/${stats.value.max_domains}`,
        class: '',
    },
    {
        key: 'wildcard',
        label: t('domain_whitelist_page.stats.wildcard'),
        value: stats.value.wildcard,
        class: '',
    },
    {
        key: 'pending',
        label: t('domain_whitelist_page.stats.pending'),
        value: stats.value.pending,
        class: 'text-warning',
    },
    {
        key: 'recent_passed',
        label: t('domain_whitelist_page.stats.recent_passed'),
        value: stats.value.recent_passed,
        class: 'text-success',
    },
    {
        key: 'recent_blocked',
        label: t('domain_whitelist_page.stats.recent_blocked'),
        value: stats.value.recent_blocked,
        class: 'text-danger',
    },
])

const scopeOptions = computed(() =>
    scopeKeys.map((value) => ({
        value,
        label: t(`domain_whitelist_page.scope.${value}`),
    })),
)

const verifyScopeOptions = computed(() => [
    { value: 'validation', label: t('domain_whitelist_page.scope.validation_short') },
    { value: 'activation', label: t('domain_whitelist_page.scope.activation_short') },
])

// 添加
const showAddDialog = ref(false)
const addForm = ref({ domain: '', scope: 'both', notes: '' })
const addRules = computed(() => ({
    domain: [{ required: true, message: t('domain_whitelist_page.rules.domain_required') }],
    scope: [{ required: true, message: t('domain_whitelist_page.rules.scope_required') }],
}))
const addFormRef = ref(null)
const adding = ref(false)

// 批量
const showBatchDialog = ref(false)
const batchInput = ref('')
const batchScope = ref('both')
const batchAdding = ref(false)
const batchResults = ref([])

// 验证
const verifyForm = ref({ license_id: 1, domain: '', scope: 'validation' })
const verifyResult = ref(null)
const verifying = ref(false)

watch(activeTab, (tab) => {
    if (tab === 'logs') fetchLogs()
})

function loadData() {
    loading.value = true
    loaded.value = false
    Promise.all([
        fetchDomains(),
        fetchStats(),
    ]).then(() => {
        loaded.value = true
    }).finally(() => {
        loading.value = false
    })
}

async function fetchDomains() {
    try {
        const res = await getDomainWhitelist(searchForm.value.license_id)
        domains.value = res.data?.domains || []
    } catch { domains.value = [] }
}

async function fetchStats() {
    try {
        const res = await getDomainStats(searchForm.value.license_id)
        if (res.data) stats.value = res.data
    } catch { /* ignore */ }
}

async function fetchLogs() {
    loadingLogs.value = true
    try {
        const res = await getDomainLogs(searchForm.value.license_id)
        logs.value = res.data?.logs || []
    } catch { logs.value = [] }
    loadingLogs.value = false
}

function scopeLabel(s) {
    return scopeKeys.includes(s) ? t(`domain_whitelist_page.scope.${s}`) : s
}

function scopeTagType(s) {
    return { activation: 'info', validation: 'primary', both: 'success' }[s] || 'info'
}

function statusLabel(s) {
    return statusKeys.includes(s) ? t(`domain_whitelist_page.status.${s}`) : s
}

function statusTagType(s) {
    return { active: 'success', pending: 'warning', pending_remove: 'warning', rejected: 'danger', inactive: 'info', removed: 'info' }[s] || 'info'
}

async function handleAdd() {
    const valid = await addFormRef.value.validate().catch(() => false)
    if (!valid) return
    adding.value = true
    try {
        const res = await addDomain(searchForm.value.license_id, addForm.value)
        ElMessage.success(res.message || t('domain_whitelist_page.messages.domain_added'))
        showAddDialog.value = false
        addForm.value = { domain: '', scope: 'both', notes: '' }
        fetchDomains()
        fetchStats()
    } catch (e) {
        ElMessage.error(e.message || t('domain_whitelist_page.messages.add_failed'))
    }
    adding.value = false
}

async function handleBatchAdd() {
    const lines = batchInput.value.split('\n').map(l => l.trim()).filter(Boolean)
    if (lines.length === 0) {
        ElMessage.warning(t('domain_whitelist_page.messages.batch_empty'))
        return
    }
    batchAdding.value = true
    try {
        const res = await batchAddDomains(searchForm.value.license_id, {
            domains: lines,
            scope: batchScope.value,
        })
        batchResults.value = res.data?.results || []
        const successCount = batchResults.value.filter(r => r.success).length
        ElMessage.success(t('domain_whitelist_page.messages.batch_done', { n: successCount }))
        fetchDomains()
        fetchStats()
    } catch (e) {
        ElMessage.error(e.message || t('domain_whitelist_page.messages.batch_failed'))
    }
    batchAdding.value = false
}

async function handleRemove(row) {
    try {
        await ElMessageBox.confirm(
            t('domain_whitelist_page.confirm.remove', { domain: row.domain }),
            t('actions.confirm'),
        )
        await removeDomain(searchForm.value.license_id, row.id)
        ElMessage.success(t('domain_whitelist_page.messages.removed'))
        fetchDomains()
        fetchStats()
    } catch { /* ignore */ }
}

async function handleVerify() {
    verifying.value = true
    verifyResult.value = null
    try {
        const res = await verifyDomain(verifyForm.value)
        verifyResult.value = res.data || { passed: true }
    } catch (e) {
        verifyResult.value = { passed: false, reason: e.message || t('domain_whitelist_page.messages.verify_failed') }
    }
    verifying.value = false
}
</script>

<style scoped>
.domain-whitelist-container {
    padding: 20px;
}

.search-card {
    margin-top: 20px;
    margin-bottom: 16px;
}

.stat-cards {
    margin-bottom: 16px;
}

.stat-label {
    font-size: 13px;
    color: #909399;
    margin-bottom: 6px;
    text-align: center;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
    text-align: center;
}

.text-success { color: #67c23a; }
.text-warning { color: #e6a23c; }
.text-danger { color: #f56c6c; }

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.section-header h3 {
    margin: 0;
}

.tag-spacer {
    margin-left: 6px;
}

.form-tip {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
}

.main-tabs {
    margin-top: 8px;
}
</style>
