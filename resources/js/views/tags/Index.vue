<template>
    <div class="tag-manager-page">
        <div class="page-header">
            <div class="header-left">
                <h2>标签管理</h2>
                <span class="header-subtitle">管理系统中的标签，用于分类和筛选 License/工单/客户</span>
            </div>
            <div class="header-right">
                <el-button type="primary" @click="openCreateDialog">
                    <el-icon><Plus /></el-icon>
                    新建标签
                </el-button>
            </div>
        </div>

        <el-card shadow="never">
            <div class="tag-group-section" v-for="group in groupedTags" :key="group.key">
                <div class="group-header">
                    <h3 class="group-title">{{ groupLabels[group.key] || group.key || '未分组' }}</h3>
                    <span class="group-count">{{ group.items.length }} 个标签</span>
                </div>
                <div class="tag-grid">
                    <div
                        v-for="tag in group.items"
                        :key="tag.id"
                        class="tag-card"
                        :style="{ borderLeftColor: tag.color }"
                    >
                        <div class="tag-card-header">
                            <el-tag :color="tag.color" effect="dark" size="small">
                                {{ tag.name }}
                            </el-tag>
                            <el-tag v-if="tag.is_system" size="small" type="warning" effect="plain">系统</el-tag>
                        </div>
                        <div class="tag-card-slug" v-if="tag.slug">/ {{ tag.slug }}</div>
                        <div class="tag-card-desc" v-if="tag.description">{{ tag.description }}</div>
                        <div class="tag-card-usage">
                            使用次数: <strong>{{ tag.usage_count || 0 }}</strong>
                        </div>
                        <div class="tag-card-actions">
                            <el-button text size="small" type="primary" @click="openEditDialog(tag)">
                                编辑
                            </el-button>
                            <el-button
                                text
                                size="small"
                                type="danger"
                                :disabled="tag.is_system"
                                @click="handleDelete(tag)"
                            >
                                删除
                            </el-button>
                        </div>
                    </div>
                </div>
                <el-divider v-if="group.key !== lastGroupKey" />
            </div>

            <el-empty v-if="allTags.length === 0" description="暂无标签" />
        </el-card>

        <!-- Dialogs -->
        <el-dialog
            v-model="dialogVisible"
            :title="isEditing ? '编辑标签' : '新建标签'"
            width="480px"
            :close-on-click-modal="false"
        >
            <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
                <el-form-item label="标签名称" prop="name">
                    <el-input v-model="form.name" placeholder="输入标签名称" maxlength="100" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="分组" prop="group">
                            <el-select v-model="form.group" placeholder="选择分组" clearable filterable allow-create style="width:100%">
                                <el-option
                                    v-for="g in groupOptions"
                                    :key="g"
                                    :label="g"
                                    :value="g"
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="颜色" prop="color">
                            <el-color-picker v-model="form.color" :predefine="presetColors" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="描述" prop="description">
                    <el-input
                        v-model="form.description"
                        placeholder="简要描述标签用途"
                        type="textarea"
                        :rows="2"
                        maxlength="500"
                        show-word-limit
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="handleSave">
                    {{ isEditing ? '保存' : '创建' }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import tagApi from '@/api/tag';

const allTags = ref([]);
const loading = ref(false);
const saving = ref(false);
const dialogVisible = ref(false);
const isEditing = ref(false);
const formRef = ref(null);

const groupLabels = {
    priority: '优先级',
    status: '状态',
    type: '类型',
    tier: '客户等级',
    alert: '预警',
};

const groupOptions = ['priority', 'status', 'type', 'tier', 'alert', 'custom'];

const presetColors = [
    '#409EFF', '#67C23A', '#E6A23C', '#F56C6C', '#909399',
    '#E040FB', '#00BCD4', '#FF9800', '#795548', '#607D8B',
    '#9C27B0', '#2196F3', '#4CAF50', '#FF5722', '#3F51B5',
];

const form = ref({
    id: null,
    name: '',
    group: '',
    color: '#409EFF',
    description: '',
});

const rules = {
    name: [{ required: true, message: '请输入标签名称', trigger: 'blur' }],
    color: [{ required: true, message: '请选择颜色', trigger: 'change' }],
};

const groupedTags = computed(() => {
    const groups = {};
    allTags.value.forEach(tag => {
        const key = tag.group || '_ungrouped';
        if (!groups[key]) groups[key] = { key, items: [] };
        groups[key].items.push(tag);
    });
    // Sort groups: custom order
    const order = ['priority', 'status', 'type', 'tier', 'alert', '_ungrouped'];
    const sorted = [];
    order.forEach(k => {
        if (groups[k]) {
            sorted.push(groups[k]);
            delete groups[k];
        }
    });
    // Remaining (custom groups)
    Object.keys(groups).sort().forEach(k => sorted.push(groups[k]));
    return sorted;
});

const lastGroupKey = computed(() => {
    const g = groupedTags.value;
    return g.length > 0 ? g[g.length - 1].key : null;
});

async function fetchTags() {
    loading.value = true;
    try {
        const { data: res } = await tagApi.list({ per_page: 200 });
        if (res.success) {
            allTags.value = res.data?.data || [];
        }
    } catch {
        // ignore
    } finally {
        loading.value = false;
    }
}

function openCreateDialog() {
    isEditing.value = false;
    form.value = { id: null, name: '', group: '', color: '#409EFF', description: '' };
    dialogVisible.value = true;
}

function openEditDialog(tag) {
    isEditing.value = true;
    form.value = {
        id: tag.id,
        name: tag.name,
        group: tag.group || '',
        color: tag.color || '#409EFF',
        description: tag.description || '',
    };
    dialogVisible.value = true;
}

async function handleSave() {
    const valid = await formRef.value?.validate().catch(() => false);
    if (!valid) return;

    saving.value = true;
    try {
        let res;
        if (isEditing.value) {
            res = await tagApi.update(form.value.id, {
                name: form.value.name,
                group: form.value.group || null,
                color: form.value.color,
                description: form.value.description || null,
            });
        } else {
            res = await tagApi.create({
                name: form.value.name,
                group: form.value.group || null,
                color: form.value.color,
                description: form.value.description || null,
            });
        }

        if (res.data.success) {
            ElMessage.success(isEditing.value ? '标签已更新' : '标签已创建');
            dialogVisible.value = false;
            await fetchTags();
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '操作失败');
    } finally {
        saving.value = false;
    }
}

async function handleDelete(tag) {
    try {
        await ElMessageBox.confirm(
            `确定要删除标签「${tag.name}」吗？该操作将从所有关联对象中移除。`,
            '确认删除',
            { confirmButtonText: '删除', cancelButtonText: '取消', type: 'warning' }
        );

        const { data: res } = await tagApi.destroy(tag.id);
        if (res.success) {
            ElMessage.success('标签已删除');
            await fetchTags();
        }
    } catch {
        // cancelled or error
    }
}

onMounted(() => {
    fetchTags();
});
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.header-left h2 {
    margin: 0 0 4px;
}
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
}
.tag-group-section {
    margin-bottom: 8px;
}
.group-header {
    display: flex;
    align-items: baseline;
    gap: 12px;
    margin-bottom: 12px;
}
.group-title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}
.group-count {
    font-size: 12px;
    color: var(--el-text-color-secondary);
}
.tag-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px;
}
.tag-card {
    border: 1px solid var(--el-border-color-lighter);
    border-left: 4px solid #409eff;
    border-radius: 6px;
    padding: 12px;
    transition: box-shadow 0.2s;
}
.tag-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.tag-card-header {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
}
.tag-card-slug {
    font-size: 12px;
    color: var(--el-text-color-placeholder);
    margin-bottom: 4px;
}
.tag-card-desc {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-bottom: 6px;
}
.tag-card-usage {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-bottom: 8px;
}
.tag-card-actions {
    display: flex;
    gap: 4px;
}
</style>
