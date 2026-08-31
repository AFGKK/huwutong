<template>
    <div class="pages-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t(`${P}.title`) }}</h2>
                <span class="header-subtitle">{{ t(`${P}.subtitle`) }}</span>
            </div>
            <div class="header-right">
                <el-button type="primary" @click="openEditDialog(null)">
                    <el-icon><Plus /></el-icon>
                    {{ t(`${P}.create_btn`) }}
                </el-button>
            </div>
        </div>

        <el-alert
            class="mb-4"
            type="info"
            :closable="false"
            show-icon
            :title="t(`${P}.linkage_alert_title`)"
            :description="t(`${P}.linkage_alert_desc`)"
        />

        <!-- 表格 -->
        <el-card shadow="never">
            <el-table :data="pages" v-loading="loading" stripe>
                <el-table-column :label="t(`${P}.col_slug`)" width="120" prop="slug">
                    <template #default="{ row }">
                        <code>{{ row.slug }}</code>
                    </template>
                </el-table-column>
                <el-table-column :label="t('blog_page.col_title')" min-width="160" prop="title" />
                <el-table-column :label="t(`${P}.col_frontend_status`)" width="130">
                    <template #default="{ row }">
                        <el-tag
                            size="small"
                            :type="row.linkage?.mode === 'cms' ? 'success' : (row.linkage?.mode === 'static_fallback' || row.linkage?.mode === 'static_form' ? 'warning' : 'info')"
                        >
                            {{ modeLabel(row.linkage?.mode) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('blog_page.col_status')" width="90" prop="status">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'published' ? 'success' : 'info'" size="small">
                            {{ row.status === 'published' ? t('email_templates_page.status_published') : t('email_templates_page.status_draft') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('blog_page.col_version')" width="70" prop="version" align="center">
                    <template #default="{ row }">
                        <el-tag type="primary" effect="plain" size="small">v{{ row.version }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.col_locale`)" width="80" prop="locale" />
                <el-table-column :label="t('blog_page.col_published_at')" width="160" prop="published_at">
                    <template #default="{ row }">
                        {{ formatDate(row.published_at) || '-' }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('blog_page.col_actions')" width="280" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="openEditDialog(row)">
                            {{ t('actions.edit') }}
                        </el-button>
                        <el-button text size="small" @click="openFrontend(row)">
                            {{ t(`${P}.btn_frontend`) }}
                        </el-button>
                        <el-button
                            v-if="row.status === 'draft'"
                            text
                            size="small"
                            type="success"
                            @click="handlePublish(row)"
                        >
                            {{ t('blog_page.publish') }}
                        </el-button>
                        <el-button
                            v-if="row.status === 'published'"
                            text
                            size="small"
                            type="warning"
                            @click="handleDraft(row)"
                        >
                            {{ t(`${P}.revert_draft`) }}
                        </el-button>
                        <el-button text size="small" type="danger" @click="handleDelete(row)">
                            {{ t('actions.delete') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 编辑 Dialog -->
        <el-dialog
            v-model="dialogVisible"
            :title="dialogTitle"
            width="750px"
            :close-on-click-modal="false"
        >
            <el-form
                ref="formRef"
                :model="form"
                :rules="formRules"
                label-width="100px"
                label-position="right"
            >
                <el-form-item :label="t(`${P}.form_slug`)" prop="slug">
                    <el-input v-model="form.slug" :disabled="!!editingId" :placeholder="t(`${P}.slug_ph`)" style="max-width: 300px;" />
                </el-form-item>
                <el-form-item :label="t('blog_page.col_title')" prop="title">
                    <el-input v-model="form.title" :placeholder="t(`${P}.title_ph`)" />
                </el-form-item>
                <el-form-item :label="t(`${P}.form_locale`)" prop="locale">
                    <el-select v-model="form.locale" style="width: 150px;">
                        <el-option
                            v-for="opt in localeOptions"
                            :key="opt.value"
                            :label="opt.label"
                            :value="opt.value"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('blog_page.form_content')" prop="content">
                    <el-input
                        v-model="form.content"
                        type="textarea"
                        :rows="12"
                        :placeholder="t(`${P}.content_ph`)"
                    />
                </el-form-item>
                <el-divider>{{ t(`${P}.seo_divider`) }}</el-divider>
                <el-form-item :label="t(`${P}.seo_title`)" prop="meta.title">
                    <el-input v-model="form.meta.title" :placeholder="t(`${P}.seo_title_ph`)" />
                </el-form-item>
                <el-form-item :label="t(`${P}.seo_desc`)" prop="meta.description">
                    <el-input v-model="form.meta.description" type="textarea" :rows="2" :placeholder="t(`${P}.seo_desc_ph`)" />
                </el-form-item>
                <el-form-item :label="t(`${P}.seo_keywords`)" prop="meta.keywords">
                    <el-input v-model="form.meta.keywords" :placeholder="t(`${P}.seo_keywords_ph`)" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="submitForm">
                    {{ editingId ? t('actions.save') : t('actions.create') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import settingApi from '@/api/setting';

const P = 'pages_page';
const { t, locale } = useI18n();

const loading = ref(false);
const pages = ref([]);
const dialogVisible = ref(false);
const submitting = ref(false);
const editingId = ref(null);
const formRef = ref(null);

const form = reactive({
    slug: '',
    title: '',
    locale: 'zh-CN',
    content: '',
    meta: {
        title: '',
        description: '',
        keywords: '',
    },
});

const modeKeys = ['cms', 'static_fallback', 'static_form', 'draft_only'];

const modeLabels = computed(() => Object.fromEntries(
    modeKeys.map((key) => [key, t(`${P}.modes.${key}`)]),
));

const localeOptions = computed(() => [
    { label: t('email_templates_page.locale_zh'), value: 'zh-CN' },
    { label: t('email_templates_page.locale_en'), value: 'en' },
]);

const dialogTitle = computed(() => (
    editingId.value ? t(`${P}.dialog_edit`) : t(`${P}.dialog_create`)
));

const formRules = computed(() => ({
    slug: [{ required: true, message: t(`${P}.validation.slug_required`), trigger: 'blur' }],
    title: [{ required: true, message: t(`${P}.validation.title_required`), trigger: 'blur' }],
}));

function formatDate(dateStr) {
    if (!dateStr) return null;
    return new Date(dateStr).toLocaleString(locale.value === 'zh_CN' ? 'zh-CN' : 'en-US', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

async function loadPages() {
    loading.value = true;
    try {
        const { data: res } = await settingApi.pages({ per_page: 50 });
        pages.value = res.data || [];
    } catch {
        pages.value = [];
    } finally {
        loading.value = false;
    }
}

function openEditDialog(row) {
    editingId.value = row ? row.id : null;
    form.slug = row?.slug || '';
    form.title = row?.title || '';
    form.locale = row?.locale || 'zh-CN';
    form.content = row?.content || '';
    form.meta = {
        title: row?.meta?.title || '',
        description: row?.meta?.description || '',
        keywords: row?.meta?.keywords || '',
    };
    dialogVisible.value = true;
}

async function submitForm() {
    const valid = await formRef.value.validate().catch(() => false);
    if (!valid) return;

    submitting.value = true;
    try {
        const payload = {
            title: form.title,
            content: form.content,
            locale: form.locale,
            meta: form.meta,
        };

        if (editingId.value) {
            await settingApi.pageUpdate(editingId.value, payload);
            ElMessage.success(t(`${P}.msg_updated`));
        } else {
            payload.slug = form.slug;
            await settingApi.pageCreate(payload);
            ElMessage.success(t(`${P}.msg_created`));
        }
        dialogVisible.value = false;
        loadPages();
    } catch {
        // handled by interceptor
    } finally {
        submitting.value = false;
    }
}

function modeLabel(mode) {
    return modeLabels.value[mode] || t(`${P}.modes.unknown`);
}

function openFrontend(row) {
    const url = row.linkage?.url || `/${row.slug}`;
    window.open(url, '_blank');
}

async function handlePublish(row) {
    try {
        const tip = row.linkage?.hint
            ? t(`${P}.confirm_publish_with_hint`, { hint: row.linkage.hint, title: row.title })
            : t(`${P}.confirm_publish_body`, { title: row.title });
        await ElMessageBox.confirm(tip, t('blog_page.confirm_publish'), {
            confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'info',
        });
        const { data: res } = await settingApi.pagePublish(row.id);
        ElMessage.success(res?.message || t(`${P}.msg_published`));
        loadPages();
    } catch (e) {
        if (e === 'cancel' || e?.toString?.().includes('cancel')) return;
    }
}

async function handleDraft(row) {
    try {
        await ElMessageBox.confirm(
            t(`${P}.confirm_revert_body`, { title: row.title }),
            t(`${P}.confirm_revert_title`),
            {
                confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning',
            },
        );
        const { data: res } = await settingApi.pageDraft(row.id);
        ElMessage.success(res?.message || t(`${P}.msg_reverted`));
        loadPages();
    } catch { /* cancelled */ }
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(
            t(`${P}.confirm_delete_body`, { title: row.title }),
            t('tags_page.delete_title'),
            {
                confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning',
            },
        );
        await settingApi.pageDelete(row.id);
        ElMessage.success(t(`${P}.msg_deleted`));
        loadPages();
    } catch { /* cancelled */ }
}

onMounted(() => {
    loadPages();
});
</script>

<style scoped>
.pages-page { padding: 20px; }
.mb-4 { margin-bottom: 16px; }

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

code {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 12px;
    background: var(--el-fill-color-light);
    padding: 2px 6px;
    border-radius: 3px;
}

:deep(.el-card__body) { padding: 16px; }
</style>
