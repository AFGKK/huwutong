<template>
    <div class="announce-banners-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t(`${P}.title`) }}</h2>
                <span class="header-subtitle">{{ t(`${P}.subtitle`) }}</span>
            </div>
            <div class="header-right">
                <el-button type="primary" @click="openCreateDialog">
                    <el-icon><Plus /></el-icon>
                    {{ t(`${P}.btn_create`) }}
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
                <el-table-column prop="title" :label="t(`${P}.cols.title`)" min-width="150" />
                <el-table-column :label="t(`${P}.cols.type`)" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.type" size="small">{{ typeLabel(row.type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.position`)" width="80">
                    <template #default="{ row }">
                        {{ positionLabel(row.position) }}
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.roles`)" width="120">
                    <template #default="{ row }">
                        <el-tag v-if="!row.roles || !row.roles.length" size="small" type="info">{{ t(`${P}.roles.all_users`) }}</el-tag>
                        <el-popover v-else placement="top" :width="200" trigger="hover">
                            <template #reference>
                                <el-tag size="small">{{ t(`${P}.roles.count`, { n: row.roles.length }) }}</el-tag>
                            </template>
                            <div v-for="r in row.roles" :key="r">{{ r }}</div>
                        </el-popover>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.schedule`)" width="160">
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
                        <span v-else class="text-muted">{{ t(`${P}.schedule.permanent`) }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.status`)" width="80">
                    <template #default="{ row }">
                        <el-switch
                            :model-value="row.is_active"
                            @change="(val) => toggleActive(row, val)"
                            :loading="toggling[row.id]"
                        />
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.sort_order`)" width="70" prop="sort_order" />
                <el-table-column :label="t(`${P}.cols.actions`)" width="160" fixed="right">
                    <template #default="{ row }">
                        <el-button text type="primary" @click="openEditDialog(row)">{{ t('actions.edit') }}</el-button>
                        <el-popconfirm
                            :title="t(`${P}.confirm_delete`)"
                            :confirm-button-text="t('actions.delete')"
                            @confirm="handleDelete(row)"
                        >
                            <template #reference>
                                <el-button text type="danger">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 创建/编辑对话框 -->
        <el-dialog
            v-model="dialogVisible"
            :title="isEditing ? t(`${P}.dialog.edit`) : t(`${P}.dialog.create`)"
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
                <el-form-item :label="t(`${P}.form.title`)" prop="title">
                    <el-input v-model="form.title" :placeholder="t(`${P}.form.title_ph`)" maxlength="200" show-word-limit />
                </el-form-item>
                <el-form-item :label="t(`${P}.form.content`)" prop="content">
                    <el-input
                        v-model="form.content"
                        type="textarea"
                        :rows="3"
                        :placeholder="t(`${P}.form.content_ph`)"
                    />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="t(`${P}.form.type`)" prop="type">
                            <el-select v-model="form.type" style="width: 100%">
                                <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t(`${P}.form.position`)" prop="position">
                            <el-select v-model="form.position" style="width: 100%">
                                <el-option v-for="opt in positionOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t(`${P}.form.sort_order`)" prop="sort_order">
                            <el-input-number v-model="form.sort_order" :min="0" :max="999" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t(`${P}.form.can_close`)" prop="can_close">
                    <el-switch v-model="form.can_close" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t(`${P}.form.starts_at`)" prop="starts_at">
                            <el-date-picker
                                v-model="form.starts_at"
                                type="datetime"
                                :placeholder="t(`${P}.form.starts_at_ph`)"
                                style="width: 100%"
                                value-format="YYYY-MM-DD HH:mm:ss"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t(`${P}.form.ends_at`)" prop="ends_at">
                            <el-date-picker
                                v-model="form.ends_at"
                                type="datetime"
                                :placeholder="t(`${P}.form.ends_at_ph`)"
                                style="width: 100%"
                                value-format="YYYY-MM-DD HH:mm:ss"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t(`${P}.form.link_url`)" prop="link_url">
                    <el-input v-model="form.link_url" :placeholder="t(`${P}.form.link_url_ph`)" />
                </el-form-item>
                <el-form-item :label="t(`${P}.form.link_text`)" prop="link_text">
                    <el-input v-model="form.link_text" :placeholder="t(`${P}.form.link_text_ph`)" maxlength="100" />
                </el-form-item>
                <el-form-item :label="t(`${P}.form.roles`)" prop="roles">
                    <el-select
                        v-model="form.roles"
                        multiple
                        :placeholder="t(`${P}.form.roles_ph`)"
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
                    <div class="form-tip">{{ t(`${P}.form.roles_tip`) }}</div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Plus, Timer } from '@element-plus/icons-vue';
import { getAnnounceBanners, createAnnounceBanner, updateAnnounceBanner, deleteAnnounceBanner } from '@/api/announce-banners';

const P = 'announce_banners_page';
const { t } = useI18n();

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

const rules = computed(() => ({
    title: [{ required: true, message: t(`${P}.validation.title_required`), trigger: 'blur' }],
}));

const typeOptions = computed(() => [
    { label: t(`${P}.type.info`), value: 'info' },
    { label: t(`${P}.type.success`), value: 'success' },
    { label: t(`${P}.type.warning`), value: 'warning' },
    { label: t(`${P}.type.danger`), value: 'danger' },
]);

const positionOptions = computed(() => [
    { label: t(`${P}.position.top`), value: 'top' },
    { label: t(`${P}.position.bottom`), value: 'bottom' },
]);

// 角色选项（可从后端获取，目前写死常见角色）
const roleOptions = computed(() => [
    { label: t(`${P}.role.super_admin`), value: 'super-admin' },
    { label: t(`${P}.role.admin`), value: 'admin' },
    { label: t(`${P}.role.customer`), value: 'customer' },
    { label: t(`${P}.role.developer`), value: 'developer' },
]);

const typeMap = computed(() => ({
    info: t(`${P}.type.info`),
    success: t(`${P}.type.success`),
    warning: t(`${P}.type.warning`),
    danger: t(`${P}.type.danger`),
}));

const positionMap = computed(() => ({
    top: t(`${P}.position.top`),
    bottom: t(`${P}.position.bottom`),
}));

function typeLabel(type) {
    return typeMap.value[type] || type;
}

function positionLabel(position) {
    return positionMap.value[position] || position;
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
        ElMessage.error(t(`${P}.messages.fetch_failed`));
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
            ElMessage.success(t(`${P}.messages.updated`));
        } else {
            await createAnnounceBanner(data);
            ElMessage.success(t(`${P}.messages.created`));
        }
        dialogVisible.value = false;
        await fetchBanners();
    } catch (e) {
        const msg = e?.response?.data?.message || e?.response?.data?.error?.message || (isEditing.value ? t(`${P}.messages.update_failed`) : t(`${P}.messages.create_failed`));
        ElMessage.error(msg);
    } finally {
        saving.value = false;
    }
}

async function handleDelete(row) {
    try {
        await deleteAnnounceBanner(row.id);
        ElMessage.success(t(`${P}.messages.deleted`));
        await fetchBanners();
    } catch {
        ElMessage.error(t('messages.failed'));
    }
}

async function toggleActive(row, val) {
    toggling.value[row.id] = true;
    try {
        await updateAnnounceBanner(row.id, { is_active: val });
        row.is_active = val;
        ElMessage.success(val ? t('actions.enable') : t('actions.disable'));
    } catch {
        ElMessage.error(t('messages.failed'));
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
