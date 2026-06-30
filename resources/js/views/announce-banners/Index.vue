<template>
    <div class="announce-banners-page">
        <div class="page-header">
            <div class="header-left">
                <h2>系统公告横幅</h2>
                <span class="header-subtitle">管理后台顶部/底部公告横幅，支持定时上线、角色定向展示</span>
            </div>
            <div class="header-right">
                <el-button type="primary" @click="openCreateDialog">
                    <el-icon><Plus /></el-icon>
                    新建公告
                </el-button>
            </div>
        </div>

        <el-card shadow="never" class="mt-4">
            <el-table
                :data="banners"
                v-loading="loading"
                stripe
                style="width: 100%"
            >
                <el-table-column prop="title" label="标题" min-width="150" />
                <el-table-column label="类型" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.type" size="small">{{ typeLabel(row.type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="位置" width="80">
                    <template #default="{ row }">
                        {{ row.position === 'top' ? '顶部' : '底部' }}
                    </template>
                </el-table-column>
                <el-table-column label="角色限制" width="120">
                    <template #default="{ row }">
                        <el-tag v-if="!row.roles || !row.roles.length" size="small" type="info">全部用户</el-tag>
                        <el-popover v-else placement="top" :width="200" trigger="hover">
                            <template #reference>
                                <el-tag size="small">{{ row.roles.length }} 个角色</el-tag>
                            </template>
                            <div v-for="r in row.roles" :key="r">{{ r }}</div>
                        </el-popover>
                    </template>
                </el-table-column>
                <el-table-column label="定时" width="160">
                    <template #default="{ row }">
                        <div v-if="row.starts_at || row.ends_at" class="time-info">
                            <div v-if="row.starts_at">
                                <el-icon><Timer /></el-icon>
                                {{ formatTime(row.starts_at) }}
                            </div>
                            <div v-if="row.ends_at">
                                → {{ formatTime(row.ends_at) }}
                            </div>
                        </div>
                        <span v-else class="text-muted">长期展示</span>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="80">
                    <template #default="{ row }">
                        <el-switch
                            :model-value="row.is_active"
                            @change="(val) => toggleActive(row, val)"
                            :loading="toggling[row.id]"
                        />
                    </template>
                </el-table-column>
                <el-table-column label="排序" width="70" prop="sort_order" />
                <el-table-column label="操作" width="160" fixed="right">
                    <template #default="{ row }">
                        <el-button text type="primary" @click="openEditDialog(row)">编辑</el-button>
                        <el-popconfirm
                            title="确定删除此公告？"
                            confirm-button-text="删除"
                            @confirm="handleDelete(row)"
                        >
                            <template #reference>
                                <el-button text type="danger">删除</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 创建/编辑对话框 -->
        <el-dialog
            v-model="dialogVisible"
            :title="isEditing ? '编辑公告' : '新建公告'"
            width="700px"
            :close-on-click-modal="false"
        >
            <el-form
                ref="formRef"
                :model="form"
                :rules="rules"
                label-width="100px"
                class="mt-2"
            >
                <el-form-item label="标题" prop="title">
                    <el-input v-model="form.title" placeholder="请输入公告标题" maxlength="200" show-word-limit />
                </el-form-item>
                <el-form-item label="内容" prop="content">
                    <el-input
                        v-model="form.content"
                        type="textarea"
                        :rows="3"
                        placeholder="公告内容，支持 HTML"
                    />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="类型" prop="type">
                            <el-select v-model="form.type" style="width: 100%">
                                <el-option label="信息" value="info" />
                                <el-option label="成功" value="success" />
                                <el-option label="警告" value="warning" />
                                <el-option label="危险" value="danger" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="位置" prop="position">
                            <el-select v-model="form.position" style="width: 100%">
                                <el-option label="顶部" value="top" />
                                <el-option label="底部" value="bottom" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="排序" prop="sort_order">
                            <el-input-number v-model="form.sort_order" :min="0" :max="999" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="可关闭" prop="can_close">
                    <el-switch v-model="form.can_close" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="开始时间" prop="starts_at">
                            <el-date-picker
                                v-model="form.starts_at"
                                type="datetime"
                                placeholder="选择开始时间（可选）"
                                style="width: 100%"
                                value-format="YYYY-MM-DD HH:mm:ss"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="结束时间" prop="ends_at">
                            <el-date-picker
                                v-model="form.ends_at"
                                type="datetime"
                                placeholder="选择结束时间（可选）"
                                style="width: 100%"
                                value-format="YYYY-MM-DD HH:mm:ss"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="跳转链接" prop="link_url">
                    <el-input v-model="form.link_url" placeholder="https://..." />
                </el-form-item>
                <el-form-item label="链接文字" prop="link_text">
                    <el-input v-model="form.link_text" placeholder="查看详情" maxlength="100" />
                </el-form-item>
                <el-form-item label="可见角色" prop="roles">
                    <el-select
                        v-model="form.roles"
                        multiple
                        placeholder="不选则全部用户可见"
                        style="width: 100%"
                        clearable
                    >
                        <el-option
                            v-for="r in roleOptions"
                            :key="r.value"
                            :label="r.label"
                            :value="r.value"
                        />
                    </el-select>
                    <div class="form-tip">留空则对所有用户可见</div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus, Timer } from '@element-plus/icons-vue';
import { getAnnounceBanners, createAnnounceBanner, updateAnnounceBanner, deleteAnnounceBanner } from '@/api/announce-banners';

const banners = ref([]);
const loading = ref(false);
const dialogVisible = ref(false);
const isEditing = ref(false);
const saving = ref(false);
const toggling = ref({});
const formRef = ref(null);

const defaultForm = {
    title: '',
    content: '',
    type: 'info',
    position: 'top',
    can_close: true,
    link_url: '',
    link_text: '',
    roles: [],
    starts_at: null,
    ends_at: null,
    sort_order: 0,
};

const form = ref({ ...defaultForm });

const rules = {
    title: [{ required: true, message: '请输入公告标题', trigger: 'blur' }],
};

// 角色选项（可从后端获取，目前写死常见角色）
const roleOptions = [
    { label: '超级管理员', value: 'super-admin' },
    { label: '管理员', value: 'admin' },
    { label: '客户', value: 'customer' },
    { label: '开发者', value: 'developer' },
];

function typeLabel(type) {
    const map = { info: '信息', success: '成功', warning: '警告', danger: '危险' };
    return map[type] || type;
}

function formatTime(val) {
    if (!val) return '';
    return val.slice(0, 16);
}

async function fetchBanners() {
    loading.value = true;
    try {
        const res = await getAnnounceBanners();
        banners.value = res.data?.data || res.data || [];
    } catch (e) {
        ElMessage.error('获取公告列表失败');
    } finally {
        loading.value = false;
    }
}

function openCreateDialog() {
    isEditing.value = false;
    form.value = { ...defaultForm };
    dialogVisible.value = true;
}

function openEditDialog(row) {
    isEditing.value = true;
    form.value = {
        title: row.title,
        content: row.content || '',
        type: row.type,
        position: row.position,
        can_close: row.can_close,
        link_url: row.link_url || '',
        link_text: row.link_text || '',
        roles: row.roles || [],
        starts_at: row.starts_at || null,
        ends_at: row.ends_at || null,
        sort_order: row.sort_order || 0,
    };
    form.value._id = row.id;
    dialogVisible.value = true;
}

async function handleSave() {
    const valid = await formRef.value.validate().catch(() => false);
    if (!valid) return;

    saving.value = true;
    try {
        const data = { ...form.value };
        delete data._id;
        // 清理空字符串可选字段，避免后端验证失败
        if (!data.link_url) data.link_url = null;
        if (!data.link_text) data.link_text = null;
        if (!data.content) data.content = null;
        if (!data.starts_at) data.starts_at = null;
        if (!data.ends_at) data.ends_at = null;

        if (isEditing.value) {
            await updateAnnounceBanner(form.value._id, data);
            ElMessage.success('公告已更新');
        } else {
            await createAnnounceBanner(data);
            ElMessage.success('公告已创建');
        }
        dialogVisible.value = false;
        await fetchBanners();
    } catch (e) {
        const msg = e?.response?.data?.message || e?.response?.data?.error?.message || (isEditing.value ? '更新失败' : '创建失败');
        ElMessage.error(msg);
    } finally {
        saving.value = false;
    }
}

async function handleDelete(row) {
    try {
        await deleteAnnounceBanner(row.id);
        ElMessage.success('公告已删除');
        await fetchBanners();
    } catch {
        ElMessage.error('删除失败');
    }
}

async function toggleActive(row, val) {
    toggling.value[row.id] = true;
    try {
        await updateAnnounceBanner(row.id, { is_active: val });
        row.is_active = val;
        ElMessage.success(val ? '已启用' : '已禁用');
    } catch {
        ElMessage.error('操作失败');
    } finally {
        toggling.value[row.id] = false;
    }
}

onMounted(fetchBanners);
</script>

<style scoped>
.announce-banners-page {
    padding: 0;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-subtitle {
    font-size: 13px;
    color: #909399;
    margin-left: 12px;
}

.mt-4 {
    margin-top: 16px;
}

.mt-2 {
    margin-top: 8px;
}

.time-info {
    font-size: 12px;
    color: #606266;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.time-info .el-icon {
    vertical-align: middle;
    margin-right: 2px;
}

.text-muted {
    color: #c0c4cc;
    font-size: 13px;
}

.form-tip {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
}
</style>
