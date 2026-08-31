<template>
    <div class="cookie-consent-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('cookie_consent_page.title') }}</h2>
                <span class="header-subtitle">{{ t('cookie_consent_page.subtitle') }}</span>
            </div>
        </div>

        <el-tabs v-model="activeTab" class="mt-4">
            <!-- 配置 Tab -->
            <el-tab-pane :label="t('cookie_consent_page.tabs.config')" name="config">
                <el-card shadow="never">
                    <el-form
                        ref="formRef"
                        :model="form"
                        :rules="rules"
                        label-width="120px"
                        class="config-form"
                    >
                        <el-form-item :label="t('cookie_consent_page.form.enable_banner')" prop="is_active">
                            <el-switch v-model="form.is_active" />
                        </el-form-item>
                        <el-form-item :label="t('cookie_consent_page.form.floating_button')" prop="show_floating_button">
                            <el-switch v-model="form.show_floating_button" />
                            <span style="font-size:12px;color:#999;margin-left:8px">{{ t('cookie_consent_page.form.floating_button_hint') }}</span>
                        </el-form-item>

                        <el-row :gutter="24">
                            <el-col :span="8">
                                <el-form-item :label="t('cookie_consent_page.form.position')" prop="position">
                                    <el-select v-model="form.position" style="width: 100%">
                                        <el-option
                                            v-for="opt in positionOptions"
                                            :key="opt.value"
                                            :label="opt.label"
                                            :value="opt.value"
                                        />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item :label="t('cookie_consent_page.form.layout')" prop="layout">
                                    <el-select v-model="form.layout" style="width: 100%">
                                        <el-option
                                            v-for="opt in layoutOptions"
                                            :key="opt.value"
                                            :label="opt.label"
                                            :value="opt.value"
                                        />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item :label="t('cookie_consent_page.form.theme')" prop="theme">
                                    <el-select v-model="form.theme" style="width: 100%">
                                        <el-option
                                            v-for="opt in themeOptions"
                                            :key="opt.value"
                                            :label="opt.label"
                                            :value="opt.value"
                                        />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-form-item :label="t('cookie_consent_page.form.title')" prop="title">
                            <el-input v-model="form.title" maxlength="200" />
                        </el-form-item>

                        <el-form-item :label="t('cookie_consent_page.form.description')" prop="description">
                            <el-input v-model="form.description" type="textarea" :rows="2" />
                        </el-form-item>

                        <el-row :gutter="24">
                            <el-col :span="8">
                                <el-form-item :label="t('cookie_consent_page.form.accept_button')" prop="accept_all_text">
                                    <el-input v-model="form.accept_all_text" maxlength="100" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item :label="t('cookie_consent_page.form.reject_button')" prop="reject_all_text">
                                    <el-input v-model="form.reject_all_text" maxlength="100" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item :label="t('cookie_consent_page.form.customize_button')" prop="customize_text">
                                    <el-input v-model="form.customize_text" maxlength="100" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-row :gutter="24">
                            <el-col :span="12">
                                <el-form-item :label="t('cookie_consent_page.form.privacy_policy_url')" prop="privacy_policy_url">
                                    <el-input v-model="form.privacy_policy_url" :placeholder="t('cookie_consent_page.form.privacy_policy_url_ph')" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="12">
                                <el-form-item :label="t('cookie_consent_page.form.privacy_policy_text')" prop="privacy_policy_text">
                                    <el-input v-model="form.privacy_policy_text" maxlength="100" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-form-item :label="t('cookie_consent_page.form.consent_lifetime')" prop="consent_lifetime_days">
                            <el-input-number
                                v-model="form.consent_lifetime_days"
                                :min="1"
                                :max="1825"
                                style="width: 200px"
                            />
                            <span class="form-hint">{{ t('cookie_consent_page.form.consent_lifetime_hint') }}</span>
                        </el-form-item>

                        <el-divider>{{ t('cookie_consent_page.categories.divider') }}</el-divider>

                        <div
                            v-for="(cat, index) in form.categories"
                            :key="cat.id"
                            class="cookie-category-card"
                        >
                            <div class="category-header">
                                <el-tag v-if="cat.required" type="danger" size="small">{{ t('cookie_consent_page.categories.required') }}</el-tag>
                                <el-tag v-else type="info" size="small">{{ t('cookie_consent_page.categories.optional') }}</el-tag>
                                <strong>{{ cat.name }}</strong>
                            </div>
                            <div class="category-fields">
                                <el-input
                                    v-model="cat.name"
                                    :placeholder="t('cookie_consent_page.categories.name_ph')"
                                    size="small"
                                    style="width: 200px"
                                    @input="markDirty"
                                />
                                <el-input
                                    v-model="cat.description"
                                    :placeholder="t('cookie_consent_page.categories.description_ph')"
                                    size="small"
                                    style="flex: 1"
                                    @input="markDirty"
                                />
                                <el-checkbox
                                    :model-value="cat.required"
                                    @change="(v) => { cat.required = v; markDirty(); }"
                                >
                                    {{ t('cookie_consent_page.categories.required') }}
                                </el-checkbox>
                                <el-checkbox
                                    :model-value="cat.default"
                                    :disabled="cat.required"
                                    @change="(v) => { cat.default = v; markDirty(); }"
                                >
                                    {{ t('cookie_consent_page.categories.default_checked') }}
                                </el-checkbox>
                            </div>
                            <el-button
                                v-if="!cat.required"
                                text
                                type="danger"
                                size="small"
                                @click="removeCategory(index)"
                            >
                                {{ t('actions.delete') }}
                            </el-button>
                        </div>

                        <el-button
                            text
                            type="primary"
                            size="small"
                            class="mt-2"
                            @click="addCategory"
                        >
                            + {{ t('cookie_consent_page.categories.add') }}
                        </el-button>

                        <el-divider />

                        <el-form-item>
                            <el-button type="primary" @click="handleSave" :loading="saving">
                                {{ t('cookie_consent_page.save_config') }}
                            </el-button>
                            <el-button @click="resetForm">{{ t('actions.reset') }}</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <!-- 统计 Tab -->
            <el-tab-pane :label="t('cookie_consent_page.tabs.stats')" name="stats">
                <el-card shadow="never">
                    <div v-if="stats" class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value">{{ stats.total }}</div>
                            <div class="stat-label">{{ t('cookie_consent_page.stats.total') }}</div>
                        </div>
                        <div class="stat-card stat-card--success">
                            <div class="stat-value">{{ stats.accepted }}</div>
                            <div class="stat-label">{{ t('cookie_consent_page.stats.accepted') }}</div>
                        </div>
                        <div class="stat-card stat-card--danger">
                            <div class="stat-value">{{ stats.rejected }}</div>
                            <div class="stat-label">{{ t('cookie_consent_page.stats.rejected') }}</div>
                        </div>
                        <div class="stat-card stat-card--warning">
                            <div class="stat-value">{{ stats.customized }}</div>
                            <div class="stat-label">{{ t('cookie_consent_page.stats.customized') }}</div>
                        </div>
                        <div class="stat-card stat-card--info">
                            <div class="stat-value">{{ stats.today }}</div>
                            <div class="stat-label">{{ t('cookie_consent_page.stats.today') }}</div>
                        </div>
                    </div>
                    <div v-if="stats?.category_breakdown" class="category-breakdown mt-4">
                        <h4>{{ t('cookie_consent_page.stats.category_breakdown') }}</h4>
                        <div class="breakdown-list">
                            <div
                                v-for="(count, cat) in stats.category_breakdown"
                                :key="cat"
                                class="breakdown-item"
                            >
                                <span class="breakdown-name">{{ cat }}</span>
                                <el-progress
                                    :percentage="Math.round(count / stats.total * 100)"
                                    :text-inside="true"
                                    :stroke-width="20"
                                />
                                <span class="breakdown-count">{{ count }}</span>
                            </div>
                        </div>
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- 日志 Tab -->
            <el-tab-pane :label="t('cookie_consent_page.tabs.logs')" name="logs">
                <el-card shadow="never">
                    <el-table :data="logs" v-loading="logsLoading" stripe style="width: 100%">
                        <el-table-column prop="created_at" :label="t('cookie_consent_page.logs.time')" width="160">
                            <template #default="{ row }">
                                {{ formatTime(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('cookie_consent_page.logs.user')" width="150">
                            <template #default="{ row }">
                                {{ row.user?.name || row.user?.email || t('cookie_consent_page.logs.anonymous') }}
                            </template>
                        </el-table-column>
                        <el-table-column prop="ip" :label="t('cookie_consent_page.logs.ip')" width="140" />
                        <el-table-column prop="action" :label="t('cookie_consent_page.logs.action')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="actionTag(row.action)" size="small">
                                    {{ actionLabel(row.action) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="selected_categories" :label="t('cookie_consent_page.logs.selected_categories')">
                            <template #default="{ row }">
                                <el-tag
                                    v-for="cat in (row.selected_categories || [])"
                                    :key="cat"
                                    size="small"
                                    class="mr-1"
                                >
                                    {{ cat }}
                                </el-tag>
                                <span v-if="!row.selected_categories?.length" class="text-muted">-</span>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-wrap" v-if="pagination">
                        <el-pagination
                            v-model:current-page="pagination.current_page"
                            :page-size="pagination.per_page"
                            :total="pagination.total"
                            layout="prev, pager, next, total"
                            @current-change="fetchLogs"
                        />
                    </div>
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import {
    getAdminConfig, updateAdminConfig,
    getCookieStats, getCookieLogs,
} from '@/api/cookie-consent';

const { t } = useI18n();

const activeTab = ref('config');
const formRef = ref(null);
const saving = ref(false);
const stats = ref(null);
const logs = ref([]);
const logsLoading = ref(false);
const pagination = ref(null);

const positionOptions = computed(() => [
    { label: t('cookie_consent_page.position.bottom'), value: 'bottom' },
    { label: t('cookie_consent_page.position.top'), value: 'top' },
    { label: t('cookie_consent_page.position.center'), value: 'center' },
]);

const layoutOptions = computed(() => [
    { label: t('cookie_consent_page.layout.bar'), value: 'bar' },
    { label: t('cookie_consent_page.layout.modal'), value: 'modal' },
    { label: t('cookie_consent_page.layout.floating'), value: 'floating' },
]);

const themeOptions = computed(() => [
    { label: t('cookie_consent_page.theme.light'), value: 'light' },
    { label: t('cookie_consent_page.theme.dark'), value: 'dark' },
    { label: t('cookie_consent_page.theme.auto'), value: 'auto' },
]);

function createDefaultCategories() {
    return [
        { id: 'necessary', name: t('cookie_consent_page.defaults.necessary_name'), description: t('cookie_consent_page.defaults.necessary_desc'), required: true, default: true },
        { id: 'functional', name: t('cookie_consent_page.defaults.functional_name'), description: t('cookie_consent_page.defaults.functional_desc'), required: false, default: true },
        { id: 'analytics', name: t('cookie_consent_page.defaults.analytics_name'), description: t('cookie_consent_page.defaults.analytics_desc'), required: false, default: false },
        { id: 'marketing', name: t('cookie_consent_page.defaults.marketing_name'), description: t('cookie_consent_page.defaults.marketing_desc'), required: false, default: false },
    ];
}

const form = reactive({
    is_active: true,
    show_floating_button: true,
    position: 'bottom',
    layout: 'bar',
    theme: 'light',
    title: '',
    description: '',
    accept_all_text: '',
    reject_all_text: '',
    customize_text: '',
    privacy_policy_url: '',
    privacy_policy_text: '',
    categories: [],
    consent_lifetime_days: 365,
});

const rules = computed(() => ({
    title: [{ required: true, message: t('cookie_consent_page.validation.title_required'), trigger: 'blur' }],
}));

function applyFormDefaults() {
    form.title = t('cookie_consent_page.defaults.title');
    form.description = t('cookie_consent_page.defaults.description');
    form.accept_all_text = t('cookie_consent_page.defaults.accept_all');
    form.reject_all_text = t('cookie_consent_page.defaults.reject_all');
    form.customize_text = t('cookie_consent_page.defaults.customize');
    form.privacy_policy_text = t('footer.privacy_policy');
    form.categories = JSON.parse(JSON.stringify(createDefaultCategories()));
}

function markDirty() {
    // 标记表单为已修改
}

function addCategory() {
    const id = `custom_${Date.now()}`;
    form.categories.push({
        id,
        name: t('cookie_consent_page.defaults.new_category'),
        description: '',
        required: false,
        default: false,
    });
}

function removeCategory(index) {
    form.categories.splice(index, 1);
}

async function fetchConfig() {
    try {
        const res = await getAdminConfig();
        if (res.data) {
            Object.assign(form, {
                is_active: res.data.is_active ?? true,
                show_floating_button: res.data.show_floating_button ?? true,
                position: res.data.position || 'bottom',
                layout: res.data.layout || 'bar',
                theme: res.data.theme || 'light',
                title: res.data.title || t('cookie_consent_page.defaults.title'),
                description: res.data.description || t('cookie_consent_page.defaults.description'),
                accept_all_text: res.data.accept_all_text || t('cookie_consent_page.defaults.accept_all'),
                reject_all_text: res.data.reject_all_text || t('cookie_consent_page.defaults.reject_all'),
                customize_text: res.data.customize_text || t('cookie_consent_page.defaults.customize'),
                privacy_policy_url: res.data.privacy_policy_url || '',
                privacy_policy_text: res.data.privacy_policy_text || t('footer.privacy_policy'),
                categories: res.data.categories || JSON.parse(JSON.stringify(createDefaultCategories())),
                consent_lifetime_days: res.data.consent_lifetime_days || 365,
            });
        } else {
            applyFormDefaults();
        }
    } catch {
        applyFormDefaults();
    }
}

async function handleSave() {
    const valid = await formRef.value.validate().catch(() => false);
    if (!valid) return;

    saving.value = true;
    try {
        await updateAdminConfig({ ...form });
        ElMessage.success(t('cookie_consent_page.messages.saved'));
    } catch {
        ElMessage.error(t('messages.failed'));
    } finally {
        saving.value = false;
    }
}

function resetForm() {
    fetchConfig();
}

async function fetchStats() {
    try {
        const res = await getCookieStats();
        stats.value = res.data;
    } catch {
        // ignore
    }
}

async function fetchLogs(page = 1) {
    logsLoading.value = true;
    try {
        const res = await getCookieLogs({ page, per_page: 20 });
        logs.value = res.data?.data || res.data || [];
        pagination.value = res.meta || null;
    } catch {
        // ignore
    } finally {
        logsLoading.value = false;
    }
}

function formatTime(val) {
    if (!val) return '';
    return val.slice(0, 16).replace('T', ' ');
}

function actionTag(action) {
    const map = { accepted: 'success', rejected: 'danger', customized: 'warning' };
    return map[action] || 'info';
}

function actionLabel(action) {
    const key = `cookie_consent_page.actions_map.${action}`;
    const label = t(key);
    return label !== key ? label : action;
}

onMounted(() => {
    fetchConfig();
    fetchStats();
    fetchLogs();
});
</script>

<style scoped>
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

.config-form {
    max-width: 900px;
}

.form-hint {
    font-size: 12px;
    color: #909399;
    margin-left: 8px;
}

.cookie-category-card {
    background: #fafafa;
    border: 1px solid #ebeef5;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 8px;
}

.cookie-category-card:last-child {
    margin-bottom: 0;
}

.category-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.category-fields {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 16px;
}

.stat-card {
    background: #f5f7fa;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #303133;
}

.stat-label {
    font-size: 13px;
    color: #909399;
    margin-top: 4px;
}

.stat-card--success .stat-value { color: #67c23a; }
.stat-card--danger .stat-value { color: #f56c6c; }
.stat-card--warning .stat-value { color: #e6a23c; }
.stat-card--info .stat-value { color: #0f172a; }

.breakdown-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-width: 500px;
}

.breakdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
}

.breakdown-name {
    width: 100px;
    font-size: 13px;
    flex-shrink: 0;
}

.breakdown-count {
    width: 40px;
    text-align: right;
    font-size: 13px;
    color: #606266;
}

.pagination-wrap {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
}

.mr-1 {
    margin-right: 4px;
}

.text-muted {
    color: #c0c4cc;
    font-size: 13px;
}
</style>
