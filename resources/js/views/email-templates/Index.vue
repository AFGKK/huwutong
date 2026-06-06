<template>
    <div class="email-templates-page">
        <div class="page-header">
            <div class="header-left">
                <h2>邮件模板管理</h2>
                <span class="header-subtitle">自定义邮件模板，支持变量占位符</span>
            </div>
            <div class="header-right">
                <el-button @click="initDefaults">
                    <el-icon><Refresh /></el-icon> 初始化默认模板
                </el-button>
                <el-button type="primary" @click="openEditDialog(null)">
                    <el-icon><Plus /></el-icon> 新建模板
                </el-button>
            </div>
        </div>

        <!-- 筛选 -->
        <el-card shadow="never" class="filter-card">
            <el-form :inline="true" :model="filters">
                <el-form-item label="搜索">
                    <el-input v-model="filters.search" placeholder="搜索名称/标识" clearable @input="loadTemplates" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="filters.status" placeholder="全部" clearable @change="loadTemplates" style="width:120px;">
                        <el-option label="已发布" value="published" />
                        <el-option label="草稿" value="draft" />
                    </el-select>
                </el-form-item>
                <el-form-item label="语言">
                    <el-select v-model="filters.locale" placeholder="全部" clearable @change="loadTemplates" style="width:120px;">
                        <el-option label="中文" value="zh-CN" />
                        <el-option label="英文" value="en" />
                    </el-select>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 表格 -->
        <el-card shadow="never">
            <el-table :data="templates" v-loading="loading" stripe>
                <el-table-column label="标识" width="180" prop="code">
                    <template #default="{ row }">
                        <code>{{ row.code }}</code>
                    </template>
                </el-table-column>
                <el-table-column label="名称" min-width="180" prop="name" />
                <el-table-column label="主题" min-width="250" prop="subject" show-overflow-tooltip />
                <el-table-column label="语言" width="80" prop="locale">
                    <template #default="{ row }">
                        <el-tag size="small" effect="plain">{{ row.locale }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="100" prop="status">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'published' ? 'success' : 'info'" size="small">
                            {{ row.status === 'published' ? '已发布' : '草稿' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="创建时间" width="170" prop="created_at">
                    <template #default="{ row }">
                        {{ formatDate(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="220" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="openEditDialog(row)">编辑</el-button>
                        <el-button text size="small" type="primary" @click="openPreview(row)">预览</el-button>
                        <el-button text size="small" type="danger" @click="handleDelete(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 编辑 Dialog -->
        <el-dialog
            v-model="dialogVisible"
            :title="editingId ? '编辑邮件模板' : '新建邮件模板'"
            width="850px"
            :close-on-click-modal="false"
            top="3vh"
        >
            <el-tabs v-model="editTab" type="border-card">
                <el-tab-pane label="基本信息" name="basic">
                    <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px" label-position="right">
                        <el-row :gutter="20">
                            <el-col :span="12">
                                <el-form-item label="模板标识" prop="code">
                                    <el-input v-model="form.code" :disabled="!!editingId" placeholder="如: license_activated" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="12">
                                <el-form-item label="模板名称" prop="name">
                                    <el-input v-model="form.name" placeholder="如: License 激活成功" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                        <el-row :gutter="20">
                            <el-col :span="12">
                                <el-form-item label="语言" prop="locale">
                                    <el-select v-model="form.locale" style="width:100%;">
                                        <el-option label="中文 (zh-CN)" value="zh-CN" />
                                        <el-option label="英文 (en)" value="en" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :span="12">
                                <el-form-item label="状态" prop="status">
                                    <el-select v-model="form.status" style="width:100%;">
                                        <el-option label="草稿" value="draft" />
                                        <el-option label="已发布" value="published" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                        </el-row>
                        <el-form-item label="邮件主题" prop="subject">
                            <el-input v-model="form.subject" placeholder="支持变量，如: 【{{site_name}}】License 激活成功" />
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane label="HTML 正文" name="html">
                    <el-form-item label="">
                        <div class="editor-toolbar">
                            <span class="toolbar-title">支持 HTML + 变量占位符</span>
                            <el-button text size="small" type="primary" @click="showVariables = !showVariables">
                                <el-icon><HelpFilled /></el-icon> 查看可用变量
                            </el-button>
                            <el-button text size="small" type="primary" @click="handlePreview">
                                <el-icon><View /></el-icon> 预览效果
                            </el-button>
                        </div>
                    </el-form-item>
                    <el-input
                        v-model="form.body_html"
                        type="textarea"
                        :rows="16"
                        placeholder="HTML 正文内容，支持 {{变量}} 占位符"
                        style="font-family: 'SF Mono', 'Fira Code', monospace; font-size: 13px;"
                    />
                </el-tab-pane>

                <el-tab-pane label="纯文本" name="text">
                    <el-form-item label="">
                        <div class="editor-toolbar">
                            <span class="toolbar-title">纯文本正文（可选，留空自动从 HTML 生成）</span>
                        </div>
                    </el-form-item>
                    <el-input
                        v-model="form.body_text"
                        type="textarea"
                        :rows="12"
                        placeholder="纯文本内容，支持 {{变量}} 占位符"
                        style="font-family: 'SF Mono', 'Fira Code', monospace; font-size: 13px;"
                    />
                </el-tab-pane>
            </el-tabs>

            <!-- 变量面板 -->
            <el-collapse-transition>
                <div v-if="showVariables" class="variables-panel">
                    <h4>可用变量 <el-tag size="small">复制变量名插入模板</el-tag></h4>
                    <div v-for="(vars, group) in allVariables" :key="group" class="var-group">
                        <div class="var-group-label">{{ groupLabels[group] || group }}</div>
                        <div class="var-tags">
                            <el-tag
                                v-for="v in vars"
                                :key="v.key"
                                class="var-tag"
                                type="primary"
                                effect="plain"
                                @click="insertVariable(v.key)"
                            >
                                <code>{{ v.key }}</code>
                                <span class="var-label">{{ v.label }}</span>
                            </el-tag>
                        </div>
                    </div>
                </div>
            </el-collapse-transition>

            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button @click="handlePreview">预览</el-button>
                <el-button type="primary" :loading="submitting" @click="submitForm">
                    {{ editingId ? '保存' : '创建' }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 预览 Dialog -->
        <el-dialog v-model="previewVisible" title="模板预览" width="700px" top="5vh">
            <div v-loading="previewLoading">
                <el-alert
                    title="以下为使用测试数据渲染后的效果"
                    type="info"
                    show-icon
                    :closable="false"
                    class="mb-3"
                />
                <el-descriptions title="邮件主题" :column="1" border size="small" class="mb-3">
                    <el-descriptions-item label="主题">{{ previewData?.subject }}</el-descriptions-item>
                </el-descriptions>
                <div class="preview-html" v-html="previewData?.html"></div>
                <el-divider />
                <div class="preview-text">
                    <div class="preview-label">纯文本版本：</div>
                    <pre>{{ previewData?.text }}</pre>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, View, HelpFilled } from '@element-plus/icons-vue';
import emailTemplateApi from '@/api/email-template';

const loading = ref(false);
const templates = ref([]);
const dialogVisible = ref(false);
const previewVisible = ref(false);
const previewLoading = ref(false);
const previewData = ref(null);
const submitting = ref(false);
const editingId = ref(null);
const showVariables = ref(false);
const editTab = ref('basic');
const formRef = ref(null);
const allVariables = ref({});
const activeHtml = ref('');

const filters = reactive({
    search: '',
    status: '',
    locale: '',
});

const groupLabels = {
    general: '通用',
    customer: '客户',
    license: 'License',
    account: '账户',
    invoice: '发票',
};

const form = reactive({
    code: '',
    name: '',
    subject: '',
    body_html: '',
    body_text: '',
    locale: 'zh-CN',
    status: 'draft',
});

const formRules = {
    code: [{ required: true, message: '请输入模板标识', trigger: 'blur' }],
    name: [{ required: true, message: '请输入模板名称', trigger: 'blur' }],
    subject: [{ required: true, message: '请输入邮件主题', trigger: 'blur' }],
};

function formatDate(dateStr) {
    if (!dateStr) return null;
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

async function loadTemplates() {
    loading.value = true;
    try {
        const params = { per_page: 50 };
        if (filters.search) params.search = filters.search;
        if (filters.status) params['filter.status'] = filters.status;
        if (filters.locale) params['filter.locale'] = filters.locale;
        const { data: res } = await emailTemplateApi.list(params);
        templates.value = res.data?.data || [];
    } catch {
        templates.value = [];
    } finally {
        loading.value = false;
    }
}

async function loadVariables() {
    try {
        const { data: res } = await emailTemplateApi.variables();
        if (res.success) {
            allVariables.value = res.data || {};
        }
    } catch {
        // ignore
    }
}

function openEditDialog(row) {
    editingId.value = row ? row.id : null;
    form.code = row?.code || '';
    form.name = row?.name || '';
    form.subject = row?.subject || '';
    form.body_html = row?.body_html || '';
    form.body_text = row?.body_text || '';
    form.locale = row?.locale || 'zh-CN';
    form.status = row?.status || 'draft';
    editTab.value = 'basic';
    dialogVisible.value = true;
}

function insertVariable(key) {
    const ta = document.activeElement;
    if (ta && ta.tagName === 'TEXTAREA') {
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        ta.value = ta.value.substring(0, start) + key + ta.value.substring(end);
        ta.selectionStart = ta.selectionEnd = start + key.length;
        ta.focus();
        // 根据当前 tab 更新对应的 model
        if (editTab.value === 'html') {
            form.body_html = ta.value;
        } else if (editTab.value === 'text') {
            form.body_text = ta.value;
        }
    }
}

async function submitForm() {
    const valid = await formRef.value.validate().catch(() => false);
    if (!valid) return;

    submitting.value = true;
    try {
        const payload = {
            name: form.name,
            subject: form.subject,
            body_html: form.body_html,
            body_text: form.body_text,
            locale: form.locale,
            status: form.status,
        };

        if (editingId.value) {
            await emailTemplateApi.update(editingId.value, payload);
            ElMessage.success('模板更新成功');
        } else {
            payload.code = form.code;
            await emailTemplateApi.create(payload);
            ElMessage.success('模板创建成功');
        }
        dialogVisible.value = false;
        loadTemplates();
    } catch {
        // handled by interceptor
    } finally {
        submitting.value = false;
    }
}

async function handlePreview() {
    if (!form.subject && !form.body_html) {
        ElMessage.warning('请先填写主题和正文');
        return;
    }
    previewLoading.value = true;
    previewVisible.value = true;
    try {
        const { data: res } = await emailTemplateApi.preview({
            subject: form.subject,
            body_html: form.body_html,
            body_text: form.body_text,
        });
        if (res.success) {
            previewData.value = res.data;
        }
    } catch {
        previewData.value = null;
    } finally {
        previewLoading.value = false;
    }
}

function openPreview(row) {
    previewLoading.value = true;
    previewVisible.value = true;
    emailTemplateApi.preview({
        subject: row.subject,
        body_html: row.body_html,
        body_text: row.body_text,
    }).then(({ data: res }) => {
        if (res.success) {
            previewData.value = res.data;
        }
    }).catch(() => {
        previewData.value = null;
    }).finally(() => {
        previewLoading.value = false;
    });
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(
            `确定要删除模板「${row.name}」({{${row.code}}}) 吗？`,
            '确认删除',
            { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
        );
        await emailTemplateApi.destroy(row.id);
        ElMessage.success('模板已删除');
        loadTemplates();
    } catch { /* cancelled */ }
}

async function initDefaults() {
    try {
        await ElMessageBox.confirm(
            '将创建所有不存在的默认模板（不会覆盖已有模板）。确定继续？',
            '初始化默认模板',
            { confirmButtonText: '确定', cancelButtonText: '取消', type: 'info' }
        );
        const { data: res } = await emailTemplateApi.initDefaults();
        if (res.success) {
            ElMessage.success(`已创建 ${res.data?.created?.length || 0} 个默认模板`);
            loadTemplates();
        }
    } catch { /* cancelled */ }
}

onMounted(() => {
    loadTemplates();
    loadVariables();
});
</script>

<style scoped>
.email-templates-page { padding: 20px; }

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

.filter-card { margin-bottom: 16px; }
.filter-card :deep(.el-card__body) { padding: 12px 16px; }

code {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 12px;
    background: var(--el-fill-color-light);
    padding: 2px 6px;
    border-radius: 3px;
}

.editor-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}
.toolbar-title { font-size: 13px; color: var(--el-text-color-secondary); }

/* 变量面板 */
.variables-panel {
    border: 1px solid var(--el-border-color-light);
    border-radius: 6px;
    padding: 16px;
    margin-top: 12px;
    max-height: 400px;
    overflow-y: auto;
}
.variables-panel h4 {
    margin: 0 0 12px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.var-group { margin-bottom: 12px; }
.var-group-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--el-text-color-secondary);
    margin-bottom: 6px;
}
.var-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.var-tag {
    cursor: pointer;
    font-size: 12px;
}
.var-tag code {
    background: transparent;
    padding: 0;
    color: var(--el-color-primary);
}
.var-label {
    margin-left: 4px;
    font-size: 11px;
}

/* 预览 */
.preview-html {
    border: 1px solid var(--el-border-color-light);
    border-radius: 6px;
    padding: 24px;
    background: white;
    min-height: 200px;
}
.preview-text pre {
    background: var(--el-fill-color-light);
    padding: 12px;
    border-radius: 4px;
    font-size: 13px;
    white-space: pre-wrap;
}
.preview-label { font-size: 13px; font-weight: 600; margin-bottom: 8px; }

.mb-3 { margin-bottom: 12px; }

:deep(.el-card__body) { padding: 16px; }
</style>
