<template>
    <div class="billing-cycles-page">
        <div class="page-header">
            <h2>计费周期管理</h2>
            <el-button type="primary" @click="openCreateDialog">
                <el-icon><Plus /></el-icon> 新增周期
            </el-button>
        </div>

        <el-card shadow="never">
            <el-table :data="cycles" v-loading="loading" stripe>
                <el-table-column prop="code" label="编码" width="150" />
                <el-table-column prop="name" label="名称" width="150" />
                <el-table-column label="对应月数" width="120">
                    <template #default="{ row }">
                        <span v-if="row.months">{{ row.months }} 个月</span>
                        <span v-else class="text-muted">—</span>
                    </template>
                </el-table-column>
                <el-table-column prop="sort_order" label="排序" width="80" />
                <el-table-column label="状态" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                            {{ row.is_active ? '启用' : '停用' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button text type="primary" size="small" @click="openEditDialog(row)">编辑</el-button>
                        <el-button text type="danger" size="small" @click="handleDelete(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 创建/编辑对话框 -->
        <el-dialog v-model="dialogVisible" :title="isEditing ? '编辑周期' : '新增周期'" width="480px">
            <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
                <el-form-item label="编码" prop="code">
                    <el-input v-model="form.code" placeholder="唯一标识，如：semi-annual" :disabled="isEditing" />
                </el-form-item>
                <el-form-item label="名称" prop="name">
                    <el-input v-model="form.name" placeholder="显示名称，如：半年付" />
                </el-form-item>
                <el-form-item label="对应月数">
                    <el-input-number v-model="form.months" :min="0" :max="120" style="width:200px" />
                    <span class="text-muted ml-2">0=无固定周期</span>
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="form.sort_order" :min="0" style="width:200px" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-switch v-model="form.is_active" active-text="启用" inactive-text="停用" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import apiClient from '@/api/client';

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
    sort_order: 0,
    is_active: true,
});

const rules = {
    code: [
        { required: true, message: '请输入编码', trigger: 'blur' },
        { pattern: /^[a-z][a-z0-9_-]*$/, message: '以小写字母开头，仅含小写字母/数字/下划线/连字符', trigger: 'blur' },
    ],
    name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
};

async function loadData() {
    loading.value = true;
    try {
        const res = await apiClient.get('/admin/billing-cycles');
        cycles.value = res.data?.data || [];
    } catch { ElMessage.error('加载失败'); }
    finally { loading.value = false; }
}

function openCreateDialog() {
    isEditing.value = false;
    form.value = { code: '', name: '', months: null, sort_order: 0, is_active: true };
    dialogVisible.value = true;
}

function openEditDialog(row) {
    isEditing.value = true;
    form.value = {
        code: row.code,
        name: row.name,
        months: row.months,
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
            await apiClient.put(`/admin/billing-cycles/${form.value._id}`, form.value);
            ElMessage.success('已更新');
        } else {
            await apiClient.post('/admin/billing-cycles', form.value);
            ElMessage.success('已创建');
        }
        dialogVisible.value = false;
        loadData();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败');
    } finally { saving.value = false; }
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(`确定删除「${row.name}」？`, '确认', { type: 'warning' });
        await apiClient.delete(`/admin/billing-cycles/${row.id}`);
        ElMessage.success('已删除');
        loadData();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || '删除失败');
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
