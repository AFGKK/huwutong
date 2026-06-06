<template>
    <div class="global-resource-page">
        <div class="page-header">
            <div class="header-left">
                <h2>全局资源白名单</h2>
                <span class="header-subtitle">products / feature_flags 等全局共享表不强制 tenant_id 过滤 + 写入保护</span>
            </div>
        </div>

        <el-alert
            title="白名单中的资源对所有租户可见（无需 tenant_id 过滤），写入操作仅限超管和管理员"
            type="info" show-icon :closable="false" class="alert-bar"
        />

        <el-row :gutter="16">
            <el-col :span="12">
                <!-- 白名单模型 -->
                <el-card shadow="never" class="section-card">
                    <template #header>
                        <div class="flex-between">
                            <span>白名单模型</span>
                            <el-tag type="primary" size="small">{{ configData.whitelisted_models?.length || 0 }} 个</el-tag>
                        </div>
                    </template>
                    <el-table :data="modelTableData" stripe size="small">
                        <el-table-column label="#" width="50" type="index" />
                        <el-table-column label="模型类" prop="class" min-width="300">
                            <template #default="{ row }">
                                <code class="mono">{{ row.class }}</code>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
            <el-col :span="12">
                <!-- 白名单表 -->
                <el-card shadow="never" class="section-card">
                    <template #header>
                        <div class="flex-between">
                            <span>白名单表名</span>
                            <el-tag type="primary" size="small">{{ configData.whitelisted_tables?.length || 0 }} 个</el-tag>
                        </div>
                    </template>
                    <el-table :data="tableTableData" stripe size="small">
                        <el-table-column label="#" width="50" type="index" />
                        <el-table-column label="表名" prop="table" min-width="200">
                            <template #default="{ row }">
                                <code class="mono">{{ row.table }}</code>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
        </el-row>

        <!-- 写入权限 -->
        <el-card shadow="never" class="section-card">
            <template #header>
                <div class="flex-between">
                    <span>写入保护</span>
                    <el-tag v-if="canWrite" type="success" size="small">有权限</el-tag>
                    <el-tag v-else type="danger" size="small">无权限</el-tag>
                </div>
            </template>
            <el-descriptions :column="3" border size="small">
                <el-descriptions-item label="允许写入的角色">
                    <el-tag v-for="r in configData.write_roles" :key="r" size="small" style="margin-right: 4px;">{{ r }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="当前用户角色">{{ currentRole || '—' }}</el-descriptions-item>
                <el-descriptions-item label="写入权限">
                    <el-tag :type="canWrite ? 'success' : 'danger'" size="small">
                        {{ canWrite ? '允许' : '拒绝' }}
                    </el-tag>
                </el-descriptions-item>
            </el-descriptions>
        </el-card>

        <!-- 操作审计 -->
        <el-card shadow="never" class="section-card">
            <template #header>
                <div class="flex-between">
                    <span>白名单操作审计</span>
                    <el-button size="small" text @click="loadOperations">刷新</el-button>
                </div>
            </template>
            <el-table :data="operationList" v-loading="loadingOps" stripe size="small" max-height="400">
                <el-table-column label="操作" width="80" prop="operation" />
                <el-table-column label="资源类型" min-width="200" prop="resource_type">
                    <template #default="{ row }"><code class="mono">{{ row.resource_type }}</code></template>
                </el-table-column>
                <el-table-column label="资源 ID" width="80" prop="resource_id" align="center" />
                <el-table-column label="用户" width="80" prop="user_id" align="center" />
                <el-table-column label="角色" width="80" prop="user_role" />
                <el-table-column label="IP" width="130" prop="ip_address" />
                <el-table-column label="允许" width="60" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.allowed ? 'success' : 'danger'" size="small">
                            {{ row.allowed ? '是' : '否' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="原因" min-width="160" prop="reason" />
                <el-table-column label="时间" width="170" prop="created_at" />
            </el-table>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import globalResourceApi from '@/api/global-resource';

const configData = reactive({ whitelisted_models: [], whitelisted_tables: [], write_roles: [] });
const canWrite = ref(false);
const currentRole = ref('');
const operationList = ref([]);
const loadingOps = ref(false);

const modelTableData = computed(() =>
    (configData.whitelisted_models || []).map(c => ({ class: c }))
);
const tableTableData = computed(() =>
    (configData.whitelisted_tables || []).map(t => ({ table: t }))
);

async function loadConfig() {
    try {
        const { data: res } = await globalResourceApi.config();
        if (res.success) Object.assign(configData, res.data);
    } catch { /* ignore */ }
}

async function loadCheckWrite() {
    try {
        const { data: res } = await globalResourceApi.checkWrite();
        if (res.success) {
            canWrite.value = res.data?.can_write || false;
            currentRole.value = res.data?.user_role || '';
        }
    } catch { /* ignore */ }
}

async function loadOperations() {
    loadingOps.value = true;
    try {
        const { data: res } = await globalResourceApi.operations({ per_page: 50 });
        if (res.success) operationList.value = res.data?.data || [];
    } finally {
        loadingOps.value = false;
    }
}

onMounted(() => {
    loadConfig();
    loadCheckWrite();
    loadOperations();
});
</script>

<style scoped>
.global-resource-page { padding: 20px; }
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}
.alert-bar { margin-bottom: 16px; }
.section-card { margin-bottom: 16px; }
.flex-between {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.mono {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 12px;
}
:deep(.el-card__body) { padding: 16px; }
</style>
