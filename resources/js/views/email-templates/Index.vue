<template>
    <div class="email-templates-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('email_templates_page.title') }}</h2>
                <span class="header-subtitle">{{ t('email_templates_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button @click="initDefaults">
                    <el-icon><Refresh /></el-icon> {{ t('email_templates_page.init_defaults') }}
                </el-button>
                <el-button type="primary" @click="openEditDialog(null)">
                    <el-icon><Plus /></el-icon> {{ t('email_templates_page.create_template') }}
                </el-button>
            </div>
        </div>

        <!-- 筛选 -->
        <el-card shadow="never" class="filter-card">
            <el-form :inline="true" :model="filters">
                <el-form-item :label="t('actions.search')">
                    <el-input v-model="filters.search" :placeholder="t('email_templates_page.search_ph')" clearable @input="loadTemplates" />
                </el-form-item>
                <el-form-item :label="t('email_templates_page.status')">
                    <el-select v-model="filters.status" :placeholder="t('email_templates_page.all_ph')" clearable @change="loadTemplates" style="width:120px;">
                        <el-option
                            v-for="opt in statusOptions"
                            :key="opt.value"
                            :label="opt.label"
                            :value="opt.value"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('email_templates_page.locale')">
                    <el-select v-model="filters.locale" :placeholder="t('email_templates_page.all_ph')" clearable @change="loadTemplates" style="width:120px;">
                        <el-option
                            v-for="opt in localeFilterOptions"
                            :key="opt.value"
                            :label="opt.label"
                            :value="opt.value"
                        />
                    </el-select>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 表格 -->
        <el-card shadow="never">
            <el-table :data="templates" v-loading="loading" stripe>
                <el-table-column :label="t('email_templates_page.cols.code')" width="180" prop="code">
                    <template #default="{ row }">
                        <code>{{ row.code }}</code>
                    </template>
                </el-table-column>
                <el-table-column :label="t('email_templates_page.cols.name')" min-width="180" prop="name" />
                <el-table-column :label="t('email_templates_page.cols.subject')" min-width="250" prop="subject" show-overflow-tooltip />
                <el-table-column :label="t('email_templates_page.locale')" width="80" prop="locale">
                    <template #default="{ row }">
                        <el-tag size="small" effect="plain">{{ row.locale }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('email_templates_page.status')" width="100" prop="status">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'published' ? 'success' : 'info'" size="small">
                            {{ statusLabels[row.status] || row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('email_templates_page.cols.created_at')" width="170" prop="created_at">
                    <template #default="{ row }">
                        {{ formatDate(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('email_templates_page.cols.actions')" width="220" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="openEditDialog(row)">{{ t('actions.edit') }}</el-button>
                        <el-button text size="small" type="primary" @click="openPreview(row)">{{ t('email_templates_page.preview') }}</el-button>
                        <el-button text size="small" type="danger" @click="handleDelete(row)">{{ t('actions.delete') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 编辑 Dialog -->
        <el-dialog
            v-model="dialogVisible"
            :title="editingId ? t('email_templates_page.dialog_edit') : t('email_templates_page.dialog_create')"
            width="850px"
            :close-on-click-modal="false"
            top="3vh"
        >
            <el-tabs v-model="editTab" type="border-card">
                <el-tab-pane :label="t('email_templates_page.tabs.basic')" name="basic">
                    <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px" label-position="right">
                        <el-row :gutter="20">
                            <el-col :span="12">
                                <el-form-item :label="t('email_templates_page.form.code')" prop="code">
                                    <el-input v-model="form.code" :disabled="!!editingId" :placeholder="t('email_templates_page.form.code_ph')" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="12">
                                <el-form-item :label="t('email_templates_page.form.name')" prop="name">
                                    <el-input v-model="form.name" :placeholder="t('email_templates_page.form.name_ph')" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                        <el-row :gutter="20">
                            <el-col :span="12">
                                <el-form-item :label="t('email_templates_page.form.locale')" prop="locale">
                                    <el-select v-model="form.locale" style="width:100%;">
                                        <el-option
                                            v-for="opt in localeFormOptions"
                                            :key="opt.value"
                                            :label="opt.label"
                                            :value="opt.value"
                                        />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :span="12">
                                <el-form-item :label="t('email_templates_page.form.status')" prop="status">
                                    <el-select v-model="form.status" style="width:100%;">
                                        <el-option
                                            v-for="opt in statusOptions"
                                            :key="opt.value"
                                            :label="opt.label"
                                            :value="opt.value"
                                        />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                        </el-row>
                        <el-form-item :label="t('email_templates_page.form.subject')" prop="subject">
                            <el-input v-model="form.subject" :placeholder="t('email_templates_page.form.subject_ph')" />
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane :label="t('email_templates_page.tabs.html')" name="html">
                    <el-form-item label="">
                        <div class="editor-toolbar">
                            <span class="toolbar-title">{{ t('email_templates_page.html_toolbar') }}</span>
                            <el-button text size="small" type="primary" @click="showVariables = !showVariables">
                                <el-icon><HelpFilled /></el-icon> {{ t('email_templates_page.show_variables') }}
                            </el-button>
                            <el-button text size="small" type="primary" @click="handlePreview">
                                <el-icon><View /></el-icon> {{ t('email_templates_page.preview_effect') }}
                            </el-button>
                        </div>
                    </el-form-item>
                    <el-input
                        v-model="form.body_html"
                        type="textarea"
                        :rows="16"
                        :placeholder="t('email_templates_page.body_html_ph')"
                        style="font-family: 'SF Mono', 'Fira Code', monospace; font-size: 13px;"
                    />
                </el-tab-pane>

                <el-tab-pane :label="t('email_templates_page.tabs.text')" name="text">
                    <el-form-item label="">
                        <div class="editor-toolbar">
                            <span class="toolbar-title">{{ t('email_templates_page.text_toolbar') }}</span>
                        </div>
                    </el-form-item>
                    <el-input
                        v-model="form.body_text"
                        type="textarea"
                        :rows="12"
                        :placeholder="t('email_templates_page.body_text_ph')"
                        style="font-family: 'SF Mono', 'Fira Code', monospace; font-size: 13px;"
                    />
                </el-tab-pane>
            </el-tabs>

            <!-- 变量面板 -->
            <el-collapse-transition>
                <div v-if="showVariables" class="variables-panel">
                    <h4>
                        {{ t('email_templates_page.variables_title') }}
                        <el-tag size="small">{{ t('email_templates_page.variables_hint') }}</el-tag>
                    </h4>
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
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button @click="handlePreview">{{ t('email_templates_page.preview') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="submitForm">
                    {{ editingId ? t('actions.save') : t('actions.create') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 预览 Dialog -->
        <el-dialog v-model="previewVisible" :title="t('email_templates_page.preview_title')" width="700px" top="5vh">
            <div v-loading="previewLoading">
                <el-alert
                    :title="t('email_templates_page.preview_alert')"
                    type="info"
                    show-icon
                    :closable="false"
                    class="mb-3"
                />
                <el-descriptions :title="t('email_templates_page.preview_subject_title')" :column="1" border size="small" class="mb-3">
                    <el-descriptions-item :label="t('email_templates_page.cols.subject')">{{ previewData?.subject }}</el-descriptions-item>
                </el-descriptions>
                <div class="preview-html" v-html="previewData?.html"></div>
                <el-divider />
                <div class="preview-text">
                    <div class="preview-label">{{ t('email_templates_page.preview_text_label') }}</div>
                    <pre>{{ previewData?.text }}</pre>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, View, HelpFilled } from '@element-plus/icons-vue';
import emailTemplateApi from '@/api/email-template';

const { t, locale } = useI18n();

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

const filters = reactive({
    search: '',
    status: '',
    locale: '',
});

const statusLabels = computed(() => ({
    published: t('email_templates_page.status_published'),
    draft: t('email_templates_page.status_draft'),
}));

const statusOptions = computed(() => [
    { value: 'published', label: statusLabels.value.published },
    { value: 'draft', label: statusLabels.value.draft },
]);

const localeFilterOptions = computed(() => [
    { value: 'zh-CN', label: t('email_templates_page.locale_zh') },
    { value: 'en', label: t('email_templates_page.locale_en') },
]);

const localeFormOptions = computed(() => [
    { value: 'zh-CN', label: t('email_templates_page.locale_zh_full') },
    { value: 'en', label: t('email_templates_page.locale_en_full') },
]);

const groupLabels = computed(() => ({
    general: t('email_templates_page.var_groups.general'),
    customer: t('email_templates_page.var_groups.customer'),
    license: t('email_templates_page.var_groups.license'),
    account: t('email_templates_page.var_groups.account'),
    invoice: t('email_templates_page.var_groups.invoice'),
}));

const form = reactive({
    code: '',
    name: '',
    subject: '',
    body_html: '',
    body_text: '',
    locale: 'zh-CN',
    status: 'draft',
});

const formRules = computed(() => ({
    code: [{ required: true, message: t('email_templates_page.rules.code_required'), trigger: 'blur' }],
    name: [{ required: true, message: t('email_templates_page.rules.name_required'), trigger: 'blur' }],
    subject: [{ required: true, message: t('email_templates_page.rules.subject_required'), trigger: 'blur' }],
}));

function formatDate(dateStr) {
    if (!dateStr) return null;
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(dateStr).toLocaleString(loc, {
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
            ElMessage.success(t('email_templates_page.messages.updated'));
        } else {
            payload.code = form.code;
            await emailTemplateApi.create(payload);
            ElMessage.success(t('email_templates_page.messages.created'));
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
        ElMessage.warning(t('email_templates_page.messages.preview_required'));
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
            t('email_templates_page.confirm_delete', { name: row.name, code: row.code }),
            t('email_templates_page.confirm_delete_title'),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning' }
        );
        await emailTemplateApi.destroy(row.id);
        ElMessage.success(t('email_templates_page.messages.deleted'));
        loadTemplates();
    } catch { /* cancelled */ }
}

async function initDefaults() {
    try {
        await ElMessageBox.confirm(
            t('email_templates_page.init_confirm'),
            t('email_templates_page.init_confirm_title'),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'info' }
        );
        const { data: res } = await emailTemplateApi.initDefaults();
        if (res.success) {
            ElMessage.success(t('email_templates_page.messages.init_created', { count: res.data?.created?.length || 0 }));
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
