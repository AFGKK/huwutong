<template>
    <div class="global-resource-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('global_resources_page.title') }}</h2>
                <span class="header-subtitle">{{ t('global_resources_page.subtitle') }}</span>
            </div>
        </div>

        <el-alert
            :title="t('global_resources_page.alert')"
            type="info" show-icon :closable="false" class="alert-bar"
        />

        <el-row :gutter="16">
            <el-col :span="12">
                <el-card shadow="never" class="section-card">
                    <template #header>
                        <div class="flex-between">
                            <span>{{ t('global_resources_page.models') }}</span>
                            <el-tag type="primary" size="small">{{ t('global_resources_page.count', { n: configData.whitelisted_models?.length || 0 }) }}</el-tag>
                        </div>
                    </template>
                    <el-table :data="modelTableData" stripe size="small">
                        <el-table-column label="#" width="50" type="index" />
                        <el-table-column :label="t('global_resources_page.cols.model')" prop="class" min-width="300">
                            <template #default="{ row }">
                                <code class="mono">{{ row.class }}</code>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="never" class="section-card">
                    <template #header>
                        <div class="flex-between">
                            <span>{{ t('global_resources_page.tables') }}</span>
                            <el-tag type="primary" size="small">{{ t('global_resources_page.count', { n: configData.whitelisted_tables?.length || 0 }) }}</el-tag>
                        </div>
                    </template>
                    <el-table :data="tableTableData" stripe size="small">
                        <el-table-column label="#" width="50" type="index" />
                        <el-table-column :label="t('global_resources_page.cols.table')" prop="table" min-width="200">
                            <template #default="{ row }">
                                <code class="mono">{{ row.table }}</code>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never" class="section-card">
            <template #header>
                <div class="flex-between">
                    <span>{{ t('global_resources_page.write_protection') }}</span>
                    <el-tag v-if="canWrite" type="success" size="small">{{ t('global_resources_page.has_permission') }}</el-tag>
                    <el-tag v-else type="danger" size="small">{{ t('global_resources_page.no_permission') }}</el-tag>
                </div>
            </template>
            <el-descriptions :column="3" border size="small">
                <el-descriptions-item :label="t('global_resources_page.write_roles')">
                    <el-tag v-for="r in configData.write_roles" :key="r" size="small" style="margin-right: 4px;">{{ r }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item :label="t('global_resources_page.current_role')">{{ currentRole || '—' }}</el-descriptions-item>
                <el-descriptions-item :label="t('global_resources_page.write_access')">
                    <el-tag :type="canWrite ? 'success' : 'danger'" size="small">
                        {{ canWrite ? t('global_resources_page.allowed') : t('global_resources_page.denied') }}
                    </el-tag>
                </el-descriptions-item>
            </el-descriptions>
        </el-card>

        <el-card shadow="never" class="section-card">
            <template #header>
                <div class="flex-between">
                    <span>{{ t('global_resources_page.audit') }}</span>
                    <el-button size="small" text @click="loadOperations">{{ t('actions.refresh') }}</el-button>
                </div>
            </template>
            <el-table :data="operationList" v-loading="loadingOps" stripe size="small" max-height="400">
                <el-table-column :label="t('global_resources_page.cols.operation')" width="80" prop="operation" />
                <el-table-column :label="t('global_resources_page.cols.resource_type')" min-width="200" prop="resource_type">
                    <template #default="{ row }"><code class="mono">{{ row.resource_type }}</code></template>
                </el-table-column>
                <el-table-column :label="t('global_resources_page.cols.resource_id')" width="80" prop="resource_id" align="center" />
                <el-table-column :label="t('global_resources_page.cols.user')" width="80" prop="user_id" align="center" />
                <el-table-column :label="t('global_resources_page.cols.role')" width="80" prop="user_role" />
                <el-table-column label="IP" width="130" prop="ip_address" />
                <el-table-column :label="t('global_resources_page.cols.allowed')" width="60" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.allowed ? 'success' : 'danger'" size="small">
                            {{ row.allowed ? t('global_resources_page.yes') : t('global_resources_page.no') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('global_resources_page.cols.reason')" min-width="160" prop="reason" />
                <el-table-column :label="t('global_resources_page.cols.time')" width="170" prop="created_at" />
            </el-table>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import globalResourceApi from '@/api/global-resource'

const { t } = useI18n()

const configData = reactive({ whitelisted_models: [], whitelisted_tables: [], write_roles: [] })
const canWrite = ref(false)
const currentRole = ref('')
const operationList = ref([])
const loadingOps = ref(false)

const modelTableData = computed(() =>
    (configData.whitelisted_models || []).map(c => ({ class: c }))
)
const tableTableData = computed(() =>
    (configData.whitelisted_tables || []).map(t => ({ table: t }))
)

async function loadConfig() {
    try {
        const { data: res } = await globalResourceApi.config()
        if (res.success) Object.assign(configData, res.data)
    } catch { /* ignore */ }
}

async function loadCheckWrite() {
    try {
        const { data: res } = await globalResourceApi.checkWrite()
        if (res.success) {
            canWrite.value = res.data?.can_write || false
            currentRole.value = res.data?.user_role || ''
        }
    } catch { /* ignore */ }
}

async function loadOperations() {
    loadingOps.value = true
    try {
        const { data: res } = await globalResourceApi.operations({ per_page: 50 })
        if (res.success) operationList.value = res.data?.data || []
    } finally {
        loadingOps.value = false
    }
}

onMounted(() => {
    loadConfig()
    loadCheckWrite()
    loadOperations()
})
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
