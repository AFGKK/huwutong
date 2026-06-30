<template>
    <div class="webhook-filter-container">
        <el-page-header :content="'Webhook 条件过滤器'" @back="$router.push('/admin/dashboard')" />

        <!-- 说明 -->
        <el-alert
            title="使用条件过滤器可以对 Webhook 事件进行精筛：按事件类型/产品/客户/状态筛选，并可自定义 Payload 模板转换数据格式。"
            type="info"
            show-icon
            :closable="false"
            class="alert-info"
        />

        <!-- 选择 Webhook 端点 -->
        <el-card class="endpoint-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="Webhook 端点">
                    <el-select
                        v-model="selectedEndpointId"
                        placeholder="请选择端点"
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
                    <el-button type="primary" @click="showOptions = true">查看筛选选项</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 过滤器列表 -->
        <template v-if="selectedEndpointId">
            <div class="section-header">
                <h3>过滤器列表</h3>
                <el-button type="primary" size="small" @click="openCreateDialog">新建过滤器</el-button>
            </div>

            <el-table :data="filters" v-loading="loading" stripe empty-text="暂无过滤器">
                <el-table-column prop="name" label="名称" min-width="160" />
                <el-table-column label="匹配条件" min-width="200">
                    <template #default="{ row }">
                        <el-tag :type="row.match_type === 'all' ? 'primary' : 'warning'" size="small">
                            {{ row.match_type === 'all' ? '全部 (AND)' : '任一 (OR)' }}
                        </el-tag>
                        <span class="ml-2"> {{ row.conditions?.length || 0 }} 条条件</span>
                    </template>
                </el-table-column>
                <el-table-column label="自定义模板" width="120" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.payload_template" type="success" size="small">已启用</el-tag>
                        <span v-else class="text-gray">-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="priority" label="优先级" width="80" align="center" />
                <el-table-column label="状态" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                            {{ row.is_active ? '启用' : '停用' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="editFilter(row)">编辑</el-button>
                        <el-button size="small" type="danger" plain @click="handleDelete(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 批量测试区域 -->
            <el-card class="test-card">
                <template #header>
                    <span>批量测试</span>
                    <el-button size="small" style="float:right" @click="addTestEvent">添加测试事件</el-button>
                </template>
                <div v-for="(evt, idx) in testEvents" :key="idx" class="test-event-item">
                    <el-form :model="evt" inline>
                        <el-form-item label="事件类型">
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
                                placeholder='{"license":{"status":"active"}}'
                                style="width:400px"
                            />
                        </el-form-item>
                        <el-button type="danger" size="small" @click="removeTestEvent(idx)">移除</el-button>
                    </el-form>
                </div>
                <el-button
                    type="primary"
                    @click="handleBatchTest"
                    :loading="testing"
                    :disabled="testEvents.length === 0"
                >
                    运行批量测试
                </el-button>

                <!-- 测试结果 -->
                <div v-if="testResults.length > 0" class="test-results">
                    <h4>测试结果</h4>
                    <el-table :data="testResults" stripe size="small">
                        <el-table-column prop="event_type" label="事件类型" width="200" />
                        <el-table-column label="匹配结果" width="120">
                            <template #default="{ row }">
                                <el-tag :type="row.any_matched ? 'success' : 'danger'" size="small">
                                    {{ row.any_matched ? '已匹配' : '未匹配' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="匹配详情" min-width="300">
                            <template #default="{ row }">
                                <div v-for="mf in row.matched_filters" :key="mf.filter_id" class="match-detail">
                                    <el-tag :type="mf.matched ? 'success' : 'info'" size="small">
                                        {{ mf.matched ? '✅' : '❌' }} {{ mf.filter_name }}
                                    </el-tag>
                                </div>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </el-card>
        </template>

        <!-- 新建/编辑过滤器 Dialog -->
        <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑过滤器' : '新建过滤器'" width="700px">
            <el-form :model="form" label-width="120px" :rules="formRules" ref="formRef">
                <el-form-item label="名称" prop="name">
                    <el-input v-model="form.name" maxlength="100" />
                </el-form-item>
                <el-form-item label="匹配类型" prop="match_type">
                    <el-radio-group v-model="form.match_type">
                        <el-radio value="all">全部匹配 (AND) — 所有条件都满足</el-radio>
                        <el-radio value="any">任一匹配 (OR) — 满足任一条件</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="优先级">
                    <el-input-number v-model="form.priority" :min="-100" :max="100" />
                    <span class="form-tip">数字越大优先级越高</span>
                </el-form-item>
                <el-form-item label="条件列表" prop="conditions">
                    <div v-for="(cond, idx) in form.conditions" :key="idx" class="condition-row">
                        <el-row :gutter="8">
                            <el-col :span="7">
                                <el-select v-model="cond.field" placeholder="字段" filterable style="width:100%">
                                    <el-option
                                        v-for="f in filterOptions?.fields || []"
                                        :key="f"
                                        :label="f"
                                        :value="f"
                                    />
                                </el-select>
                            </el-col>
                            <el-col :span="6">
                                <el-select v-model="cond.operator" placeholder="操作符" style="width:100%">
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
                                    placeholder="值"
                                    style="width:100%"
                                />
                                <span v-else class="form-tip" style="line-height:32px">无需输入值</span>
                            </el-col>
                            <el-col :span="3">
                                <el-button type="danger" size="small" @click="removeCondition(idx)" circle>
                                    -
                                </el-button>
                            </el-col>
                        </el-row>
                    </div>
                    <el-button size="small" @click="addCondition">+ 添加条件</el-button>
                </el-form-item>
                <el-form-item label="Payload 模板">
                    <div class="template-info">
                        <span>可选：自定义输出格式，留空则使用原始 Payload。</span>
                        <el-button size="small" @click="showTemplateHelp = !showTemplateHelp">
                            {{ showTemplateHelp ? '收起' : '变量帮助' }}
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
                        placeholder='{"custom_key": "{{license.key}}", "event":"{{event_type}}"}'
                    />
                </el-form-item>
                <el-form-item label="启用">
                    <el-switch v-model="form.is_active" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">保存</el-button>
            </template>
        </el-dialog>

        <!-- 筛选选项 Dialog -->
        <el-dialog v-model="showOptions" title="支持的筛选选项" width="600px">
            <el-tabs>
                <el-tab-pane label="筛选字段">
                    <el-tag v-for="f in filterOptions?.fields || []" :key="f" class="option-tag">{{ f }}</el-tag>
                </el-tab-pane>
                <el-tab-pane label="操作符">
                    <div v-for="op in filterOptions?.operators || []" :key="op" class="option-item">
                        <strong>{{ operatorLabel(op) }}</strong>
                        <code>{{ op }}</code>
                    </div>
                </el-tab-pane>
                <el-tab-pane label="模板变量">
                    <el-tag v-for="v in filterOptions?.template_variables || []" :key="v" class="option-tag">{{ v }}</el-tag>
                </el-tab-pane>
                <el-tab-pane label="事件类型">
                    <el-tag v-for="et in filterOptions?.event_types || []" :key="et" class="option-tag">{{ et }}</el-tag>
                </el-tab-pane>
            </el-tabs>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getWebhookFilters, createWebhookFilter, updateWebhookFilter, deleteWebhookFilter,
    getWebhookFilterOptions, batchTestWebhookFilters,
} from '@/api/webhookFilter'
import webhookEndpoint from '@/api/webhookEndpoint'

const endpoints = ref([])
const selectedEndpointId = ref(null)
const filters = ref([])
const loading = ref(false)
const filterOptions = ref(null)
const showOptions = ref(false)

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
const formRules = {
    name: [{ required: true, message: '请输入名称' }],
    conditions: [{ required: true, message: '至少添加一个条件' }],
}
const formRef = ref(null)
const saving = ref(false)
const showTemplateHelp = ref(false)

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
    const labels = {
        equals: '等于', not_equals: '不等于', contains: '包含',
        not_contains: '不包含', starts_with: '开头是', ends_with: '结尾是',
        in: '在列表中', not_in: '不在列表中',
        greater_than: '大于', less_than: '小于',
        exists: '存在', not_exists: '不存在', regex: '正则匹配',
    }
    return `${labels[op] || op} (${op})`
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
            ElMessage.error('Payload 模板 JSON 格式错误')
            return
        }
    }

    saving.value = true
    try {
        if (isEdit.value && editingId.value) {
            await updateWebhookFilter(selectedEndpointId.value, editingId.value, payload)
            ElMessage.success('过滤器已更新')
        } else {
            await createWebhookFilter(selectedEndpointId.value, payload)
            ElMessage.success('过滤器已创建')
        }
        dialogVisible.value = false
        loadFilters()
    } catch (e) {
        ElMessage.error(e.message || '操作失败')
    }
    saving.value = false
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(`确定删除过滤器「${row.name}」？`, '确认')
        await deleteWebhookFilter(selectedEndpointId.value, row.id)
        ElMessage.success('已删除')
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
            ElMessage.error(`事件 ${evt.event_type} 的 Payload JSON 格式错误`)
            return null
        }
    }).filter(Boolean)

    if (events.length === 0) return

    testing.value = true
    try {
        const res = await batchTestWebhookFilters(selectedEndpointId.value, { test_events: events })
        testResults.value = res.data?.results || []
    } catch (e) {
        ElMessage.error(e.message || '测试失败')
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
    background: #ecf5ff;
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
