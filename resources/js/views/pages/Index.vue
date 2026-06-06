<template>
    <div class="pages-page">
        <div class="page-header">
            <div class="header-left">
                <h2>页面管理</h2>
                <span class="header-subtitle">管理公开页面内容</span>
            </div>
            <div class="header-right">
                <el-button type="primary" @click="openEditDialog(null)">
                    <el-icon><Plus /></el-icon>
                    新建页面
                </el-button>
            </div>
        </div>

        <!-- 表格 -->
        <el-card shadow="never">
            <el-table :data="pages" v-loading="loading" stripe>
                <el-table-column label="页面标识" width="150" prop="slug">
                    <template #default="{ row }">
                        <code>{{ row.slug }}</code>
                    </template>
                </el-table-column>
                <el-table-column label="标题" min-width="200" prop="title" />
                <el-table-column label="状态" width="100" prop="status">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'published' ? 'success' : 'info'" size="small">
                            {{ row.status === 'published' ? '已发布' : '草稿' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="版本" width="70" prop="version" align="center">
                    <template #default="{ row }">
                        <el-tag type="primary" effect="plain" size="small">v{{ row.version }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="语言" width="80" prop="locale" />
                <el-table-column label="发布时间" width="170" prop="published_at">
                    <template #default="{ row }">
                        {{ formatDate(row.published_at) || '-' }}
                    </template>
                </el-table-column>
                <el-table-column label="创建时间" width="170" prop="created_at">
                    <template #default="{ row }">
                        {{ formatDate(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="240" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="openEditDialog(row)">
                            编辑
                        </el-button>
                        <el-button
                            v-if="row.status === 'draft'"
                            text
                            size="small"
                            type="success"
                            @click="handlePublish(row)"
                        >
                            发布
                        </el-button>
                        <el-button
                            v-if="row.status === 'published'"
                            text
                            size="small"
                            type="warning"
                            @click="handleDraft(row)"
                        >
                            撤回
                        </el-button>
                        <el-button text size="small" type="danger" @click="handleDelete(row)">
                            删除
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 编辑 Dialog -->
        <el-dialog
            v-model="dialogVisible"
            :title="editingId ? '编辑页面' : '新建页面'"
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
                <el-form-item label="页面标识" prop="slug">
                    <el-input v-model="form.slug" :disabled="!!editingId" placeholder="如: about, privacy, terms" style="max-width: 300px;" />
                </el-form-item>
                <el-form-item label="标题" prop="title">
                    <el-input v-model="form.title" placeholder="页面标题" />
                </el-form-item>
                <el-form-item label="语言" prop="locale">
                    <el-select v-model="form.locale" style="width: 150px;">
                        <el-option label="中文" value="zh-CN" />
                        <el-option label="英文" value="en" />
                    </el-select>
                </el-form-item>
                <el-form-item label="内容" prop="content">
                    <el-input
                        v-model="form.content"
                        type="textarea"
                        :rows="12"
                        placeholder="页面内容 (支持 HTML)"
                    />
                </el-form-item>
                <el-divider>SEO 设置</el-divider>
                <el-form-item label="SEO 标题" prop="meta.title">
                    <el-input v-model="form.meta.title" placeholder="SEO 标题（可选）" />
                </el-form-item>
                <el-form-item label="SEO 描述" prop="meta.description">
                    <el-input v-model="form.meta.description" type="textarea" :rows="2" placeholder="SEO 描述（可选）" />
                </el-form-item>
                <el-form-item label="关键词" prop="meta.keywords">
                    <el-input v-model="form.meta.keywords" placeholder="关键词，逗号分隔（可选）" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="submitForm">
                    {{ editingId ? '保存' : '创建' }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import settingApi from '@/api/setting';

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

const formRules = {
    slug: [{ required: true, message: '请输入页面标识', trigger: 'blur' }],
    title: [{ required: true, message: '请输入页面标题', trigger: 'blur' }],
};

function formatDate(dateStr) {
    if (!dateStr) return null;
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

async function loadPages() {
    loading.value = true;
    try {
        const { data: res } = await settingApi.pages({ per_page: 50 });
        pages.value = res.data?.data || [];
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
            ElMessage.success('页面更新成功');
        } else {
            payload.slug = form.slug;
            await settingApi.pageCreate(payload);
            ElMessage.success('页面创建成功');
        }
        dialogVisible.value = false;
        loadPages();
    } catch {
        // handled by interceptor
    } finally {
        submitting.value = false;
    }
}

async function handlePublish(row) {
    try {
        await ElMessageBox.confirm(`确定要发布「${row.title}」吗？`, '确认发布', {
            confirmButtonText: '确定', cancelButtonText: '取消', type: 'info',
        });
        await settingApi.pagePublish(row.id);
        ElMessage.success('页面已发布');
        loadPages();
    } catch { /* cancelled */ }
}

async function handleDraft(row) {
    try {
        await ElMessageBox.confirm(`确定要将「${row.title}」撤回为草稿吗？`, '确认撤回', {
            confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning',
        });
        await settingApi.pageDraft(row.id);
        ElMessage.success('已撤回为草稿');
        loadPages();
    } catch { /* cancelled */ }
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(`确定要删除「${row.title}」吗？`, '确认删除', {
            confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning',
        });
        await settingApi.pageDelete(row.id);
        ElMessage.success('页面已删除');
        loadPages();
    } catch { /* cancelled */ }
}

onMounted(() => {
    loadPages();
});
</script>

<style scoped>
.pages-page { padding: 20px; }

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
