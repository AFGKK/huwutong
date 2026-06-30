<template>
    <div class="quota-page">
        <div class="page-header">
            <div>
                <h2>限流与配额管理</h2>
                <p class="text-muted">管理 API 密钥的调用频率上限和用量配额，超额自动拦截</p>
            </div>
            <el-button @click="loadAll" :loading="loading" :icon="Refresh">刷新</el-button>
        </div>

        <!-- 概览统计 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover">
                    <div class="stat-label">总密钥数</div>
                    <div class="stat-value">{{ overview.total_keys || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover">
                    <div class="stat-label">活跃密钥</div>
                    <div class="stat-value" style="color:#67c23a">{{ overview.active_keys || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover">
                    <div class="stat-label" style="color:#e6a23c">接近限额</div>
                    <div class="stat-value" style="color:#e6a23c">{{ overview.keys_near_quota || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover">
                    <div class="stat-label">即将过期</div>
                    <div class="stat-value" style="color:#f56c6c">{{ overview.keys_expiring_soon || 0 }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 搜索筛选 -->
        <el-card shadow="hover" class="mb-4">
            <el-form :inline="true" @submit.prevent="doSearch">
                <el-form-item label="搜索">
                    <el-input v-model="search" placeholder="密钥名称/ID" clearable style="width:200px" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" native-type="submit" :icon="Search">查询</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 密钥配额列表 -->
        <el-card shadow="hover">
            <el-table :data="keys" v-loading="loading" stripe>
                <el-table-column label="密钥名称" min-width="140" prop="name" />
                <el-table-column label="Key ID" width="150">
                    <template #default="{ row }"><code style="font-size:12px">{{ row.key_id }}</code></template>
                </el-table-column>
                <el-table-column label="限流" width="80" align="center">
                    <template #default="{ row }">{{ row.rate_limit ? row.rate_limit + '/s' : '不限' }}</template>
                </el-table-column>
                <el-table-column label="总用量配额" min-width="160">
                    <template #default="{ row }">
                        <div v-if="row.usage_quota" class="quota-bar">
                            <el-progress
                                :percentage="Math.min(row.usage_percent || 0, 100)"
                                :status="(row.usage_percent || 0) >= 80 ? 'exception' : 'success'"
                                :stroke-width="16"
                                :text-inside="true"
                            >
                                {{ row.usage_count }}/{{ row.usage_quota }}
                            </el-progress>
                        </div>
                        <span v-else class="text-muted">不限</span>
                    </template>
                </el-table-column>
                <el-table-column label="每日配额" min-width="160">
                    <template #default="{ row }">
                        <div v-if="row.daily_quota" class="quota-bar">
                            <el-progress
                                :percentage="Math.min(row.daily_usage_percent || 0, 100)"
                                :status="(row.daily_usage_percent || 0) >= 80 ? 'exception' : ''"
                                :stroke-width="16"
                                :text-inside="true"
                            >
                                {{ row.daily_usage }}/{{ row.daily_quota }}
                            </el-progress>
                        </div>
                        <span v-else class="text-muted">不限</span>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
                            {{ row.is_active ? '启用' : '禁用' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="过期时间" width="120">
                    <template #default="{ row }">
                        <span v-if="row.expires_at" :class="isExpiring(row.expires_at) ? 'expiry-warning' : ''">
                            {{ fmtDate(row.expires_at) }}
                        </span>
                        <span v-else class="text-muted">永久</span>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" link type="primary" @click="editQuota(row)">编辑配额</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!keys.length && !loading" description="暂无 API 密钥" :image-size="60" />

            <div class="pagination-wrap" v-if="total > 0">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :total="total"
                    :page-sizes="[10, 20, 50]"
                    layout="total, sizes, prev, pager, next"
                    @current-change="loadKeys"
                    @size-change="loadKeys"
                />
            </div>
        </el-card>

        <!-- 编辑配额对话框 -->
        <el-dialog v-model="editVisible" title="编辑配额" width="480px">
            <el-form :model="editForm" label-position="top">
                <el-form-item label="密钥">
                    <el-tag>{{ editForm.name }}</el-tag>
                </el-form-item>
                <el-form-item label="限流（每秒请求数）">
                    <el-input-number v-model="editForm.rate_limit" :min="1" :max="10000" :step="100" placeholder="不限" style="width:100%" />
                    <div class="form-hint">0 或空表示不限流</div>
                </el-form-item>
                <el-form-item label="总用量配额">
                    <el-input-number v-model="editForm.usage_quota" :min="1" :step="1000" placeholder="不限" style="width:100%" />
                    <div class="form-hint">0 或空表示不限制总用量</div>
                </el-form-item>
                <el-form-item label="每日用量配额">
                    <el-input-number v-model="editForm.daily_quota" :min="1" :step="100" placeholder="不限" style="width:100%" />
                    <div class="form-hint">0 或空表示不限制每日用量</div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="editVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="saveQuota">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, Search } from '@element-plus/icons-vue';
import apiKeyApi from '@/api/apiKey';

const loading = ref(false);
const saving = ref(false);
const keys = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);
const search = ref('');
const editVisible = ref(false);

const overview = reactive({
    total_keys: 0, active_keys: 0, keys_near_quota: 0, keys_expiring_soon: 0, keys_expired: 0,
});

const editForm = reactive({
    id: null, name: '', rate_limit: null, usage_quota: null, daily_quota: null,
});

function fmtDate(t) {
    if (!t) return '-';
    return new Date(t).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
    });
}

function isExpiring(t) {
    if (!t) return false;
    const days = (new Date(t) - new Date()) / (1000 * 60 * 60 * 24);
    return days <= 14 && days > 0;
}

async function loadOverview() {
    try {
        const { data: res } = await apiKeyApi.myOverview();
        const d = res.data || {};
        overview.total_keys = d.total_keys || 0;
        overview.active_keys = d.active_keys || 0;
        overview.keys_near_quota = d.keys_near_quota || 0;
        overview.keys_expiring_soon = d.keys_expiring_soon || 0;
    } catch { /* ignore */ }
}

async function loadKeys() {
    loading.value = true;
    try {
        const params = { page: page.value, per_page: perPage.value };
        if (search.value) params.search = search.value;
        const { data: res } = await apiKeyApi.list(params);
        keys.value = res.data?.data || res.data || [];
        total.value = res.meta?.total || res.data?.total || keys.value.length;
    } catch {
        keys.value = [];
    } finally {
        loading.value = false;
    }
}

function doSearch() {
    page.value = 1;
    loadKeys();
}

function editQuota(row) {
    editForm.id = row.id;
    editForm.name = row.name;
    editForm.rate_limit = row.rate_limit ?? null;
    editForm.usage_quota = row.usage_quota ?? null;
    editForm.daily_quota = row.daily_quota ?? null;
    editVisible.value = true;
}

async function saveQuota() {
    saving.value = true;
    try {
        await apiKeyApi.update(editForm.id, {
            rate_limit: editForm.rate_limit || null,
            usage_quota: editForm.usage_quota || null,
            daily_quota: editForm.daily_quota || null,
        });
        ElMessage.success('配额已更新');
        editVisible.value = false;
        loadKeys();
        loadOverview();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败');
    } finally {
        saving.value = false;
    }
}

async function loadAll() {
    loading.value = true;
    await Promise.all([loadOverview(), loadKeys()]);
    loading.value = false;
}

onMounted(loadAll);
</script>

<style scoped>
.quota-page { padding: 20px; }
.page-header {
    display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;
}
.page-header h2 { margin: 0; font-size: 20px; }
.text-muted { color: #909399; font-size: 13px; margin: 4px 0 0; }
.mb-4 { margin-bottom: 16px; }
.stat-label { font-size: 13px; color: #909399; }
.stat-value { font-size: 24px; font-weight: 700; color: #303133; }
.pagination-wrap { display: flex; justify-content: flex-end; padding: 16px 0 0; }
.quota-bar { padding: 2px 0; }
.expiry-warning { color: #e6a23c; font-weight: 500; }
.form-hint { font-size: 12px; color: #909399; margin-top: 4px; }
</style>
