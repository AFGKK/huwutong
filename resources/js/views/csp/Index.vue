<template>
    <div class="csp-page">
        <div class="page-header">
            <h2>{{ t('csp_page.title') }}</h2>
            <div class="header-actions">
                <el-button @click="activeTab = 'violations'" :type="activeTab === 'violations' ? 'default' : 'default'">
                    {{ t('csp_page.violations_btn', { n: violationCount }) }}
                </el-button>
                <el-button type="primary" @click="openDialog()">
                    <el-icon><Plus /></el-icon> {{ t('csp_page.add_policy') }}
                </el-button>
            </div>
        </div>

        <el-tabs v-model="activeTab">
            <el-tab-pane :label="t('csp_page.tabs.configs')" name="configs">
                <el-table :data="configs" v-loading="loading" stripe>
                    <el-table-column prop="name" :label="t('csp_page.cols.name')" min-width="120" />
                    <el-table-column :label="t('csp_page.cols.mode')" width="120">
                        <template #default="{ row }">
                            <el-tag :type="row.mode === 'report-only' ? 'warning' : 'success'" size="small">
                                {{ row.mode === 'report-only' ? t('csp_page.modes.report_only') : t('csp_page.modes.enforce') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('csp_page.cols.directives')" min-width="250">
                        <template #default="{ row }">
                            <div v-if="row.directives" class="directive-tags">
                                <el-tag v-for="(sources, directive) in row.directives" :key="directive"
                                    type="info" size="small" effect="plain">
                                    {{ directive }} {{ sources.join(' ') }}
                                </el-tag>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('csp_page.cols.route')" width="120">
                        <template #default="{ row }">
                            <el-tag v-if="row.route_pattern" type="info" size="small">{{ row.route_pattern }}</el-tag>
                            <span v-else class="text-muted">{{ t('csp_page.all_routes') }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="priority" :label="t('csp_page.cols.priority')" width="80" />
                    <el-table-column :label="t('csp_page.cols.status')" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
                                {{ row.is_active ? t('actions.enable') : t('actions.disable') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('csp_page.cols.actions')" width="160" fixed="right">
                        <template #default="{ row }">
                            <el-button size="small" @click="openDialog(row)">{{ t('actions.edit') }}</el-button>
                            <el-popconfirm :title="t('csp_page.confirm_delete')" @confirm="handleDelete(row)">
                                <template #reference>
                                    <el-button size="small" type="danger">{{ t('actions.delete') }}</el-button>
                                </template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <el-tab-pane :label="t('csp_page.tabs.violations')" name="violations">
                <el-row :gutter="16" style="margin-bottom: 16px;">
                    <el-col :span="8">
                        <el-statistic :title="t('csp_page.stats.total')" :value="stats?.total || 0" />
                    </el-col>
                    <el-col :span="8">
                        <el-statistic :title="t('csp_page.stats.last_24h')" :value="stats?.last_24h || 0" />
                    </el-col>
                </el-row>

                <el-table :data="violations" v-loading="violationsLoading" stripe>
                    <el-table-column prop="document_uri" :label="t('csp_page.cols.document_uri')" min-width="200" show-overflow-tooltip />
                    <el-table-column prop="blocked_uri" :label="t('csp_page.cols.blocked_uri')" min-width="200" show-overflow-tooltip />
                    <el-table-column prop="violated_directive" :label="t('csp_page.cols.violated')" width="160" />
                    <el-table-column prop="disposition" :label="t('csp_page.cols.disposition')" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.disposition === 'enforce' ? 'danger' : 'warning'" size="small">
                                {{ row.disposition === 'enforce' ? t('csp_page.dispositions.block') : t('csp_page.dispositions.report') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="created_at" :label="t('csp_page.cols.time')" width="170" />
                </el-table>
            </el-tab-pane>
        </el-tabs>

        <el-dialog v-model="dialogVisible" :title="editingId ? t('csp_page.edit_title') : t('csp_page.create_title')" width="800px">
            <el-form ref="formRef" :model="form" :rules="formRules" label-width="140px">
                <el-form-item :label="t('csp_page.cols.name')" prop="name">
                    <el-input v-model="form.name" :placeholder="t('csp_page.name_ph')" />
                </el-form-item>
                <el-form-item :label="t('csp_page.cols.mode')">
                    <el-radio-group v-model="form.mode">
                        <el-radio value="enforce">{{ t('csp_page.modes.enforce_full') }}</el-radio>
                        <el-radio value="report-only">{{ t('csp_page.modes.report_only_full') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t('csp_page.cols.directives')" prop="directives">
                    <div class="directive-form">
                        <div v-for="(entry, i) in directiveEntries" :key="i" class="directive-row">
                            <el-select v-model="entry.directive" filterable allow-create default-first-option
                                :placeholder="t('csp_page.directive_ph')" style="width: 200px">
                                <el-option v-for="d in knownDirectives" :key="d" :value="d" />
                            </el-select>
                            <el-input v-model="entry.sources" :placeholder="t('csp_page.sources_ph')" style="flex: 1" />
                            <el-button @click="removeDirective(i)" type="danger" :icon="Delete" circle />
                        </div>
                        <el-button @click="addDirective()" type="primary" link>
                            {{ t('csp_page.add_directive') }}
                        </el-button>
                    </div>
                    <div v-if="policyPreview" class="policy-preview">
                        <label>{{ t('csp_page.preview') }}</label>
                        <code>{{ policyPreview }}</code>
                    </div>
                </el-form-item>
                <el-form-item :label="t('csp_page.cols.route')">
                    <el-input v-model="form.route_pattern" :placeholder="t('csp_page.route_ph')" />
                </el-form-item>
                <el-form-item :label="t('csp_page.cols.priority')">
                    <el-input-number v-model="form.priority" :min="-100" :max="100" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Plus, Delete } from '@element-plus/icons-vue'
import {
    getCspConfigs, createCspConfig, updateCspConfig, deleteCspConfig,
    getCspViolations, getCspViolationStats,
} from '@/api/csp'
import { ElMessage } from 'element-plus'

const { t } = useI18n()

const loading = ref(false)
const saving = ref(false)
const configs = ref([])
const dialogVisible = ref(false)
const editingId = ref(null)
const formRef = ref(null)
const activeTab = ref('configs')

const knownDirectives = [
    'default-src', 'script-src', 'style-src', 'img-src', 'font-src',
    'connect-src', 'media-src', 'object-src', 'frame-src', 'frame-ancestors',
    'form-action', 'base-uri', 'manifest-src', 'worker-src', 'report-uri',
    'report-to', 'block-all-mixed-content', 'upgrade-insecure-requests',
]

const form = ref({
    name: '',
    mode: 'enforce',
    directives: {},
    route_pattern: '',
    priority: 0,
})

const directiveEntries = ref([{ directive: 'default-src', sources: "'self'" }])

const formRules = computed(() => ({
    name: [{ required: true, message: t('csp_page.validation.name'), trigger: 'blur' }],
}))

const policyPreview = computed(() => {
    const parts = []
    for (const entry of directiveEntries.value) {
        if (entry.directive && entry.sources) {
            parts.push(`${entry.directive} ${entry.sources}`)
        }
    }
    return parts.join('; ')
})

const violations = ref([])
const violationsLoading = ref(false)
const stats = ref(null)
const violationCount = computed(() => stats.value?.total || 0)

async function fetchConfigs() {
    loading.value = true
    try {
        const res = await getCspConfigs()
        configs.value = res.data || []
    } catch (e) {
        ElMessage.error(t('csp_page.messages.load_failed'))
    } finally {
        loading.value = false
    }
}

async function fetchViolations() {
    violationsLoading.value = true
    try {
        const res = await getCspViolations({ per_page: 50 })
        violations.value = res.data?.data || []
    } catch (e) {
        // ignore
    } finally {
        violationsLoading.value = false
    }
}

async function fetchStats() {
    try {
        const res = await getCspViolationStats()
        stats.value = res.data
    } catch (e) {
        // ignore
    }
}

function buildDirectivesFromEntries() {
    const directives = {}
    for (const entry of directiveEntries.value) {
        if (entry.directive) {
            directives[entry.directive] = entry.sources.split(/\s+/).filter(Boolean)
        }
    }
    return directives
}

function buildEntriesFromDirectives(directives) {
    if (!directives || Object.keys(directives).length === 0) {
        return [{ directive: 'default-src', sources: "'self'" }]
    }
    return Object.entries(directives).map(([directive, sources]) => ({
        directive,
        sources: Array.isArray(sources) ? sources.join(' ') : String(sources),
    }))
}

function addDirective() {
    directiveEntries.value.push({ directive: '', sources: '' })
}

function removeDirective(i) {
    directiveEntries.value.splice(i, 1)
}

function openDialog(row) {
    if (row) {
        editingId.value = row.id
        form.value.name = row.name || ''
        form.value.mode = row.mode || 'enforce'
        form.value.route_pattern = row.route_pattern || ''
        form.value.priority = row.priority ?? 0
        directiveEntries.value = buildEntriesFromDirectives(row.directives)
    } else {
        editingId.value = null
        form.value.name = ''
        form.value.mode = 'enforce'
        form.value.route_pattern = ''
        form.value.priority = 0
        directiveEntries.value = [{ directive: 'default-src', sources: "'self'" }]
    }
    dialogVisible.value = true
}

async function handleSave() {
    const valid = await formRef.value?.validate().catch(() => false)
    if (!valid) return

    const payload = {
        name: form.value.name,
        mode: form.value.mode,
        directives: buildDirectivesFromEntries(),
        route_pattern: form.value.route_pattern || null,
        priority: form.value.priority,
    }

    if (Object.keys(payload.directives).length === 0) {
        ElMessage.warning(t('csp_page.messages.need_directive'))
        return
    }

    saving.value = true
    try {
        if (editingId.value) {
            await updateCspConfig(editingId.value, payload)
            ElMessage.success(t('csp_page.messages.updated'))
        } else {
            await createCspConfig(payload)
            ElMessage.success(t('csp_page.messages.created'))
        }
        dialogVisible.value = false
        await fetchConfigs()
    } catch (e) {
        ElMessage.error(t('messages.failed'))
    } finally {
        saving.value = false
    }
}

async function handleDelete(row) {
    try {
        await deleteCspConfig(row.id)
        ElMessage.success(t('csp_page.messages.deleted'))
        await fetchConfigs()
    } catch (e) {
        ElMessage.error(t('csp_page.messages.delete_failed'))
    }
}

watch(activeTab, (tab) => {
    if (tab === 'violations') {
        fetchViolations()
        fetchStats()
    }
})

onMounted(() => {
    fetchConfigs()
    fetchStats()
})
</script>

<style scoped>
.csp-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.header-actions { display: flex; gap: 8px; }
.directive-tags { display: flex; flex-wrap: wrap; gap: 4px; }
.text-muted { color: #999; }
.directive-form { width: 100%; }
.directive-row { display: flex; gap: 8px; margin-bottom: 8px; align-items: center; }
.policy-preview { margin-top: 12px; }
.policy-preview label { font-size: 12px; color: #666; display: block; margin-bottom: 4px; }
.policy-preview code { background: #f5f7fa; padding: 8px 12px; border-radius: 4px; font-size: 12px; display: block; word-break: break-all; }
</style>
