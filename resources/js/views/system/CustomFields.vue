<template>
    <div class="custom-fields-page">
        <div class="page-header">
            <h2>{{ t('custom_fields_page.title') }}</h2>
            <p class="text-muted">{{ t('custom_fields_page.subtitle') }}</p>
        </div>

        <!-- 工具条 -->
        <el-card class="toolbar-card">
            <el-form :inline="true" :model="filterForm" size="small">
                <el-form-item :label="t('custom_fields_page.filter_applies_to')">
                    <el-select v-model="filterForm.applies_to" clearable :placeholder="t('custom_fields_page.filter_all')" style="width: 130px">
                        <el-option
                            v-for="entity in entityOptions"
                            :key="entity.value"
                            :label="entity.label"
                            :value="entity.value"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('custom_fields_page.filter_type')">
                    <el-select v-model="filterForm.field_type" clearable :placeholder="t('custom_fields_page.filter_all_types')" style="width: 130px">
                        <el-option v-for="ft in localizedFieldTypes" :key="ft.value" :label="ft.label" :value="ft.value" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadDefinitions" :icon="Search">{{ t('actions.search') }}</el-button>
                    <el-button @click="resetFilter" :icon="Refresh">{{ t('actions.reset') }}</el-button>
                </el-form-item>
                <el-form-item style="float: right">
                    <el-button type="success" @click="openCreateDialog" :icon="Plus">{{ t('custom_fields_page.create_btn') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 字段定义列表 -->
        <el-card>
            <el-table :data="definitions" v-loading="loading" stripe :empty-text="t('custom_fields_page.empty')" style="width: 100%">
                <el-table-column prop="id" :label="t('custom_fields_page.col_id')" width="60" />
                <el-table-column prop="name" :label="t('custom_fields_page.col_name')" width="140">
                    <template #default="{ row }">
                        <span class="field-name">{{ row.name }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="slug" :label="t('custom_fields_page.col_slug')" width="130">
                    <template #default="{ row }">
                        <code>{{ row.slug }}</code>
                    </template>
                </el-table-column>
                <el-table-column prop="field_type" :label="t('custom_fields_page.col_type')" width="100">
                    <template #default="{ row }">
                        <el-tag size="small">{{ typeLabel(row.field_type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="applies_to" :label="t('custom_fields_page.col_applies_to')" width="180">
                    <template #default="{ row }">
                        <el-tag v-for="e in (row.applies_to || ['license'])" :key="e" size="small" class="mr-1" type="info">
                            {{ entityLabel(e) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="group" :label="t('custom_fields_page.col_group')" width="100">
                    <template #default="{ row }">{{ row.group || '-' }}</template>
                </el-table-column>
                <el-table-column prop="is_required" :label="t('custom_fields_page.col_required')" width="60" align="center">
                    <template #default="{ row }">
                        <el-icon v-if="row.is_required" color="#e6a23c"><WarningFilled /></el-icon>
                        <span v-else class="text-muted">-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="is_active" :label="t('custom_fields_page.col_active')" width="60" align="center">
                    <template #default="{ row }">
                        <el-switch v-model="row.is_active" @change="toggleActive(row)" size="small" />
                    </template>
                </el-table-column>
                <el-table-column prop="sort_order" :label="t('custom_fields_page.col_sort')" width="70" align="center" />
                <el-table-column prop="description" :label="t('custom_fields_page.col_description')" min-width="160" show-overflow-tooltip />
                <el-table-column :label="t('custom_fields_page.col_actions')" width="160" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="openEditDialog(row)">{{ t('actions.edit') }}</el-button>
                        <el-popconfirm
                            :title="t('custom_fields_page.delete_confirm')"
                            @confirm="handleDelete(row)"
                        >
                            <template #reference>
                                <el-button text size="small" type="danger">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 新建/编辑字段弹窗 -->
        <el-dialog
            v-model="dialog.visible"
            :title="dialog.isEdit ? t('custom_fields_page.edit_dialog_title') : t('custom_fields_page.create_dialog_title')"
            width="600px"
        >
            <el-form :model="form" :rules="formRules" ref="formRef" label-width="120px" size="small">
                <el-form-item :label="t('custom_fields_page.name_label')" prop="name">
                    <el-input v-model="form.name" :placeholder="t('custom_fields_page.name_ph')" maxlength="100" show-word-limit />
                </el-form-item>
                <el-form-item :label="t('custom_fields_page.field_type_label')" prop="field_type">
                    <el-select v-model="form.field_type" style="width: 100%">
                        <el-option v-for="ft in localizedFieldTypes" :key="ft.value" :label="ft.label" :value="ft.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('custom_fields_page.applies_to_label')" prop="applies_to">
                    <el-checkbox-group v-model="form.applies_to">
                        <el-checkbox
                            v-for="entity in entityOptions"
                            :key="entity.value"
                            :label="entity.value"
                        >{{ entity.label }}</el-checkbox>
                    </el-checkbox-group>
                </el-form-item>
                <el-form-item v-if="isSelectType" :label="t('custom_fields_page.options_label')" prop="options">
                    <el-select
                        v-model="form.options"
                        multiple
                        allow-create
                        filterable
                        default-first-option
                        :placeholder="t('custom_fields_page.options_ph')"
                        style="width: 100%"
                    >
                        <el-option v-for="opt in form.options" :key="opt" :label="opt" :value="opt" />
                    </el-select>
                    <div class="text-muted small">{{ t('custom_fields_page.options_tip') }}</div>
                </el-form-item>
                <el-form-item :label="t('custom_fields_page.group_label')">
                    <el-input v-model="form.group" :placeholder="t('custom_fields_page.group_ph')" maxlength="100" />
                </el-form-item>
                <el-form-item :label="t('custom_fields_page.default_value_label')">
                    <el-input v-model="form.default_value" maxlength="255" />
                </el-form-item>
                <el-form-item :label="t('custom_fields_page.placeholder_label')">
                    <el-input v-model="form.placeholder" maxlength="255" />
                </el-form-item>
                <el-form-item :label="t('custom_fields_page.description_label')">
                    <el-input v-model="form.description" type="textarea" :rows="2" maxlength="1000" show-word-limit />
                </el-form-item>
                <el-form-item :label="t('custom_fields_page.sort_label')">
                    <el-input-number v-model="form.sort_order" :min="0" :max="999" style="width: 120px" />
                </el-form-item>
                <el-form-item :label="t('custom_fields_page.required_label')">
                    <el-switch v-model="form.is_required" />
                </el-form-item>
                <el-form-item :label="t('custom_fields_page.active_label')">
                    <el-switch v-model="form.is_active" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialog.visible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="submitForm" :loading="submitting">
                    {{ dialog.isEdit ? t('custom_fields_page.save_btn') : t('custom_fields_page.create_field_btn') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Search, Refresh, Plus, WarningFilled } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import customFieldsApi from '../../api/customFields';

const { t } = useI18n();

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
const entityLabels = computed(() => ({
    license: t('custom_fields_page.entities.license'),
    customer: t('custom_fields_page.entities.customer'),
    product: t('custom_fields_page.entities.product'),
}));

const entityOptions = computed(() =>
    (entityTypes.value.length ? entityTypes.value : ['license', 'customer', 'product']).map(value => ({
        value,
        label: entityLabels.value[value] || value,
    }))
);

const fieldTypeLabels = computed(() => ({
    text: t('custom_fields_page.field_types.text'),
    textarea: t('custom_fields_page.field_types.textarea'),
    number: t('custom_fields_page.field_types.number'),
    select: t('custom_fields_page.field_types.select'),
    multi_select: t('custom_fields_page.field_types.multi_select'),
    date: t('custom_fields_page.field_types.date'),
    boolean: t('custom_fields_page.field_types.boolean'),
    url: t('custom_fields_page.field_types.url'),
    email: t('custom_fields_page.field_types.email'),
    color: t('custom_fields_page.field_types.color'),
}));

const localizedFieldTypes = computed(() =>
    fieldTypes.value.map(ft => ({
        value: ft.value,
        label: fieldTypeLabels.value[ft.value] || ft.label || ft.value,
    }))
);

const isSelectType = computed(() =>
    ['select', 'multi_select'].includes(form.field_type)
);

const formRules = computed(() => ({
    name: [{ required: true, message: t('custom_fields_page.rules.name_required'), trigger: 'blur' }],
    field_type: [{ required: true, message: t('custom_fields_page.rules.field_type_required'), trigger: 'change' }],
    applies_to: [{ required: true, type: 'array', min: 1, message: t('custom_fields_page.rules.applies_to_required'), trigger: 'change' }],
}));

// ─── 方法 ───

function typeLabel(type) {
    return fieldTypeLabels.value[type] || type;
}

function entityLabel(entity) {
    return entityLabels.value[entity] || entity;
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
        ElMessage.error(t('messages.load_failed'));
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
            ElMessage.success(t('custom_fields_page.messages.update_ok'));
        } else {
            await customFieldsApi.createDefinition(payload);
            ElMessage.success(t('custom_fields_page.messages.create_ok'));
        }

        dialog.visible = false;
        loadDefinitions();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'));
    } finally {
        submitting.value = false;
    }
}

async function handleDelete(row) {
    try {
        await customFieldsApi.deleteDefinition(row.id);
        ElMessage.success(t('custom_fields_page.messages.delete_ok'));
        loadDefinitions();
    } catch (e) {
        ElMessage.error(t('custom_fields_page.messages.delete_failed'));
    }
}

async function toggleActive(row) {
    try {
        await customFieldsApi.updateDefinition(row.id, { is_active: row.is_active });
    } catch (e) {
        row.is_active = !row.is_active;
        ElMessage.error(t('custom_fields_page.messages.toggle_failed'));
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
