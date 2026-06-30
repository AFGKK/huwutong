<template>
    <div class="migration-page">
        <h2>竞品迁移工具</h2>

        <el-row :gutter="20" class="stats-row">
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.total || 0 }}</div><div class="stat-label">总导入任务</div></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ stats.completed || 0 }}</div><div class="stat-label">已完成</div></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value danger">{{ stats.failed || 0 }}</div><div class="stat-label">失败</div></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ stats.totalLicenses || 0 }}</div><div class="stat-label">已迁移License</div></div></el-card></el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <el-tab-pane label="导入列表" name="list">
                <div class="toolbar">
                    <el-button type="primary" @click="showApiDialog = true">从API导入</el-button>
                    <el-button @click="showFileDialog = true">从文件导入</el-button>
                    <el-button @click="loadImports">刷新</el-button>
                </div>
                <el-table :data="imports" v-loading="loading" stripe @row-click="showDetail">
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column prop="source" label="来源" width="120"><template #default="{row}"><el-tag size="small">{{ row.source }}</el-tag></template></el-table-column>
                    <el-table-column prop="status" label="状态" width="100"><template #default="{row}"><el-tag :type="row.status==='completed'?'success':row.status==='failed'?'danger':'warning'" size="small">{{ row.status }}</el-tag></template></el-table-column>
                    <el-table-column prop="total_rows" label="总数" width="70" align="center" />
                    <el-table-column prop="success" label="成功" width="70" align="center" />
                    <el-table-column prop="failed" label="失败" width="70" align="center" />
                    <el-table-column prop="skipped" label="跳过" width="70" align="center" />
                    <el-table-column label="操作" width="120">
                        <template #default="{row}">
                            <el-button size="small" type="primary" @click.stop="handleRun(row)" :disabled="row.status==='running'">执行</el-button>
                        </template>
                    </el-table-column>
                    <el-table-column prop="created_at" label="创建时间" width="170" />
                </el-table>

                <!-- API导入对话框 -->
                <el-dialog v-model="showApiDialog" title="从竞品API导入" width="500px">
                    <el-form :model="apiForm" label-width="120px">
                        <el-form-item label="来源"><el-select v-model="apiForm.source" style="width:100%"><el-option label="Keygen.sh" value="keygen" /><el-option label="LicenseSpring" value="licensespring" /></el-select></el-form-item>
                        <el-form-item label="API Key"><el-input v-model="apiForm.api_key" type="password" show-password /></el-form-item>
                        <el-form-item label="Account ID" v-if="apiForm.source==='keygen'"><el-input v-model="apiForm.account_id" placeholder="Keygen Account ID" /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showApiDialog = false">取消</el-button><el-button type="primary" @click="handleCreateApi">创建导入</el-button></template>
                </el-dialog>

                <!-- 文件导入对话框 -->
                <el-dialog v-model="showFileDialog" title="从文件导入" width="500px">
                    <el-alert title="支持CSV/JSON/XLSX格式，最大10MB" type="info" show-icon :closable="false" style="margin-bottom:16px" />
                    <el-upload :auto-upload="false" :on-change="handleFileSelect" :show-file-list="true" accept=".csv,.json,.xlsx">
                        <el-button>选择文件</el-button>
                    </el-upload>
                    <div v-if="selectedFile" style="margin-top:12px;color:#606266">已选择: {{ selectedFile.name }}</div>
                    <template #footer><el-button @click="showFileDialog = false">取消</el-button><el-button type="primary" @click="handleCreateFile" :disabled="!selectedFile">创建导入</el-button></template>
                </el-dialog>
            </el-tab-pane>

            <!-- 导入详情 -->
            <el-tab-pane label="导入详情" name="detail" :disabled="!selectedImport">
                <div v-if="selectedImport">
                    <el-descriptions :column="2" border>
                        <el-descriptions-item label="来源">{{ selectedImport.source }}</el-descriptions-item>
                        <el-descriptions-item label="状态">{{ selectedImport.status }}</el-descriptions-item>
                        <el-descriptions-item label="总行数">{{ selectedImport.total_rows }}</el-descriptions-item>
                        <el-descriptions-item label="成功率">{{ selectedImport.result_summary?.success_rate || '0%' }}</el-descriptions-item>
                        <el-descriptions-item label="成功">{{ selectedImport.success }}</el-descriptions-item>
                        <el-descriptions-item label="失败">{{ selectedImport.failed }}</el-descriptions-item>
                    </el-descriptions>

                    <h4 style="margin-top:16px">失败行</h4>
                    <el-table :data="failedRows" stripe size="small" v-if="failedRows.length">
                        <el-table-column prop="row_number" label="行号" width="70" />
                        <el-table-column label="原始数据" min-width="300"><template #default="{row}">{{ JSON.stringify(row.original_data) }}</template></el-table-column>
                        <el-table-column prop="error_message" label="错误" min-width="200" />
                    </el-table>
                    <el-empty v-else description="无失败行" />
                </div>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { getMigrationDashboard, getMigrationImports, createApiImport, createFileImport, runMigrationImport, getMigrationImportDetail } from '@/api/migrationEnhancement';

const activeTab = ref('list');
const stats = ref({});
const imports = ref([]);
const loading = ref(false);
const selectedImport = ref(null);
const failedRows = ref([]);
const selectedFile = ref(null);
const showApiDialog = ref(false);
const showFileDialog = ref(false);

const apiForm = reactive({ source: 'keygen', api_key: '', account_id: '' });

async function loadDashboard() {
    try { stats.value = await getMigrationDashboard(); } catch (e) { console.error(e); }
}
async function loadImports() {
    loading.value = true;
    try { const r = await getMigrationImports({ per_page: 50 }); imports.value = r.data || []; }
    catch (e) { console.error(e); } finally { loading.value = false; }
}
async function handleCreateApi() {
    try {
        await createApiImport(apiForm);
        ElMessage.success('导入任务已创建');
        showApiDialog.value = false;
        loadImports();
    } catch (e) { ElMessage.error('创建失败'); }
}
function handleFileSelect(file) { selectedFile.value = file.raw; }
async function handleCreateFile() {
    if (!selectedFile.value) return;
    try {
        await createFileImport({ file: selectedFile.value });
        ElMessage.success('文件导入任务已创建');
        showFileDialog.value = false;
        selectedFile.value = null;
        loadImports();
    } catch (e) { ElMessage.error('创建失败'); }
}
async function handleRun(row) {
    try {
        await runMigrationImport(row.id);
        ElMessage.success('导入完成');
        loadImports();
        loadDashboard();
    } catch (e) { ElMessage.error('导入失败'); }
}
async function showDetail(row) {
    try {
        const r = await getMigrationImportDetail(row.id);
        selectedImport.value = r.import || r;
        failedRows.value = r.failed_rows || [];
        activeTab.value = 'detail';
    } catch (e) { ElMessage.error('获取详情失败'); }
}

onMounted(() => { loadDashboard(); loadImports(); });
</script>

<style scoped>
.migration-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; padding: 6px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #409eff; }
.stat-value.success { color: #67c23a; }
.stat-value.danger { color: #f56c6c; }
.stat-value.info { color: #909399; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.toolbar { margin-bottom: 16px; display: flex; align-items: center; }
</style>
