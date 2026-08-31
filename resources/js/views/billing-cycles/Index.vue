<template>
    <div class="billing-cycles-page">
        <div class="page-header">
            <h2>{{ t('billing_cycles_page.title') }}</h2>
            <el-button type="primary" @click="openCreateDialog">
                <el-icon><Plus /></el-icon> {{ t('billing_cycles_page.create_btn') }}
            </el-button>
        </div>

        <el-card shadow="never">
            <el-table :data="cycles" v-loading="loading" stripe>
                <template #empty>
                    <el-empty :description="t('billing_cycles_page.no_data')" :image-size="80" />
                </template>
                <el-table-column prop="code" :label="t('billing_cycles_page.cols.code')" width="150" />
                <el-table-column prop="name" :label="t('billing_cycles_page.cols.name')" width="150" />
                <el-table-column :label="t('billing_cycles_page.cols.period')" width="150">
                    <template #default="{ row }">
                        <span v-if="row.months && row.days">{{ row.months }}{{ t('billing_cycles_page.unit_month') }}{{ row.days }}{{ t('billing_cycles_page.unit_day') }}</span>
                        <span v-else-if="row.months">{{ t('billing_cycles_page.months_suffix', { n: row.months }) }}</span>
                        <span v-else-if="row.days">{{ t('billing_cycles_page.days_suffix', { n: row.days }) }}</span>
                        <span v-else class="text-muted">—</span>
                    </template>
                </el-table-column>
                <el-table-column prop="sort_order" :label="t('billing_cycles_page.cols.sort_order')" width="80" />
                <el-table-column :label="t('billing_cycles_page.cols.status')" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                            {{ statusLabel(row.is_active) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('billing_cycles_page.cols.actions')" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button text type="primary" size="small" @click="openEditDialog(row)">{{ t('actions.edit') }}</el-button>
                        <el-button text type="danger" size="small" @click="handleDelete(row)">{{ t('actions.delete') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 创建/编辑对话框 -->
        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="480px" destroy-on-close>
            <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
                <el-form-item :label="t('billing_cycles_page.form.code')" prop="code">
                    <el-input v-model="form.code" :placeholder="t('billing_cycles_page.form.code_ph')" :disabled="isEditing" />
                </el-form-item>
                <el-form-item :label="t('billing_cycles_page.form.name')" prop="name">
                    <el-input v-model="form.name" :placeholder="t('billing_cycles_page.form.name_ph')" />
                </el-form-item>
                <el-form-item :label="t('billing_cycles_page.form.months')">
                    <el-input-number v-model="form.months" :min="0" :max="120" style="width:200px" />
                    <span class="text-muted ml-2">{{ t('billing_cycles_page.form.months_hint') }}</span>
                </el-form-item>
                <el-form-item :label="t('billing_cycles_page.form.days')">
                    <el-input-number v-model="form.days" :min="0" :max="365" style="width:200px" />
                    <span class="text-muted ml-2">{{ t('billing_cycles_page.form.days_hint') }}</span>
                </el-form-item>
                <el-form-item :label="t('billing_cycles_page.form.sort_order')">
                    <el-input-number v-model="form.sort_order" :min="0" style="width:200px" />
                </el-form-item>
                <el-form-item :label="t('billing_cycles_page.form.status')">
                    <el-switch
                        v-model="form.is_active"
                        :active-text="t('billing_cycles_page.switch.active')"
                        :inactive-text="t('billing_cycles_page.switch.inactive')"
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="handleSave">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import { getBillingCycles, createBillingCycle, updateBillingCycle, deleteBillingCycle } from '@/api/billingCycle';

const { t } = useI18n();

const loading = ref(false);
const cycles = ref([]);
const dialogVisible = ref(false);
const isEditing = ref(false);
const saving = ref(false);
const formRef = ref(null);

const form = ref({
    code: '',
    name: '',
    months: null,
    days: null,
    sort_order: 0,
    is_active: true,
});

const statusLabels = computed(() => ({
    true: t('billing_cycles_page.status.active'),
    false: t('billing_cycles_page.status.inactive'),
}));

const dialogTitle = computed(() =>
    isEditing.value ? t('billing_cycles_page.dialog_edit') : t('billing_cycles_page.dialog_create'),
);

const rules = computed(() => ({
    code: [
        { required: true, message: t('billing_cycles_page.validation.code_required'), trigger: 'blur' },
        { pattern: /^[a-z][a-z0-9_-]*$/, message: t('billing_cycles_page.validation.code_pattern'), trigger: 'blur' },
    ],
    name: [{ required: true, message: t('billing_cycles_page.validation.name_required'), trigger: 'blur' }],
}));

function statusLabel(isActive) {
    return statusLabels.value[isActive ? 'true' : 'false'];
}

async function loadData() {
    loading.value = true;
    try {
        const res = await getBillingCycles();
        cycles.value = res.data?.data || [];
    } catch {
        ElMessage.error(t('messages.load_failed'));
    } finally {
        loading.value = false;
    }
}

function openCreateDialog() {
    isEditing.value = false;
    form.value = { code: '', name: '', months: null, days: null, sort_order: 0, is_active: true };
    dialogVisible.value = true;
}

function openEditDialog(row) {
    isEditing.value = true;
    form.value = {
        code: row.code,
        name: row.name,
        months: row.months,
        days: row.days,
        sort_order: row.sort_order ?? 0,
        is_active: row.is_active,
    };
    form.value._id = row.id;
    dialogVisible.value = true;
}

async function handleSave() {
    const valid = await formRef.value?.validate().catch(() => false);
    if (!valid) return;
    saving.value = true;
    try {
        if (isEditing.value) {
            await updateBillingCycle(form.value._id, form.value);
            ElMessage.success(t('billing_cycles_page.messages.updated'));
        } else {
            await createBillingCycle(form.value);
            ElMessage.success(t('billing_cycles_page.messages.created'));
        }
        await loadData();
        dialogVisible.value = false;
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'));
    } finally {
        saving.value = false;
    }
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(
            t('billing_cycles_page.delete_confirm', { name: row.name }),
            t('actions.confirm'),
            { type: 'warning' },
        );
        await deleteBillingCycle(row.id);
        ElMessage.success(t('billing_cycles_page.messages.deleted'));
        loadData();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || t('billing_cycles_page.messages.delete_failed'));
    }
}

onMounted(loadData);
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.page-header h2 { margin: 0; }
.text-muted { color: #909399; }
.ml-2 { margin-left: 8px; }
</style>
