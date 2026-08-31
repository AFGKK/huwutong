<template>
    <div class="webhook-filter-container">
        <el-page-header :content="t('webhook_filter_page.title')" @back="$router.push('/admin/dashboard')" />

        <!-- 说明 -->
        <el-alert
            :title="t('webhook_filter_page.info_alert')"
            type="info"
            show-icon
            :closable="false"
            class="alert-info"
        />

        <!-- 选择 Webhook 端点 -->
        <el-card class="endpoint-card">
            <el-form :model="searchForm" inline>
                <el-form-item :label="t('webhook_filter_page.endpoint_label')">
                    <el-select
                        v-model="selectedEndpointId"
                        :placeholder="t('webhook_filter_page.endpoint_ph')"
                        filterable
                        style="width:400px"
                        @change="loadFilters"
                    >
                        <el-option
                            v-for="ep in endpoints"
                            :key="ep.id"
                            :label="`${ep.name} (${ep.url})`"
                            :value="ep.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="showOptions = true">{{ t('webhook_filter_page.view_options') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 过滤器列表 -->
        <template v-if="selectedEndpointId">
            <div class="section-header">
                <h3>{{ t('webhook_filter_page.filter_list') }}</h3>
                <el-button type="primary" size="small" @click="openCreateDialog">{{ t('webhook_filter_page.create_filter') }}</el-button>
            </div>

            <el-table :data="filters" v-loading="loading" stripe :empty-text="t('webhook_filter_page.empty_filters')">
                <el-table-column prop="name" :label="t('webhook_filter_page.cols.name')" min-width="160" />
                <el-table-column :label="t('webhook_filter_page.cols.match_conditions')" min-width="200">
                    <template #default="{ row }">
                        <el-tag :type="row.match_type === 'all' ? 'primary' : 'warning'" size="small">
                            {{ row.match_type === 'all' ? t('webhook_filter_page.match_type.all') : t('webhook_filter_page.match_type.any') }}
                        </el-tag>
                        <span class="ml-2"> {{ t('webhook_filter_page.conditions_count', { n: row.conditions?.length || 0 }) }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('webhook_filter_page.cols.custom_template')" width="120" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.payload_template" type="success" size="small">{{ t('webhook_filter_page.template_enabled') }}</el-tag>
                        <span v-else class="text-gray">-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="priority" :label="t('webhook_filter_page.cols.priority')" width="80" align="center" />
                <el-table-column :label="t('webhook_filter_page.cols.status')" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                            {{ row.is_active ? t('webhook_filter_page.status.active') : t('webhook_filter_page.status.inactive') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('webhook_filter_page.cols.actions')" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="editFilter(row)">{{ t('actions.edit') }}</el-button>
                        <el-button size="small" type="danger" plain @click="handleDelete(row)">{{ t('actions.delete') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 批量测试区域 -->
            <el-card class="test-card">
                <template #header>
                    <span>{{ t('webhook_filter_page.batch_test.title') }}</span>
                    <el-button size="small" style="float:right" @click="addTestEvent">{{ t('webhook_filter_page.batch_test.add_event') }}</el-button>
                </template>
                <div v-for="(evt, idx) in testEvents" :key="idx" class="test-event-item">
                    <el-form :model="evt" inline>
                        <el-form-item :label="t('webhook_filter_page.cols.event_type')">
                            <el-select v-model="evt.event_type" style="width:220px">
                                <el-option
                                    v-for="et in filterOptions?.event_types || []"
                                    :key="et"
                                    :label="et"
                                    :value="et"
                                />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="Payload">
                            <el-input
                                v-model="evt.payload_raw"
                                type="textarea"
                                :rows="2"
                                :placeholder="t('webhook_filter_page.batch_test.payload_ph')"
                                style="width:400px"
                            />
                        </el-form-item>
                        <el-button type="danger" size="small" @click="removeTestEvent(idx)">{{ t('actions.delete') }}</el-button>
                    </el-form>
                </div>
                <el-button
                    type="primary"
                    @click="handleBatchTest"
                    :loading="testing"
                    :disabled="testEvents.length === 0"
                >
                    {{ t('webhook_filter_page.batch_test.run') }}
                </el-button>

                <!-- 测试结果 -->
                <div v-if="testResults.length > 0" class="test-results">
                    <h4>{{ t('webhook_filter_page.batch_test.results') }}</h4>
                    <el-table :data="testResults" stripe size="small">
                        <el-table-column prop="event_type" :label="t('webhook_filter_page.cols.event_type')" width="200" />
                        <el-table-column :label="t('webhook_filter_page.cols.match_result')" width="120">
                            <template #default="{ row }">
                                <el-tag :type="row.any_matched ? 'success' : 'danger'" size="small">
                                    {{ row.any_matched ? t('webhook_filter_page.matched') : t('webhook_filter_page.unmatched') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('webhook_filter_page.cols.match_detail')" min-width="300">
                            <template #default="{ row }">
                                <div v-for="mf in row.matched_filters" :key="mf.filter_id" class="match-detail">
                                    <el-tag :type="mf.matched ? 'success' : 'info'" size="small">
                                        {{ mf.matched ? t('webhook_filter_page.match_yes') : t('webhook_filter_page.match_no') }} {{ mf.filter_name }}
                                    </el-tag>
                                </div>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </el-card>
        </template>

        <!-- 新建/编辑过滤器 Dialog -->
        <el-dialog v-model="dialogVisible" :title="isEdit ? t('webhook_filter_page.dialog.edit') : t('webhook_filter_page.dialog.create')" width="700px">
            <el-form :model="form" label-width="120px" :rules="formRules" ref="formRef">
                <el-form-item :label="t('webhook_filter_page.form.name')" prop="name">
                    <el-input v-model="form.name" maxlength="100" />
                </el-form-item>
                <el-form-item :label="t('webhook_filter_page.form.match_type')" prop="match_type">
                    <el-radio-group v-model="form.match_type">
                        <el-radio value="all">{{ t('webhook_filter_page.match_type.all_full') }}</el-radio>
                        <el-radio value="any">{{ t('webhook_filter_page.match_type.any_full') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t('webhook_filter_page.form.priority')">
                    <el-input-number v-model="form.priority" :min="-100" :max="100" />
                    <span class="form-tip">{{ t('webhook_filter_page.form.priority_tip') }}</span>
                </el-form-item>
                <el-form-item :label="t('webhook_filter_page.form.conditions')" prop="conditions">
                    <div v-for="(cond, idx) in form.conditions" :key="idx" class="condition-row">
                        <el-row :gutter="8">
                            <el-col :span="7">
                                <el-select v-model="cond.field" :placeholder="t('webhook_filter_page.form.field_ph')" filterable style="width:100%">
                                    <el-option
                                        v-for="f in filterOptions?.fields || []"
                                        :key="f"
                                        :label="f"
                                        :value="f"
                                    />
                                </el-select>
                            </el-col>
                            <el-col :span="6">
                                <el-select v-model="cond.operator" :placeholder="t('webhook_filter_page.form.operator_ph')" style="width:100%">
                                    <el-option
                                        v-for="op in filterOptions?.operators || []"
                                        :key="op"
                                        :label="operatorLabel(op)"
                                        :value="op"
                                    />
                                </el-select>
                            </el-col>
                            <el-col :span="8">
                                <el-input
                                    v-if="!['exists','not_exists'].includes(cond.operator)"
                                    v-model="cond.value"
                                    :placeholder="t('webhook_filter_page.form.value_ph')"
                                    style="width:100%"
                                />
                                <span v-else class="form-tip" style="line-height:32px">{{ t('webhook_filter_page.form.no_value_needed') }}</span>
                            </el-col>
                            <el-col :span="3">
                                <el-button type="danger" size="small" @click="removeCondition(idx)" circle>
                                    -
                                </el-button>
                            </el-col>
                        </el-row>
                    </div>
                    <el-button size="small" @click="addCondition">+ {{ t('webhook_filter_page.form.add_condition') }}</el-button>
                </el-form-item>
                <el-form-item :label="t('webhook_filter_page.form.payload_template')">
                    <div class="template-info">
                        <span>{{ t('webhook_filter_page.form.template_info') }}</span>
                        <el-button size="small" @click="showTemplateHelp = !showTemplateHelp">
                            {{ showTemplateHelp ? t('webhook_filter_page.form.template_hide') : t('webhook_filter_page.form.template_help') }}
                        </el-button>
                    </div>
                    <div v-if="showTemplateHelp" class="template-vars">
                        <code v-for="v in filterOptions?.template_variables || []" :key="v" class="var-tag">
                            {{ v }}
                        </code>
                    </div>
                    <el-input
                        v-model="form.payloadTemplateStr"
                        type="textarea"
                        :rows="4"
                        :placeholder="t('webhook_filter_page.form.template_ph')"
                    />
                </el-form-item>
                <el-form-item :label="t('webhook_filter_page.form.enabled')">
                    <el-switch v-model="form.is_active" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- 筛选选项 Dialog -->
        <el-dialog v-model="showOptions" :title="t('webhook_filter_page.options_dialog.title')" width="600px">
            <el-tabs>
                <el-tab-pane :label="t('webhook_filter_page.options_dialog.tab_fields')">
                    <el-tag v-for="f in filterOptions?.fields || []" :key="f" class="option-tag">{{ f }}</el-tag>
                </el-tab-pane>
                <el-tab-pane :label="t('webhook_filter_page.options_dialog.tab_operators')">
                    <div v-for="op in filterOptions?.operators || []" :key="op" class="option-item">
                        <strong>{{ operatorLabel(op) }}</strong>
                        <code>{{ op }}</code>
                    </div>
                </el-tab-pane>
                <el-tab-pane :label="t('webhook_filter_page.options_dialog.tab_variables')">
                    <el-tag v-for="v in filterOptions?.template_variables || []" :key="v" class="option-tag">{{ v }}</el-tag>
                </el-tab-pane>
                <el-tab-pane :label="t('webhook_filter_page.options_dialog.tab_event_types')">
                    <el-tag v-for="et in filterOptions?.event_types || []" :key="et" class="option-tag">{{ et }}</el-tag>
                </el-tab-pane>
            </el-tabs>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getWebhookFilters, createWebhookFilter, updateWebhookFilter, deleteWebhookFilter,
    getWebhookFilterOptions, batchTestWebhookFilters,
} from '@/api/webhookFilter'
import webhookEndpoint from '@/api/webhookEndpoint'

const { t } = useI18n()

const endpoints = ref([])
const selectedEndpointId = ref(null)
const filters = ref([])
const loading = ref(false)
const filterOptions = ref(null)
const showOptions = ref(false)
const searchForm = ref({})

// Dialog
const dialogVisible = ref(false)
const isEdit = ref(false)
const editingId = ref(null)
const form = ref({
    name: '',
    match_type: 'all',
    priority: 0,
    conditions: [{ field: 'event_type', operator: 'equals', value: '' }],
    payloadTemplateStr: '',
    is_active: true,
})
const formRules = computed(() => ({
    name: [{ required: true, message: t('webhook_filter_page.rules.name_required') }],
    conditions: [{ required: true, message: t('webhook_filter_page.rules.conditions_required') }],
}))
const formRef = ref(null)
const saving = ref(false)
const showTemplateHelp = ref(false)

const operatorLabels = computed(() => ({
    equals: t('webhook_filter_page.operators.equals'),
    not_equals: t('webhook_filter_page.operators.not_equals'),
    contains: t('webhook_filter_page.operators.contains'),
    not_contains: t('webhook_filter_page.operators.not_contains'),
    starts_with: t('webhook_filter_page.operators.starts_with'),
    ends_with: t('webhook_filter_page.operators.ends_with'),
    in: t('webhook_filter_page.operators.in'),
    not_in: t('webhook_filter_page.operators.not_in'),
    greater_than: t('webhook_filter_page.operators.greater_than'),
    less_than: t('webhook_filter_page.operators.less_than'),
    exists: t('webhook_filter_page.operators.exists'),
    not_exists: t('webhook_filter_page.operators.not_exists'),
    regex: t('webhook_filter_page.operators.regex'),
}))

// Batch test
const testEvents = ref([{ event_type: 'license.activated', payload_raw: '{"license":{"status":"active"}}' }])
const testResults = ref([])
const testing = ref(false)

onMounted(async () => {
    try {
        const res = await getWebhookFilterOptions()
        filterOptions.value = res.data
    } catch { /* ignore */ }
    try {
        const res = await webhookEndpoint.list()
        endpoints.value = res.data?.data || []
    } catch { /* ignore */ }
})

async function loadFilters() {
    if (!selectedEndpointId.value) return
    loading.value = true
    try {
        const res = await getWebhookFilters(selectedEndpointId.value)
        filters.value = res.data?.filters || []
    } catch { filters.value = [] }
    loading.value = false
}

function openCreateDialog() {
    isEdit.value = false
    editingId.value = null
    form.value = {
        name: '',
        match_type: 'all',
        priority: 0,
        conditions: [{ field: 'event_type', operator: 'equals', value: '' }],
        payloadTemplateStr: '',
        is_active: true,
    }
    dialogVisible.value = true
}

function editFilter(row) {
    isEdit.value = true
    editingId.value = row.id
    form.value = {
        name: row.name,
        match_type: row.match_type,
        priority: row.priority,
        conditions: row.conditions || [],
        payloadTemplateStr: row.payload_template ? JSON.stringify(row.payload_template, null, 2) : '',
        is_active: row.is_active,
    }
    dialogVisible.value = true
}

function addCondition() {
    form.value.conditions.push({ field: 'event_type', operator: 'equals', value: '' })
}

function removeCondition(idx) {
    form.value.conditions.splice(idx, 1)
}

function operatorLabel(op) {
    const label = operatorLabels.value[op] || op
    return `${label} (${op})`
}

async function handleSave() {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return

    const payload = {
        name: form.value.name,
        match_type: form.value.match_type,
        priority: form.value.priority,
        conditions: form.value.conditions,
        is_active: form.value.is_active,
    }

    if (form.value.payloadTemplateStr.trim()) {
        try {
            payload.payload_template = JSON.parse(form.value.payloadTemplateStr)
        } catch {
            ElMessage.error(t('webhook_filter_page.messages.template_json_error'))
            return
        }
    }

    saving.value = true
    try {
        if (isEdit.value && editingId.value) {
            await updateWebhookFilter(selectedEndpointId.value, editingId.value, payload)
            ElMessage.success(t('webhook_filter_page.messages.updated'))
        } else {
            await createWebhookFilter(selectedEndpointId.value, payload)
            ElMessage.success(t('webhook_filter_page.messages.created'))
        }
        dialogVisible.value = false
        loadFilters()
    } catch (e) {
        ElMessage.error(e.message || t('messages.failed'))
    }
    saving.value = false
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(
            t('webhook_filter_page.messages.delete_confirm', { name: row.name }),
            t('actions.confirm'),
        )
        await deleteWebhookFilter(selectedEndpointId.value, row.id)
        ElMessage.success(t('webhook_filter_page.messages.deleted'))
        loadFilters()
    } catch { /* ignore */ }
}

function addTestEvent() {
    testEvents.value.push({ event_type: 'license.activated', payload_raw: '{"license":{"status":"active"}}' })
}

function removeTestEvent(idx) {
    testEvents.value.splice(idx, 1)
}

async function handleBatchTest() {
    const events = testEvents.value.map(evt => {
        try {
            return { event_type: evt.event_type, payload: JSON.parse(evt.payload_raw) }
        } catch {
            ElMessage.error(t('webhook_filter_page.messages.payload_json_error', { event_type: evt.event_type }))
            return null
        }
    }).filter(Boolean)

    if (events.length === 0) return

    testing.value = true
    try {
        const res = await batchTestWebhookFilters(selectedEndpointId.value, { test_events: events })
        testResults.value = res.data?.results || []
    } catch (e) {
        ElMessage.error(e.message || t('webhook_filter_page.messages.test_failed'))
    }
    testing.value = false
}
</script>

<style scoped>
.webhook-filter-container {
    padding: 20px;
}

.alert-info {
    margin-top: 16px;
    margin-bottom: 16px;
}

.endpoint-card {
    margin-bottom: 16px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.section-header h3 {
    margin: 0;
}

.test-card {
    margin-top: 20px;
}

.test-event-item {
    background: #f5f7fa;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 8px;
}

.test-results {
    margin-top: 16px;
}

.match-detail {
    display: inline-block;
    margin: 2px;
}

.condition-row {
    margin-bottom: 8px;
}

.form-tip {
    font-size: 12px;
    color: #909399;
    margin-left: 8px;
}

.template-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-size: 13px;
    color: #909399;
}

.template-vars {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 8px;
    padding: 8px;
    background: #f5f7fa;
    border-radius: 4px;
}

.var-tag {
    font-size: 12px;
    padding: 2px 6px;
    background: #f1f5f9;
    border-radius: 3px;
}

.option-tag {
    margin: 4px;
}

.option-item {
    margin: 8px 0;
    display: flex;
    justify-content: space-between;
}

.text-gray {
    color: #c0c4cc;
}

.ml-2 {
    margin-left: 8px;
}
</style>
