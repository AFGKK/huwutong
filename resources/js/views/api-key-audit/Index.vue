<template>
    <div class="api-key-audit-page">
        <div class="page-header">
            <div>
                <h2>API 密钥审计日志</h2>
                <p class="text-muted">查看 API 密钥的所有操作记录，包括创建、修改、删除、启用/禁用等</p>
            </div>
            <el-button @click="loadLogs" :loading="loading" :icon="Refresh">刷新</el-button>
        </div>

        <!-- 筛选 -->
        <el-card shadow="never" class="mb-4">
            <el-form :inline="true" @submit.prevent="doSearch">
                <el-form-item label="密钥">
                    <el-select v-model="filters.api_key_id" placeholder="全部密钥" clearable style="width:180px" @change="doSearch">
                        <el-option v-for="k in apiKeys" :key="k.id" :label="k.name" :value="k.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="操作">
                    <el-select v-model="filters.action" placeholder="全部操作" clearable style="width:140px" @change="doSearch">
                        <el-option label="创建" value="created" />
                        <el-option label="更新" value="updated" />
                        <el-option label="删除" value="deleted" />
                        <el-option label="启用" value="activated" />
                        <el-option label="禁用" value="deactivated" />
                        <el-option label="重新生成" value="regenerated" />
                        <el-option label="其他" value="other" />
                    </el-select>
                </el-form-item>
                <el-form-item label="起止">
                    <el-date-picker
                        v-model="dateRange"
                        type="datetimerange"
                        range-separator="至"
                        start-placeholder="开始"
                        end-placeholder="结束"
                        style="width:300px"
                        value-format="YYYY-MM-DD HH:mm:ss"
                        @change="doSearch"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" native-type="submit" :icon="Search">查询</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 日志列表 -->
        <el-card shadow="never">
            <el-table :data="logs" v-loading="loading" stripe>
                <el-table-column label="时间" width="170">
                    <template #default="{ row }">{{ fmtTime(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="密钥名称" min-width="140">
                    <template #default="{ row }">{{ row.api_key?.name || '-' }}</template>
                </el-table-column>
                <el-table-column label="密钥 ID" width="130">
                    <template #default="{ row }">
                        <code style="font-size:12px">{{ row.api_key?.key_id || '-' }}</code>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="100">
                    <template #default="{ row }">
                        <el-tag :type="actionType(row.action)" size="small">{{ actionLabel(row.action) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作者" width="120">
                    <template #default="{ row }">{{ row.actor_type === 'user' ? '管理员' : '系统' }}</template>
                </el-table-column>
                <el-table-column label="IP 地址" width="130" prop="ip_address" />
                <el-table-column label="备注" min-width="160" prop="remark" show-overflow-tooltip />
                <el-table-column label="详情" width="80" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" link type="primary" @click="showDetail(row)">查看</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!logs.length && !loading" description="暂无审计日志" :image-size="60" />

            <div class="pagination-wrap" v-if="total > 0">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :total="total"
                    :page-sizes="[20, 50, 100]"
                    layout="total, sizes, prev, pager, next"
                    @current-change="loadLogs"
                    @size-change="loadLogs"
                />
            </div>
        </el-card>

        <!-- 详情抽屉 -->
        <el-drawer v-model="detailVisible" :title="'操作详情'" size="480px">
            <template v-if="currentLog">
                <el-descriptions :column="1" border size="small">
                    <el-descriptions-item label="时间">{{ fmtTime(currentLog.created_at) }}</el-descriptions-item>
                    <el-descriptions-item label="密钥名称">{{ currentLog.api_key?.name }}</el-descriptions-item>
                    <el-descriptions-item label="密钥 ID">
                        <code>{{ currentLog.api_key?.key_id }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item label="操作">
                        <el-tag :type="actionType(currentLog.action)" size="small">{{ actionLabel(currentLog.action) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="操作者 IP">{{ currentLog.ip_address || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="User-Agent">
                        <div style="word-break:break-all;font-size:12px">{{ currentLog.user_agent || '-' }}</div>
                    </el-descriptions-item>
                    <el-descriptions-item label="备注">{{ currentLog.remark || '-' }}</el-descriptions-item>
                </el-descriptions>

                <h4 style="margin:20px 0 8px">变更详情</h4>
                <el-table v-if="changeEntries.length" :data="changeEntries" size="small" stripe>
                    <el-table-column label="字段" width="100" prop="field" />
                    <el-table-column label="旧值" min-width="120">
                        <template #default="{ row }">{{ formatValue(row.old) }}</template>
                    </el-table-column>
                    <el-table-column label="新值" min-width="120">
                        <template #default="{ row }">{{ formatValue(row.new) }}</template>
                    </el-table-column>
                </el-table>
                <el-empty v-else description="无详细变更记录" :image-size="40" />
            </template>
        </el-drawer>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, Search } from '@element-plus/icons-vue';
import apiClient from '@/api/client';
import apiKeyApi from '@/api/apiKey';

const loading = ref(false);
const logs = ref([]);
const apiKeys = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);
const detailVisible = ref(false);
const currentLog = ref(null);
const dateRange = ref(null);

const filters = reactive({
    api_key_id: '',
    action: '',
    date_from: '',
    date_to: '',
});

const ACTION_MAP = {
    created: { type: 'success', label: '创建' },
    updated: { type: 'primary', label: '更新' },
    deleted: { type: 'danger', label: '删除' },
    activated: { type: 'success', label: '启用' },
    deactivated: { type: 'warning', label: '禁用' },
    regenerated: { type: 'warning', label: '重新生成' },
};

function actionType(action) { return ACTION_MAP[action]?.type || 'info'; }
function actionLabel(action) { return ACTION_MAP[action]?.label || action || '-'; }

const changeEntries = computed(() => {
    const log = currentLog.value;
    if (!log) return [];
    const entries = [];
    if (log.old_values && typeof log.old_values === 'object') {
        for (const [key, val] of Object.entries(log.old_values)) {
            entries.push({
                field: key,
                old: val,
                new: log.new_values?.[key] ?? '-',
            });
        }
    }
    return entries;
});

function formatValue(val) {
    if (val === null || val === undefined) return '-';
    if (typeof val === 'boolean') return val ? '是' : '否';
    if (Array.isArray(val)) return val.join(', ') || '(空)';
    if (typeof val === 'object') return JSON.stringify(val);
    return String(val);
}

function fmtTime(t) {
    if (!t) return '-';
    return new Date(t).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
    });
}

async function loadKeys() {
    try {
        const { data: res } = await apiKeyApi.list({ per_page: 200 });
        apiKeys.value = res.data?.data || res.data || [];
    } catch { /* ignore */ }
}

async function loadLogs() {
    loading.value = true;
    try {
        const params = { page: page.value, per_page: perPage.value };
        if (filters.api_key_id) params.api_key_id = filters.api_key_id;
        if (filters.action) params.action = filters.action;
        if (dateRange.value) {
            params.date_from = dateRange.value[0];
            params.date_to = dateRange.value[1];
        }
        const { data: res } = await apiKeyApi.allAuditLogs(params);
        logs.value = res.data?.data || res.data || [];
        total.value = res.meta?.total || res.data?.total || logs.value.length;
    } catch {
        logs.value = [];
    } finally {
        loading.value = false;
    }
}

function doSearch() {
    page.value = 1;
    loadLogs();
}

function showDetail(log) {
    currentLog.value = log;
    detailVisible.value = true;
}

onMounted(() => {
    loadKeys();
    loadLogs();
});
</script>

<style scoped>
.api-key-audit-page { padding: 20px; }
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}
.page-header h2 { margin: 0; font-size: 20px; }
.text-muted { color: #909399; font-size: 13px; margin: 4px 0 0; }
.mb-4 { margin-bottom: 16px; }
.pagination-wrap { display: flex; justify-content: flex-end; padding: 16px 0 0; }
</style>
