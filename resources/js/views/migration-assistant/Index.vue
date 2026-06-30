<template>
    <div class="migration-ai-page">
        <h2>AI 迁移助手</h2>

        <el-row :gutter="20" class="stats-row">
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ stats.total || 0 }}</div><div class="stat-label">总任务</div></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ stats.completed || 0 }}</div><div class="stat-label">已完成</div></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value danger">{{ stats.failed || 0 }}</div><div class="stat-label">失败</div></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.totalImported || 0 }}</div><div class="stat-label">已导入License</div></div></el-card></el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <el-tab-pane label="迁移任务" name="list">
                <div class="toolbar">
                    <el-button type="primary" @click="showCreateDialog = true">新建迁移</el-button>
                    <el-button @click="loadJobs">刷新</el-button>
                </div>
                <el-table :data="jobs" v-loading="loading" stripe @row-click="showDetail">
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column prop="source" label="来源" width="120"><template #default="{row}"><el-tag size="small">{{ row.source }}</el-tag></template></el-table-column>
                    <el-table-column prop="status" label="状态" width="100"><template #default="{row}"><el-tag :type="row.status==='completed'?'success':row.status==='failed'?'danger':'warning'" size="small">{{ row.status }}</el-tag></template></el-table-column>
                    <el-table-column prop="total_items" label="总数" width="70" align="center" />
                    <el-table-column prop="imported_items" label="已导入" width="70" align="center" />
                    <el-table-column prop="failed_items" label="失败" width="70" align="center" />
                    <el-table-column label="操作" width="120">
                        <template #default="{row}">
                            <el-button size="small" type="primary" @click.stop="handleRun(row)" :disabled="row.status==='importing'">执行</el-button>
                        </template>
                    </el-table-column>
                    <el-table-column prop="created_at" label="创建时间" width="170" />
                </el-table>

                <el-dialog v-model="showCreateDialog" title="AI 迁移" width="500px">
                    <el-form :model="createForm" label-width="120px">
                        <el-form-item label="来源系统"><el-select v-model="createForm.source" style="width:100%"><el-option label="Cryptlex" value="cryptlex" /><el-option label="Localazy" value="localazy" /></el-select></el-form-item>
                        <el-form-item label="API Key"><el-input v-model="createForm.api_key" type="password" show-password /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showCreateDialog = false">取消</el-button><el-button type="primary" @click="handleCreate">创建</el-button></template>
                </el-dialog>
            </el-tab-pane>

            <el-tab-pane label="导入详情" name="detail" :disabled="!selectedJob">
                <div v-if="selectedJob">
                    <el-descriptions :column="2" border>
                        <el-descriptions-item label="来源">{{ selectedJob.source }}</el-descriptions-item>
                        <el-descriptions-item label="状态">{{ selectedJob.status }}</el-descriptions-item>
                        <el-descriptions-item label="总行数">{{ selectedJob.total_items }}</el-descriptions-item>
                        <el-descriptions-item label="成功率">{{ summary.success_rate }}%</el-descriptions-item>
                        <el-descriptions-item label="已导入">{{ selectedJob.imported_items }}</el-descriptions-item>
                        <el-descriptions-item label="失败">{{ selectedJob.failed_items }}</el-descriptions-item>
                    </el-descriptions>

                    <h4 style="margin-top:16px">错误详情</h4>
                    <el-table :data="failedItems" stripe size="small" v-if="failedItems.length">
                        <el-table-column prop="item_index" label="序号" width="70" />
                        <el-table-column label="原始数据" min-width="250"><template #default="{row}">{{ JSON.stringify(row.original_data) }}</template></el-table-column>
                        <el-table-column label="错误" min-width="200"><template #default="{row}">{{ JSON.stringify(row.validation_errors) }}</template></el-table-column>
                    </el-table>
                    <el-empty v-else description="无错误" />
                </div>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { getMigrationAssistantDashboard, getMigrationJobs, createMigrationJob, runMigrationJob, getMigrationJobDetail } from '@/api/migrationAssistant';

const activeTab = ref('list');
const stats = ref({});
const jobs = ref([]);
const loading = ref(false);
const selectedJob = ref(null);
const failedItems = ref([]);
const showCreateDialog = ref(false);
const createForm = reactive({ source: 'cryptlex', api_key: '' });

const summary = computed(() => selectedJob.value?.summary || {});

async function loadDashboard() {
    try { stats.value = await getMigrationAssistantDashboard(); } catch (e) { console.error(e); }
}
async function loadJobs() {
    loading.value = true;
    try { const r = await getMigrationJobs({ per_page: 50 }); jobs.value = r.data || []; }
    catch (e) { console.error(e); } finally { loading.value = false; }
}
async function handleCreate() {
    try { await createMigrationJob(createForm); ElMessage.success('任务已创建'); showCreateDialog.value = false; loadJobs(); }
    catch (e) { ElMessage.error('创建失败'); }
}
async function handleRun(row) {
    try { await runMigrationJob(row.id); ElMessage.success('迁移完成'); loadJobs(); loadDashboard(); }
    catch (e) { ElMessage.error('执行失败'); }
}
async function showDetail(row) {
    try { const r = await getMigrationJobDetail(row.id); selectedJob.value = r.job || r; failedItems.value = r.failed_items || []; activeTab.value = 'detail'; }
    catch (e) { ElMessage.error('获取详情失败'); }
}

onMounted(() => { loadDashboard(); loadJobs(); });
</script>

<style scoped>
.migration-ai-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; padding: 6px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #409eff; }
.stat-value.success { color: #67c23a; }
.stat-value.danger { color: #f56c6c; }
.stat-value.info { color: #909399; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.toolbar { margin-bottom: 16px; display: flex; align-items: center; }
</style>
