<template>
    <div class="cookie-consent-page">
        <div class="page-header">
            <div class="header-left">
                <h2>Cookie 同意管理</h2>
                <span class="header-subtitle">配置 GDPR Cookie 同意横幅、查看用户同意记录</span>
            </div>
        </div>

        <el-tabs v-model="activeTab" class="mt-4">
            <!-- 配置 Tab -->
            <el-tab-pane label="横幅配置" name="config">
                <el-card shadow="never">
                    <el-form
                        ref="formRef"
                        :model="form"
                        :rules="rules"
                        label-width="120px"
                        class="config-form"
                    >
                        <el-form-item label="启用横幅" prop="is_active">
                            <el-switch v-model="form.is_active" />
                        </el-form-item>
                        <el-form-item label="浮动 🍪 按钮" prop="show_floating_button">
                            <el-switch v-model="form.show_floating_button" />
                            <span style="font-size:12px;color:#999;margin-left:8px">关闭横幅后显示悬浮按钮，可随时重新打开设置</span>
                        </el-form-item>

                        <el-row :gutter="24">
                            <el-col :span="8">
                                <el-form-item label="位置" prop="position">
                                    <el-select v-model="form.position" style="width: 100%">
                                        <el-option label="底部" value="bottom" />
                                        <el-option label="顶部" value="top" />
                                        <el-option label="居中弹窗" value="center" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item label="布局" prop="layout">
                                    <el-select v-model="form.layout" style="width: 100%">
                                        <el-option label="横条" value="bar" />
                                        <el-option label="弹窗" value="modal" />
                                        <el-option label="浮动卡片" value="floating" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item label="主题" prop="theme">
                                    <el-select v-model="form.theme" style="width: 100%">
                                        <el-option label="浅色" value="light" />
                                        <el-option label="深色" value="dark" />
                                        <el-option label="跟随系统" value="auto" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-form-item label="标题" prop="title">
                            <el-input v-model="form.title" maxlength="200" />
                        </el-form-item>

                        <el-form-item label="说明文字" prop="description">
                            <el-input v-model="form.description" type="textarea" :rows="2" />
                        </el-form-item>

                        <el-row :gutter="24">
                            <el-col :span="8">
                                <el-form-item label="接受按钮" prop="accept_all_text">
                                    <el-input v-model="form.accept_all_text" maxlength="100" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item label="拒绝按钮" prop="reject_all_text">
                                    <el-input v-model="form.reject_all_text" maxlength="100" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item label="自定义按钮" prop="customize_text">
                                    <el-input v-model="form.customize_text" maxlength="100" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-row :gutter="24">
                            <el-col :span="12">
                                <el-form-item label="隐私政策链接" prop="privacy_policy_url">
                                    <el-input v-model="form.privacy_policy_url" placeholder="https://..." />
                                </el-form-item>
                            </el-col>
                            <el-col :span="12">
                                <el-form-item label="链接文字" prop="privacy_policy_text">
                                    <el-input v-model="form.privacy_policy_text" maxlength="100" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-form-item label="同意有效期" prop="consent_lifetime_days">
                            <el-input-number
                                v-model="form.consent_lifetime_days"
                                :min="1"
                                :max="1825"
                                style="width: 200px"
                            />
                            <span class="form-hint">天（1-1825天）</span>
                        </el-form-item>

                        <el-divider>Cookie 分类配置</el-divider>

                        <div
                            v-for="(cat, index) in form.categories"
                            :key="cat.id"
                            class="cookie-category-card"
                        >
                            <div class="category-header">
                                <el-tag v-if="cat.required" type="danger" size="small">必需</el-tag>
                                <el-tag v-else type="info" size="small">可选</el-tag>
                                <strong>{{ cat.name }}</strong>
                            </div>
                            <div class="category-fields">
                                <el-input
                                    v-model="cat.name"
                                    placeholder="分类名称"
                                    size="small"
                                    style="width: 200px"
                                    @input="markDirty"
                                />
                                <el-input
                                    v-model="cat.description"
                                    placeholder="分类描述"
                                    size="small"
                                    style="flex: 1"
                                    @input="markDirty"
                                />
                                <el-checkbox
                                    :model-value="cat.required"
                                    @change="(v) => { cat.required = v; markDirty(); }"
                                >
                                    必需
                                </el-checkbox>
                                <el-checkbox
                                    :model-value="cat.default"
                                    :disabled="cat.required"
                                    @change="(v) => { cat.default = v; markDirty(); }"
                                >
                                    默认选中
                                </el-checkbox>
                            </div>
                            <el-button
                                v-if="!cat.required"
                                text
                                type="danger"
                                size="small"
                                @click="removeCategory(index)"
                            >
                                删除
                            </el-button>
                        </div>

                        <el-button
                            text
                            type="primary"
                            size="small"
                            class="mt-2"
                            @click="addCategory"
                        >
                            + 添加分类
                        </el-button>

                        <el-divider />

                        <el-form-item>
                            <el-button type="primary" @click="handleSave" :loading="saving">
                                保存配置
                            </el-button>
                            <el-button @click="resetForm">重置</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <!-- 统计 Tab -->
            <el-tab-pane label="统计概览" name="stats">
                <el-card shadow="never">
                    <div v-if="stats" class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value">{{ stats.total }}</div>
                            <div class="stat-label">总记录数</div>
                        </div>
                        <div class="stat-card stat-card--success">
                            <div class="stat-value">{{ stats.accepted }}</div>
                            <div class="stat-label">接受全部</div>
                        </div>
                        <div class="stat-card stat-card--danger">
                            <div class="stat-value">{{ stats.rejected }}</div>
                            <div class="stat-label">拒绝全部</div>
                        </div>
                        <div class="stat-card stat-card--warning">
                            <div class="stat-value">{{ stats.customized }}</div>
                            <div class="stat-label">自定义</div>
                        </div>
                        <div class="stat-card stat-card--info">
                            <div class="stat-value">{{ stats.today }}</div>
                            <div class="stat-label">今日新增</div>
                        </div>
                    </div>
                    <div v-if="stats?.category_breakdown" class="category-breakdown mt-4">
                        <h4>分类同意分布</h4>
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
            <el-tab-pane label="同意日志" name="logs">
                <el-card shadow="never">
                    <el-table :data="logs" v-loading="logsLoading" stripe style="width: 100%">
                        <el-table-column prop="created_at" label="时间" width="160">
                            <template #default="{ row }">
                                {{ formatTime(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="用户" width="150">
                            <template #default="{ row }">
                                {{ row.user?.name || row.user?.email || '匿名' }}
                            </template>
                        </el-table-column>
                        <el-table-column prop="ip" label="IP" width="140" />
                        <el-table-column prop="action" label="操作" width="100">
                            <template #default="{ row }">
                                <el-tag :type="actionTag(row.action)" size="small">
                                    {{ actionLabel(row.action) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="selected_categories" label="同意的分类">
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
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import {
    getAdminConfig, updateAdminConfig,
    getCookieStats, getCookieLogs,
} from '@/api/cookie-consent';

const activeTab = ref('config');
const formRef = ref(null);
const saving = ref(false);
const stats = ref(null);
const logs = ref([]);
const logsLoading = ref(false);
const pagination = ref(null);

const defaultCategories = [
    { id: 'necessary', name: '必要 Cookies', description: '网站运行必需的 Cookie，无法关闭', required: true, default: true },
    { id: 'functional', name: '功能 Cookies', description: '记住您的偏好设置，提升使用体验', required: false, default: true },
    { id: 'analytics', name: '分析 Cookies', description: '收集匿名使用数据，帮助我们改进产品', required: false, default: false },
    { id: 'marketing', name: '营销 Cookies', description: '用于个性化广告和营销内容推送', required: false, default: false },
];

const form = reactive({
    is_active: true,
    show_floating_button: true,
    position: 'bottom',
    layout: 'bar',
    theme: 'light',
    title: 'Cookie 设置',
    description: '我们使用 Cookie 来提升您的使用体验。',
    accept_all_text: '接受全部',
    reject_all_text: '拒绝全部',
    customize_text: '自定义设置',
    privacy_policy_url: '',
    privacy_policy_text: '隐私政策',
    categories: JSON.parse(JSON.stringify(defaultCategories)),
    consent_lifetime_days: 365,
});

const rules = {
    title: [{ required: true, message: '请输入标题', trigger: 'blur' }],
};

function markDirty() {
    // 标记表单为已修改
}

function addCategory() {
    const id = `custom_${Date.now()}`;
    form.categories.push({
        id,
        name: '新分类',
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
                title: res.data.title || 'Cookie 设置',
                description: res.data.description || '',
                accept_all_text: res.data.accept_all_text || '接受全部',
                reject_all_text: res.data.reject_all_text || '拒绝全部',
                customize_text: res.data.customize_text || '自定义设置',
                privacy_policy_url: res.data.privacy_policy_url || '',
                privacy_policy_text: res.data.privacy_policy_text || '隐私政策',
                categories: res.data.categories || JSON.parse(JSON.stringify(defaultCategories)),
                consent_lifetime_days: res.data.consent_lifetime_days || 365,
            });
        }
    } catch {
        // 使用默认值
    }
}

async function handleSave() {
    const valid = await formRef.value.validate().catch(() => false);
    if (!valid) return;

    saving.value = true;
    try {
        await updateAdminConfig({ ...form });
        ElMessage.success('Cookie 配置已保存');
    } catch {
        ElMessage.error('保存失败');
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
    const map = { accepted: '接受', rejected: '拒绝', customized: '自定义' };
    return map[action] || action;
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
.stat-card--info .stat-value { color: #409eff; }

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
