<template>
    <div class="custom-fields-page">
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>自定义字段管理</span>
                    <el-button type="primary" size="small" @click="openCreate">
                        <el-icon><Plus /></el-icon> 新建字段
                    </el-button>
                </div>
            </template>

            <el-table :data="fields" v-loading="loading" stripe>
                <el-table-column label="排序" width="60" align="center">
                    <template #default="{ row }">{{ row.sort_order }}</template>
                </el-table-column>
                <el-table-column prop="name" label="字段名称" width="160" />
                <el-table-column prop="slug" label="标识" width="140" />
                <el-table-column label="类型" width="100">
                    <template #default="{ row }">
                        <el-tag size="small">{{ typeLabel(row.field_type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="分组" width="100">
                    <template #default="{ row }">{{ row.group || '-' }}</template>
                </el-table-column>
                <el-table-column prop="description" label="说明" min-width="200">
                    <template #default="{ row }">{{ row.description || '-' }}</template>
                </el-table-column>
                <el-table-column label="必填" width="60" align="center">
                    <template #default="{ row }">
                        <el-icon v-if="row.is_required" color="#f56c6c"><WarningFilled /></el-icon>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="70">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                            {{ row.is_active ? '启用' : '停用' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="租户" width="80">
                    <template #default="{ row }">
                        <el-tag size="small" :type="row.tenant_id ? '' : 'info'">
                            {{ row.tenant_id ? '自定义' : '全局' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="160" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="openEdit(row)">编辑</el-button>
                        <el-button text size="small" type="danger" @click="handleDelete(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 字段编辑对话框 -->
        <el-dialog v-model="showDialog" :title="isEditing ? '编辑字段' : '新建字段'" width="550px">
            <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="字段名称" prop="name">
                            <el-input v-model="form.name" placeholder="如：客户部门" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="字段类型" prop="field_type">
                            <el-select v-model="form.field_type" style="width: 100%" @change="handleTypeChange">
                                <el-option label="文本" value="text" />
                                <el-option label="多行文本" value="textarea" />
                                <el-option label="数字" value="number" />
                                <el-option label="单选" value="select" />
                                <el-option label="多选" value="multi_select" />
                                <el-option label="日期" value="date" />
                                <el-option label="开关" value="boolean" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="分组">
                    <el-input v-model="form.group" placeholder="如：业务信息" />
                </el-form-item>
                <el-form-item label="说明">
                    <el-input v-model="form.description" type="textarea" :rows="2" placeholder="字段用途说明" />
                </el-form-item>
                <el-form-item label="占位文本">
                    <el-input v-model="form.placeholder" placeholder="输入框占位文本" />
                </el-form-item>
                <el-form-item label="默认值">
                    <el-input v-model="form.default_value" placeholder="默认值" />
                </el-form-item>

                <!-- 选项配置（select/multi_select） -->
                <template v-if="form.field_type === 'select' || form.field_type === 'multi_select'">
                    <el-form-item label="选项">
                        <div class="options-editor">
                            <div v-for="(opt, idx) in form.options" :key="idx" class="option-row">
                                <el-input v-model="form.options[idx]" placeholder="选项值" size="small" style="width: 300px" />
                                <el-button text size="small" type="danger" @click="form.options.splice(idx, 1)">
                                    <el-icon><Remove /></el-icon>
                                </el-button>
                            </div>
                            <el-button size="small" @click="form.options.push('')" class="mt-2">
                                <el-icon><Plus /></el-icon> 添加选项
                            </el-button>
                        </div>
                    </el-form-item>
                </template>

                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="必填">
                            <el-switch v-model="form.is_required" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="启用">
                            <el-switch v-model="form.is_active" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="排序">
                    <el-input-number v-model="form.sort_order" :min="0" :max="999" style="width: 120px" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showDialog = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="confirmSubmit">{{ isEditing ? '保存修改' : '创建字段' }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Remove, WarningFilled } from '@element-plus/icons-vue';
import customFieldApi from '@/api/customField';

const fields = ref([]);
const loading = ref(false);
const showDialog = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const submitting = ref(false);
const formRef = ref(null);

const form = reactive({
    name: '', field_type: 'text', group: '', description: '',
    placeholder: '', default_value: '', options: [],
    is_required: false, is_active: true, sort_order: 0,
});

const formRules = {
    name: [{ required: true, message: '请输入字段名称', trigger: 'blur' }],
    field_type: [{ required: true, message: '请选择字段类型', trigger: 'change' }],
};

function typeLabel(type) {
    const map = { text: '文本', textarea: '多行文本', number: '数字', select: '单选', multi_select: '多选', date: '日期', boolean: '开关' };
    return map[type] || type;
}

function handleTypeChange() {
    if (form.field_type === 'select' || form.field_type === 'multi_select') {
        if (form.options.length === 0) {
            form.options = [''];
        }
    } else {
        form.options = [];
    }
}

function resetForm() {
    form.name = ''; form.field_type = 'text'; form.group = '';
    form.description = ''; form.placeholder = ''; form.default_value = '';
    form.options = []; form.is_required = false; form.is_active = true;
    form.sort_order = 0;
}

async function fetchFields() {
    loading.value = true;
    try {
        const res = await customFieldApi.list();
        fields.value = res.data?.data || [];
    } catch {
        ElMessage.error('获取字段列表失败');
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    isEditing.value = false;
    editingId.value = null;
    resetForm();
    showDialog.value = true;
}

function openEdit(row) {
    isEditing.value = true;
    editingId.value = row.id;
    form.name = row.name;
    form.field_type = row.field_type;
    form.group = row.group || '';
    form.description = row.description || '';
    form.placeholder = row.placeholder || '';
    form.default_value = row.default_value || '';
    form.options = row.options ? [...row.options] : [];
    form.is_required = row.is_required;
    form.is_active = row.is_active;
    form.sort_order = row.sort_order || 0;
    showDialog.value = true;
}

async function confirmSubmit() {
    const valid = await formRef.value?.validate().catch(() => false);
    if (!valid) return;

    submitting.value = true;
    try {
        const payload = {
            name: form.name,
            field_type: form.field_type,
            group: form.group || undefined,
            description: form.description || undefined,
            placeholder: form.placeholder || undefined,
            default_value: form.default_value || undefined,
            options: (form.field_type === 'select' || form.field_type === 'multi_select') ? form.options.filter(o => o.trim()) : undefined,
            is_required: form.is_required,
            is_active: form.is_active,
            sort_order: form.sort_order,
        };

        if (isEditing.value) {
            await customFieldApi.update(editingId.value, payload);
            ElMessage.success('字段已更新');
        } else {
            await customFieldApi.create(payload);
            ElMessage.success('字段已创建');
        }
        showDialog.value = false;
        await fetchFields();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '操作失败');
    } finally {
        submitting.value = false;
    }
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(`确定要删除字段「${row.name}」吗？所有关联的 License 数据也将被删除。`, '确认', { type: 'warning' });
        await customFieldApi.destroy(row.id);
        ElMessage.success('字段已删除');
        await fetchFields();
    } catch { /* cancelled */ }
}

onMounted(fetchFields);
</script>

<style scoped>
.card-header {
    display: flex; justify-content: space-between; align-items: center;
}

.options-editor { width: 100%; }
.option-row {
    display: flex; align-items: center; gap: 8px; margin-bottom: 4px;
}
.mt-2 { margin-top: 8px; }
</style>
