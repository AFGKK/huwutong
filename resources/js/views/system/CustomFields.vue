<template>
    <div class="custom-fields-page">
        <div class="page-header">
            <h2>自定义字段管理</h2>
            <p class="text-muted">为 License / 客户 / 产品添加可扩展的自定义字段</p>
        </div>

        <!-- 工具条 -->
        <el-card class="toolbar-card">
            <el-form :inline="true" :model="filterForm" size="small">
                <el-form-item label="目标实体">
                    <el-select v-model="filterForm.applies_to" clearable placeholder="全部" style="width: 130px">
                        <el-option label="License" value="license" />
                        <el-option label="客户" value="customer" />
                        <el-option label="产品" value="product" />
                    </el-select>
                </el-form-item>
                <el-form-item label="类型">
                    <el-select v-model="filterForm.field_type" clearable placeholder="全部类型" style="width: 130px">
                        <el-option v-for="ft in fieldTypes" :key="ft.value" :label="ft.label" :value="ft.value" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadDefinitions" :icon="Search">查询</el-button>
                    <el-button @click="resetFilter" :icon="Refresh">重置</el-button>
                </el-form-item>
                <el-form-item style="float: right">
                    <el-button type="success" @click="openCreateDialog" :icon="Plus">新建字段</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 字段定义列表 -->
        <el-card>
            <el-table :data="definitions" v-loading="loading" stripe empty-text="暂无自定义字段" style="width: 100%">
                <el-table-column prop="id" label="ID" width="60" />
                <el-table-column prop="name" label="字段名称" width="140">
                    <template #default="{ row }">
                        <span class="field-name">{{ row.name }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="slug" label="标识" width="130">
                    <template #default="{ row }">
                        <code>{{ row.slug }}</code>
                    </template>
                </el-table-column>
                <el-table-column prop="field_type" label="类型" width="100">
                    <template #default="{ row }">
                        <el-tag size="small">{{ typeLabel(row.field_type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="applies_to" label="适用实体" width="180">
                    <template #default="{ row }">
                        <el-tag v-for="e in (row.applies_to || ['license'])" :key="e" size="small" class="mr-1" type="info">
                            {{ entityLabel(e) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="group" label="分组" width="100">
                    <template #default="{ row }">{{ row.group || '-' }}</template>
                </el-table-column>
                <el-table-column prop="is_required" label="必填" width="60" align="center">
                    <template #default="{ row }">
                        <el-icon v-if="row.is_required" color="#e6a23c"><WarningFilled /></el-icon>
                        <span v-else class="text-muted">-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="is_active" label="启用" width="60" align="center">
                    <template #default="{ row }">
                        <el-switch v-model="row.is_active" @change="toggleActive(row)" size="small" />
                    </template>
                </el-table-column>
                <el-table-column prop="sort_order" label="排序" width="70" align="center" />
                <el-table-column prop="description" label="描述" min-width="160" show-overflow-tooltip />
                <el-table-column label="操作" width="160" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="openEditDialog(row)">编辑</el-button>
                        <el-popconfirm
                            title="确认删除此字段？关联的所有字段值也将被删除。"
                            @confirm="handleDelete(row)"
                        >
                            <template #reference>
                                <el-button text size="small" type="danger">删除</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 新建/编辑字段弹窗 -->
        <el-dialog v-model="dialog.visible" :title="dialog.isEdit ? '编辑自定义字段' : '新建自定义字段'" width="600px">
            <el-form :model="form" :rules="formRules" ref="formRef" label-width="120px" size="small">
                <el-form-item label="字段名称" prop="name">
                    <el-input v-model="form.name" placeholder="例如：客户行业、部门规模" maxlength="100" show-word-limit />
                </el-form-item>
                <el-form-item label="字段类型" prop="field_type">
                    <el-select v-model="form.field_type" style="width: 100%">
                        <el-option v-for="ft in fieldTypes" :key="ft.value" :label="ft.label" :value="ft.value" />
                    </el-select>
                </el-form-item>
                <el-form-item label="适用实体" prop="applies_to">
                    <el-checkbox-group v-model="form.applies_to">
                        <el-checkbox value="license" label="License" />
                        <el-checkbox value="customer" label="客户" />
                        <el-checkbox value="product" label="产品" />
                    </el-checkbox-group>
                </el-form-item>
                <el-form-item v-if="isSelectType" label="选项值" prop="options">
                    <el-select
                        v-model="form.options"
                        multiple
                        allow-create
                        filterable
                        default-first-option
                        placeholder="输入选项后回车添加"
                        style="width: 100%"
                    >
                        <el-option v-for="opt in form.options" :key="opt" :label="opt" :value="opt" />
                    </el-select>
                    <div class="text-muted small">输入选项文字后按回车添加</div>
                </el-form-item>
                <el-form-item label="分组">
                    <el-input v-model="form.group" placeholder="例如：基本信息、业务信息" maxlength="100" />
                </el-form-item>
                <el-form-item label="默认值">
                    <el-input v-model="form.default_value" maxlength="255" />
                </el-form-item>
                <el-form-item label="占位文本">
                    <el-input v-model="form.placeholder" maxlength="255" />
                </el-form-item>
                <el-form-item label="描述">
                    <el-input v-model="form.description" type="textarea" :rows="2" maxlength="1000" show-word-limit />
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="form.sort_order" :min="0" :max="999" style="width: 120px" />
                </el-form-item>
                <el-form-item label="必填">
                    <el-switch v-model="form.is_required" />
                </el-form-item>
                <el-form-item label="启用">
                    <el-switch v-model="form.is_active" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialog.visible = false">取消</el-button>
                <el-button type="primary" @click="submitForm" :loading="submitting">
                    {{ dialog.isEdit ? '保存修改' : '创建字段' }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { Search, Refresh, Plus, WarningFilled } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import customFieldsApi from '../../api/customFields';

// ─── 状态 ───
const loading = ref(false);
const definitions = ref([]);
const fieldTypes = ref([]);
const entityTypes = ref([]);
const submitting = ref(false);

const filterForm = reactive({
    applies_to: '',
    field_type: '',
});

const formRef = ref(null);
const form = reactive({
    name: '',
    field_type: 'text',
    applies_to: ['license'],
    options: [],
    placeholder: '',
    description: '',
    is_required: false,
    is_active: true,
    sort_order: 0,
    group: '',
    default_value: '',
});

const dialog = reactive({
    visible: false,
    isEdit: false,
    editId: null,
});

// ─── 计算属性 ───
const isSelectType = computed(() =>
    ['select', 'multi_select'].includes(form.field_type)
);

// ─── 校验规则 ───
const formRules = {
    name: [{ required: true, message: '请输入字段名称', trigger: 'blur' }],
    field_type: [{ required: true, message: '请选择字段类型', trigger: 'change' }],
    applies_to: [{ required: true, type: 'array', min: 1, message: '请至少选择一个目标实体', trigger: 'change' }],
};

// ─── 方法 ───

function typeLabel(type) {
    const map = {};
    fieldTypes.value.forEach(ft => { map[ft.value] = ft.label; });
    return map[type] || type;
}

function entityLabel(entity) {
    const map = { license: 'License', customer: '客户', product: '产品' };
    return map[entity] || entity;
}

async function loadMetadata() {
    try {
        const res = await customFieldsApi.getMetadata();
        fieldTypes.value = res.data.field_types || [];
        entityTypes.value = res.data.entity_types || [];
    } catch (e) {
        console.error('Failed to load metadata:', e);
    }
}

async function loadDefinitions() {
    loading.value = true;
    try {
        const params = {};
        if (filterForm.applies_to) params.applies_to = filterForm.applies_to;
        if (filterForm.field_type) params.field_type = filterForm.field_type;

        const res = await customFieldsApi.getDefinitions(params);
        definitions.value = res.data || [];
    } catch (e) {
        console.error('Failed to load definitions:', e);
        ElMessage.error('加载字段定义失败');
    } finally {
        loading.value = false;
    }
}

function resetFilter() {
    filterForm.applies_to = '';
    filterForm.field_type = '';
    loadDefinitions();
}

function resetForm() {
    form.name = '';
    form.field_type = 'text';
    form.applies_to = ['license'];
    form.options = [];
    form.placeholder = '';
    form.description = '';
    form.is_required = false;
    form.is_active = true;
    form.sort_order = 0;
    form.group = '';
    form.default_value = '';
}

function openCreateDialog() {
    resetForm();
    dialog.isEdit = false;
    dialog.editId = null;
    dialog.visible = true;
}

function openEditDialog(row) {
    resetForm();
    dialog.isEdit = true;
    dialog.editId = row.id;
    form.name = row.name;
    form.field_type = row.field_type;
    form.applies_to = row.applies_to || ['license'];
    form.options = row.options || [];
    form.placeholder = row.placeholder || '';
    form.description = row.description || '';
    form.is_required = row.is_required;
    form.is_active = row.is_active;
    form.sort_order = row.sort_order;
    form.group = row.group || '';
    form.default_value = row.default_value || '';
    dialog.visible = true;
}

async function submitForm() {
    const valid = await formRef.value?.validate().catch(() => false);
    if (!valid) return;

    submitting.value = true;
    try {
        const payload = {
            name: form.name,
            field_type: form.field_type,
            applies_to: form.applies_to,
            options: isSelectType.value ? form.options : null,
            placeholder: form.placeholder || null,
            description: form.description || null,
            is_required: form.is_required,
            is_active: form.is_active,
            sort_order: form.sort_order,
            group: form.group || null,
            default_value: form.default_value || null,
        };

        if (dialog.isEdit) {
            await customFieldsApi.updateDefinition(dialog.editId, payload);
            ElMessage.success('字段已更新');
        } else {
            await customFieldsApi.createDefinition(payload);
            ElMessage.success('字段创建成功');
        }

        dialog.visible = false;
        loadDefinitions();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败');
    } finally {
        submitting.value = false;
    }
}

async function handleDelete(row) {
    try {
        await customFieldsApi.deleteDefinition(row.id);
        ElMessage.success('字段已删除');
        loadDefinitions();
    } catch (e) {
        ElMessage.error('删除失败');
    }
}

async function toggleActive(row) {
    try {
        await customFieldsApi.updateDefinition(row.id, { is_active: row.is_active });
    } catch (e) {
        row.is_active = !row.is_active;
        ElMessage.error('更新状态失败');
    }
}

// ─── 初始化 ───
onMounted(() => {
    loadMetadata();
    loadDefinitions();
});
</script>

<style scoped>
.custom-fields-page {
    padding: 20px;
}

.page-header {
    margin-bottom: 20px;
}

.page-header h2 {
    margin: 0 0 8px;
    font-size: 22px;
}

.text-muted {
    color: #909399;
    font-size: 13px;
}

.toolbar-card {
    margin-bottom: 16px;
}

.field-name {
    font-weight: 600;
}

code {
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
    color: #606266;
}

.mr-1 {
    margin-right: 4px;
}

.small {
    font-size: 12px;
}
</style>
