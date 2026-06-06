<template>
    <div class="license-cdn-page">
        <div class="page-header">
            <div class="header-left">
                <h2>离线 License 文件云分发</h2>
                <span class="header-subtitle">.license 文件云存储 + CDN 加速</span>
            </div>
        </div>

        <!-- 统计 -->
        <el-row :gutter="16" class="stats-row">
            <el-col :span="4" v-for="s in statCards" :key="s.label">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value" :style="{ color: s.color }">{{ s.value }}</div>
                    <div class="stat-label">{{ s.label }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 操作 -->
        <el-card shadow="never" class="action-card">
            <template #header>
                <div class="flex-between">
                    <span>操作面板</span>
                    <div>
                        <el-button type="primary" size="small" @click="showGenerateDialog = true">
                            <el-icon><Plus /></el-icon> 生成 .license 文件
                        </el-button>
                        <el-button size="small" @click="showBatchDialog = true">
                            <el-icon><Collection /></el-icon> 批量分发
                        </el-button>
                        <el-button size="small" @click="showKeyDialog = true">
                            <el-icon><Refresh /></el-icon> 轮换公钥
                        </el-button>
                    </div>
                </div>
            </template>
            <el-alert title="生成的 .license 文件将自动上传到云存储并生成 CDN 加速 URL，客户端可通过下载端点获取" type="info" show-icon :closable="false" />
        </el-card>

        <!-- 分发列表 -->
        <el-card shadow="never" class="table-card">
            <template #header>
                <div class="flex-between">
                    <span>已分发的 License 文件</span>
                    <div class="filter-row">
                        <el-select v-model="filterStatus" placeholder="状态" size="small" clearable style="width: 110px;" @change="loadList">
                            <el-option label="活跃" value="active" />
                            <el-option label="已吊销" value="revoked" />
                            <el-option label="已过期" value="expired" />
                        </el-select>
                        <el-input v-model="searchText" size="small" placeholder="搜索 License Key / 文件名" clearable style="width: 230px; margin-left: 8px;" @clear="loadList" @keyup.enter="loadList" />
                    </div>
                </div>
            </template>
            <el-table :data="fileList" v-loading="loadingList" stripe>
                <el-table-column label="License Key" min-width="200">
                    <template #default="{ row }">
                        <code class="mono">{{ row.license_key }}</code>
                    </template>
                </el-table-column>
                <el-table-column label="产品" width="130" prop="product_name" />
                <el-table-column label="文件名" width="200" prop="original_filename" />
                <el-table-column label="大小" width="80" prop="file_size" align="right">
                    <template #default="{ row }">{{ formatSize(row.file_size) }}</template>
                </el-table-column>
                <el-table-column label="密钥版本" width="80" prop="key_version" align="center" />
                <el-table-column label="算法" width="90" prop="algorithm" />
                <el-table-column label="状态" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">
                            {{ row.status === 'active' ? '活跃' : row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="下载" width="70" align="center" prop="download_count" />
                <el-table-column label="操作" width="210" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="copyText(row.cdn_url)">复制 URL</el-button>
                        <el-button text size="small" v-if="row.status === 'active'" type="warning" @click="handleRedistribute(row)">重新分发</el-button>
                        <el-button text size="small" v-if="row.status === 'active'" type="danger" @click="handleRevoke(row)">吊销</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="pagination-wrap" v-if="pagination.total > pagination.per_page">
                <el-pagination
                    v-model:current-page="pagination.current_page"
                    :page-size="pagination.per_page"
                    :total="pagination.total"
                    layout="prev, pager, next"
                    small
                    @current-change="loadList"
                />
            </div>
        </el-card>

        <!-- 分发日志 -->
        <el-card shadow="never" class="table-card">
            <template #header>
                <div class="flex-between">
                    <span>分发日志</span>
                    <el-button size="small" text @click="loadLogs">刷新</el-button>
                </div>
            </template>
            <el-table :data="logList" v-loading="loadingLogs" stripe size="small" max-height="300">
                <el-table-column label="License" min-width="180">
                    <template #default="{ row }"><code class="mono">{{ row.license_key }}</code></template>
                </el-table-column>
                <el-table-column label="客户端 IP" width="130" prop="client_ip" />
                <el-table-column label="国家" width="80" prop="country" />
                <el-table-column label="响应" width="70" prop="response_code" align="center" />
                <el-table-column label="字节" width="80" prop="bytes_served" align="right">
                    <template #default="{ row }">{{ formatSize(row.bytes_served) }}</template>
                </el-table-column>
                <el-table-column label="下载时间" width="170" prop="downloaded_at" />
            </el-table>
        </el-card>

        <!-- 生成对话框 -->
        <el-dialog v-model="showGenerateDialog" title="生成 .license 文件" width="460px">
            <el-form label-position="top">
                <el-form-item label="选择 License">
                    <el-select v-model="selectedLicenseId" filterable remote placeholder="搜索 License Key" style="width: 100%;"
                        :remote-method="searchLicenses" :loading="searchingLicense">
                        <el-option v-for="l in licenseOptions" :key="l.id" :label="l.license_key" :value="l.id" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showGenerateDialog = false">取消</el-button>
                <el-button type="primary" :loading="generating" :disabled="!selectedLicenseId" @click="handleGenerate">生成并分发</el-button>
            </template>
        </el-dialog>

        <!-- 批量分发对话框 -->
        <el-dialog v-model="showBatchDialog" title="批量分发" width="400px">
            <el-alert title="将为选中的 License 批量生成 .license 文件并上传到 CDN" type="info" show-icon :closable="false" class="mb-3" />
            <el-table :data="batchLicenseList" stripe size="small" @selection-change="onBatchSelection">
                <el-table-column type="selection" width="40" />
                <el-table-column label="ID" width="60" prop="id" />
                <el-table-column label="License Key" prop="license_key" />
                <el-table-column label="产品" width="100" prop="product_name" />
            </el-table>
            <template #footer>
                <el-button @click="showBatchDialog = false">取消</el-button>
                <el-button type="primary" :loading="batchGenerating" :disabled="batchIds.length === 0" @click="handleBatchGenerate">
                    分发选中 ({{ batchIds.length }})
                </el-button>
            </template>
        </el-dialog>

        <!-- 轮换公钥对话框 -->
        <el-dialog v-model="showKeyDialog" title="轮换公钥" width="500px">
            <el-alert title="轮换后将生成新公钥版本，旧公钥将保留 30 天兼容窗口期" type="warning" show-icon :closable="false" class="mb-3" />
            <el-form label-position="top">
                <el-form-item label="新公钥 (Base64)">
                    <el-input v-model="newPublicKey" type="textarea" :rows="3" placeholder="输入新的 Ed25519 公钥 Base64..." />
                </el-form-item>
                <el-form-item label="算法">
                    <el-radio-group v-model="keyAlgorithm">
                        <el-radio value="Ed25519">Ed25519</el-radio>
                        <el-radio value="RSA-2048">RSA-2048</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showKeyDialog = false">取消</el-button>
                <el-button type="warning" :loading="rotating" :disabled="!newPublicKey" @click="handleRotateKey">确认轮换</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Collection, Refresh } from '@element-plus/icons-vue';
import licenseFileCdnApi from '@/api/license-file-cdn';
import licenseApi from '@/api/license';

const loadingList = ref(false);
const loadingLogs = ref(false);
const generating = ref(false);
const batchGenerating = ref(false);
const rotating = ref(false);
const searchingLicense = ref(false);

const showGenerateDialog = ref(false);
const showBatchDialog = ref(false);
const showKeyDialog = ref(false);

const filterStatus = ref('');
const searchText = ref('');
const stats = reactive({ total_files: 0, active_files: 0, total_downloads: 0, recent_downloads_24h: 0 });
const fileList = ref([]);
const logList = ref([]);
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 });

const selectedLicenseId = ref(null);
const licenseOptions = ref([]);
const batchLicenseList = ref([]);
const batchIds = ref([]);
const newPublicKey = ref('');
const keyAlgorithm = ref('Ed25519');

const statCards = computed(() => [
    { label: '文件总数', value: stats.total_files, color: '#409EFF' },
    { label: '活跃文件', value: stats.active_files, color: '#67C23A' },
    { label: '累计下载', value: stats.total_downloads, color: '#E6A23C' },
    { label: '24h 下载', value: stats.recent_downloads_24h, color: '#F56C6C' },
]);

import { computed } from 'vue';

function formatSize(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB'];
    let i = 0;
    let size = bytes;
    while (size >= 1024 && i < units.length - 1) { size /= 1024; i++; }
    return size.toFixed(1) + ' ' + units[i];
}

function copyText(text) {
    navigator.clipboard.writeText(text).then(() => ElMessage.success('已复制到剪贴板'));
}

async function loadStats() {
    try {
        const { data: res } = await licenseFileCdnApi.stats();
        if (res.success) Object.assign(stats, res.data);
    } catch { /* ignore */ }
}

async function loadList() {
    loadingList.value = true;
    try {
        const params = { page: pagination.current_page, per_page: pagination.per_page };
        if (filterStatus.value) params.status = filterStatus.value;
        if (searchText.value) params.search = searchText.value;
        const { data: res } = await licenseFileCdnApi.index(params);
        if (res.success) {
            fileList.value = res.data?.data || [];
            pagination.current_page = res.data?.current_page || 1;
            pagination.per_page = res.data?.per_page || 20;
            pagination.total = res.data?.total || 0;
        }
    } finally {
        loadingList.value = false;
    }
}

async function loadLogs() {
    loadingLogs.value = true;
    try {
        const { data: res } = await licenseFileCdnApi.logs({ per_page: 50 });
        if (res.success) logList.value = res.data?.data || [];
    } finally {
        loadingLogs.value = false;
    }
}

async function searchLicenses(query) {
    if (!query) return;
    searchingLicense.value = true;
    try {
        const { data: res } = await licenseApi.index({ search: query, per_page: 20 });
        if (res.success) licenseOptions.value = res.data?.data || [];
    } finally {
        searchingLicense.value = false;
    }
}

async function loadBatchLicenses() {
    try {
        const { data: res } = await licenseApi.index({ per_page: 200 });
        if (res.success) batchLicenseList.value = res.data?.data || [];
    } catch { /* ignore */ }
}

function onBatchSelection(rows) {
    batchIds.value = rows.map(r => r.id);
}

async function handleGenerate() {
    if (!selectedLicenseId.value) return;
    generating.value = true;
    try {
        const { data: res } = await licenseFileCdnApi.generate(selectedLicenseId.value);
        if (res.success) {
            ElMessage.success(res.message || '生成成功');
            showGenerateDialog.value = false;
            await loadList();
            await loadStats();
        }
    } catch {
        ElMessage.error('生成失败');
    } finally {
        generating.value = false;
    }
}

async function handleBatchGenerate() {
    if (batchIds.value.length === 0) return;
    batchGenerating.value = true;
    try {
        const { data: res } = await licenseFileCdnApi.batchGenerate(batchIds.value);
        if (res.success) {
            ElMessage.success(res.message || '批量分发完成');
            showBatchDialog.value = false;
            await loadList();
            await loadStats();
        }
    } catch {
        ElMessage.error('批量分发失败');
    } finally {
        batchGenerating.value = false;
    }
}

async function handleRevoke(row) {
    try {
        await ElMessageBox.confirm(`确定吊销 License "${row.license_key}" 的分发文件？`, '确认吊销');
        const { data: res } = await licenseFileCdnApi.revoke(row.license_key, '管理员吊销');
        if (res.success) {
            ElMessage.success('已吊销');
            await loadList();
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('吊销失败');
    }
}

async function handleRedistribute(row) {
    try {
        await ElMessageBox.confirm(`将重新生成并分发 "${row.license_key}" 的文件，旧文件将过期。确定？`, '确认重新分发');
        const { data: res } = await licenseFileCdnApi.redistribute(row.id);
        if (res.success) {
            ElMessage.success('重新分发成功');
            await loadList();
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('重新分发失败');
    }
}

async function handleRotateKey() {
    if (!newPublicKey.value) return;
    rotating.value = true;
    try {
        const { data: res } = await licenseFileCdnApi.rotateKey(newPublicKey.value, keyAlgorithm.value);
        if (res.success) {
            ElMessage.success(res.message || '公钥轮换成功');
            showKeyDialog.value = false;
            newPublicKey.value = '';
        }
    } catch {
        ElMessage.error('轮换失败');
    } finally {
        rotating.value = false;
    }
}

onMounted(() => {
    loadStats();
    loadList();
    loadLogs();
    loadBatchLicenses();
});
</script>

<style scoped>
.license-cdn-page { padding: 20px; }
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}
.stats-row { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-value { font-size: 24px; font-weight: 700; }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }
.action-card,
.table-card { margin-bottom: 16px; }
.flex-between {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.filter-row { display: flex; align-items: center; }
.mono { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 12px; }
.mb-3 { margin-bottom: 12px; }
.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}
:deep(.el-card__body) { padding: 16px; }
</style>
