<template>
    <div class="honeypot-page">
        <h2>主动蜜罐防御</h2>

        <el-row :gutter="20" class="stats-row">
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.total || 0 }}</div><div class="stat-label">总蜜罐</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ stats.active || 0 }}</div><div class="stat-label">待触发</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value danger">{{ stats.triggered || 0 }}</div><div class="stat-label">已触发</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value warning">{{ stats.total_triggers || 0 }}</div><div class="stat-label">总触发次数</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ stats.recent_triggered || 0 }}</div><div class="stat-label">近7天触发</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.disabled || 0 }}</div><div class="stat-label">已禁用</div></div></el-card></el-col>
        </el-row>

        <el-card shadow="never" style="margin-top:16px">
            <div class="toolbar">
                <el-button type="primary" @click="showCreateDialog = true">生成蜜罐 License</el-button>
                <el-button @click="handleGenerateBatch">批量生成（10个）</el-button>
                <el-button @click="loadList">刷新</el-button>
                <div style="flex:1" />
                <el-input v-model="search" placeholder="搜索 Key/标签/备注" clearable style="width:280px" @clear="loadList" @keyup.enter="loadList" />
                <el-select v-model="filterStatus" placeholder="状态" clearable style="width:120px;margin-left:8px" @change="loadList">
                    <el-option label="待触发" value="active" />
                    <el-option label="已触发" value="triggered" />
                    <el-option label="已禁用" value="disabled" />
                </el-select>
            </div>

            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="60" />
                <el-table-column prop="license_key" label="蜜罐 Key" width="240">
                    <template #default="{row}">
                        <code style="font-size:12px;cursor:pointer" @click="copyKey(row.license_key)">{{ row.license_key }}</code>
                    </template>
                </el-table-column>
                <el-table-column prop="label" label="标签" width="140" />
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{row}">
                        <el-tag v-if="row.status === 'active'" type="success" size="small">待触发</el-tag>
                        <el-tag v-else-if="row.status === 'triggered'" type="danger" size="small">已触发</el-tag>
                        <el-tag v-else type="info" size="small">已禁用</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="trigger_count" label="触发次数" width="90" align="center" />
                <el-table-column prop="triggered_ip" label="触发 IP" width="140" />
                <el-table-column prop="triggered_at" label="首次触发" width="170" />
                <el-table-column prop="created_at" label="创建时间" width="170" />
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{row}">
                        <el-button link type="primary" size="small" @click="showDetail(row)">详情</el-button>
                        <el-button v-if="row.status === 'active'" link type="warning" size="small" @click="handleDisable(row)">禁用</el-button>
                        <el-button v-if="row.status === 'disabled'" link type="success" size="small" @click="handleReactivate(row)">激活</el-button>
                        <el-button link type="danger" size="small" @click="handleDelete(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div style="margin-top:16px;text-align:right">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :total="total"
                    :page-sizes="[10, 20, 50]"
                    layout="total, sizes, prev, pager, next"
                    @size-change="loadList"
                    @current-change="loadList"
                />
            </div>
        </el-card>

        <!-- 详情抽屉 -->
        <el-drawer v-model="detailVisible" title="蜜罐详情" :size="500">
            <template v-if="selectedItem">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="ID" :span="2">{{ selectedItem.id }}</el-descriptions-item>
                    <el-descriptions-item label="License Key" :span="2">
                        <code>{{ selectedItem.license_key }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item label="标签">{{ selectedItem.label || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag v-if="selectedItem.status === 'active'" type="success">待触发</el-tag>
                        <el-tag v-else-if="selectedItem.status === 'triggered'" type="danger">已触发</el-tag>
                        <el-tag v-else type="info">已禁用</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="触发次数">{{ selectedItem.trigger_count }}</el-descriptions-item>
                    <el-descriptions-item label="触发 IP">{{ selectedItem.triggered_ip || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="首次触发时间">{{ selectedItem.triggered_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="创建时间" :span="2">{{ selectedItem.created_at }}</el-descriptions-item>
                    <el-descriptions-item label="备注" :span="2">{{ selectedItem.notes || '-' }}</el-descriptions-item>
                </el-descriptions>

                <el-card v-if="selectedItem.triggered_info" shadow="never" style="margin-top:16px">
                    <template #header><span>触发上下文</span></template>
                    <pre class="json-view">{{ JSON.stringify(selectedItem.triggered_info, null, 2) }}</pre>
                </el-card>
            </template>
        </el-drawer>

        <!-- 创建对话框 -->
        <el-dialog v-model="showCreateDialog" title="生成蜜罐 License" width="500px">
            <el-form :model="createForm" label-width="100px">
                <el-form-item label="标签">
                    <el-input v-model="createForm.label" placeholder="如: 测试环境蜜罐, 公开代码库" />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="createForm.notes" type="textarea" :rows="3" placeholder="备注信息" />
                </el-form-item>
                <el-form-item label="生成数量">
                    <el-input-number v-model="createForm.count" :min="1" :max="100" />
                    <span style="margin-left:8px;color:#909399;font-size:12px">一次最多生成100个</span>
                </el-form-item>
                <el-form-item>
                    <el-alert type="info" show-icon :closable="false">
                        <template #title>
                            生成的蜜罐 License Key 将被植入诱饵位置。当有人尝试激活这些 Key 时，系统会自动触发告警并记录攻击者信息。
                        </template>
                    </el-alert>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateDialog = false">取消</el-button>
                <el-button type="primary" @click="handleCreate">生成</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { getHoneypotDashboard, getHoneypotList, createHoneypot, disableHoneypot, reactivateHoneypot, deleteHoneypot } from '@/api/honeypot';

const stats = ref({});
const list = ref([]);
const loading = ref(false);
const page = ref(1);
const perPage = ref(20);
const total = ref(0);
const search = ref('');
const filterStatus = ref('');
const detailVisible = ref(false);
const selectedItem = ref(null);
const showCreateDialog = ref(false);
const createForm = ref({ label: '', notes: '', count: 1 });

async function loadDashboard() {
    try {
        const res = await getHoneypotDashboard();
        stats.value = res.data?.data || {};
    } catch (e) {
        console.error('Failed to load honeypot dashboard', e);
    }
}

async function loadList() {
    loading.value = true;
    try {
        const params = { page: page.value, per_page: perPage.value };
        if (search.value) params.search = search.value;
        if (filterStatus.value) params.status = filterStatus.value;
        const res = await getHoneypotList(params);
        list.value = res.data?.data?.data || [];
        total.value = res.data?.data?.total || 0;
    } catch (e) {
        console.error('Failed to load honeypot list', e);
    } finally {
        loading.value = false;
    }
}

function showDetail(row) {
    selectedItem.value = row;
    detailVisible.value = true;
}

async function handleCreate() {
    try {
        await createHoneypot(createForm.value);
        ElMessage.success(`成功生成 ${createForm.value.count} 个蜜罐 License`);
        showCreateDialog.value = false;
        createForm.value = { label: '', notes: '', count: 1 };
        loadList();
        loadDashboard();
    } catch (e) {
        ElMessage.error('生成失败');
    }
}

async function handleGenerateBatch() {
    try {
        await createHoneypot({ label: '批量蜜罐', notes: '批量自动生成', count: 10 });
        ElMessage.success('已生成 10 个蜜罐 License');
        loadList();
        loadDashboard();
    } catch (e) {
        ElMessage.error('批量生成失败');
    }
}

async function handleDisable(row) {
    try {
        await ElMessageBox.confirm(`禁用后该蜜罐将不再触发告警，确定禁用「${row.license_key}」？`, '确认禁用');
        await disableHoneypot(row.id);
        ElMessage.success('已禁用');
        loadList();
        loadDashboard();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('操作失败');
    }
}

async function handleReactivate(row) {
    try {
        await reactivateHoneypot(row.id);
        ElMessage.success('已重新激活');
        loadList();
        loadDashboard();
    } catch (e) {
        ElMessage.error('操作失败');
    }
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(`确定删除蜜罐「${row.license_key}」？删除后不可恢复。`, '确认删除', { type: 'warning' });
        await deleteHoneypot(row.id);
        ElMessage.success('已删除');
        loadList();
        loadDashboard();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('操作失败');
    }
}

function copyKey(key) {
    navigator.clipboard.writeText(key).then(() => {
        ElMessage.success('已复制到剪贴板');
    }).catch(() => {
        ElMessage.warning('复制失败，请手动选择复制');
    });
}

onMounted(() => {
    loadDashboard();
    loadList();
});
</script>

<style scoped>
.honeypot-page { padding: 20px; }
.stats-row { margin-top: 16px; }
.stat-card { text-align: center; padding: 4px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #409eff; }
.stat-value.danger { color: #f56c6c; }
.stat-value.warning { color: #e6a23c; }
.stat-value.success { color: #67c23a; }
.stat-value.info { color: #909399; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.toolbar { display: flex; align-items: center; margin-bottom: 16px; gap: 8px; flex-wrap: wrap; }
.json-view { background: #f5f7fa; padding: 12px; border-radius: 4px; font-size: 12px; max-height: 400px; overflow: auto; white-space: pre-wrap; word-break: break-all; }
code { background: #f5f7fa; padding: 2px 6px; border-radius: 3px; font-size: 12px; color: #409eff; }
</style>
