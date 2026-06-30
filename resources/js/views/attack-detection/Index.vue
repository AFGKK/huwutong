<template>
    <div class="attack-page">
        <h2>AI 攻击模式识别</h2>

        <el-row :gutter="20" class="stats-row">
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.total || 0 }}</div><div class="stat-label">总事件</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value danger">{{ stats.open || 0 }}</div><div class="stat-label">未处理</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value warning">{{ stats.blocked || 0 }}</div><div class="stat-label">已拦截</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ stats.resolved || 0 }}</div><div class="stat-label">已解决</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.falsePositive || 0 }}</div><div class="stat-label">误报</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ stats.activeBlocks || 0 }}</div><div class="stat-label">活跃封禁</div></div></el-card></el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <el-tab-pane label="攻击事件" name="events">
                <div class="toolbar">
                    <el-select v-model="filterType" placeholder="类型" clearable style="width:140px;margin-right:8px" @change="loadEvents">
                        <el-option v-for="(cfg,key) in detectorConfig" :key="key" :label="key" :value="key" />
                    </el-select>
                    <el-select v-model="filterSeverity" placeholder="严重度" clearable style="width:120px;margin-right:8px" @change="loadEvents">
                        <el-option label="严重" value="critical" /><el-option label="警告" value="warning" /><el-option label="信息" value="info" />
                    </el-select>
                    <el-button @click="loadEvents">刷新</el-button>
                </div>
                <el-table :data="events" v-loading="loading" stripe @row-click="showDetail">
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column prop="attack_type" label="类型" width="120"><template #default="{row}"><el-tag size="small">{{ row.attack_type }}</el-tag></template></el-table-column>
                    <el-table-column prop="severity" label="严重度" width="80"><template #default="{row}"><el-tag :type="row.severity==='critical'?'danger':row.severity==='warning'?'warning':'info'" size="small">{{ row.severity }}</el-tag></template></el-table-column>
                    <el-table-column prop="source_ip" label="源IP" width="140" />
                    <el-table-column prop="description" label="描述" min-width="300" show-overflow-tooltip />
                    <el-table-column prop="status" label="状态" width="100"><template #default="{row}"><el-tag :type="row.status==='resolved'?'success':row.status==='blocked'?'warning':'danger'" size="small">{{ row.status }}</el-tag></template></el-table-column>
                    <el-table-column prop="detected_at" label="检测时间" width="170" />
                </el-table>
            </el-tab-pane>

            <el-tab-pane label="事件详情" name="detail" :disabled="!selectedEvent">
                <div v-if="selectedEvent">
                    <el-descriptions :column="2" border>
                        <el-descriptions-item label="类型">{{ selectedEvent.attack_type }}</el-descriptions-item>
                        <el-descriptions-item label="严重度"><el-tag :type="selectedEvent.severity==='critical'?'danger':'warning'">{{ selectedEvent.severity }}</el-tag></el-descriptions-item>
                        <el-descriptions-item label="可信度">{{ (selectedEvent.confidence * 100).toFixed(0) }}%</el-descriptions-item>
                        <el-descriptions-item label="源IP">{{ selectedEvent.source_ip }}</el-descriptions-item>
                        <el-descriptions-item label="状态">{{ selectedEvent.status }}</el-descriptions-item>
                        <el-descriptions-item label="动作">{{ selectedEvent.action_taken || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="描述" :span="2">{{ selectedEvent.description }}</el-descriptions-item>
                    </el-descriptions>
                    <div style="margin-top:12px">
                        <el-select v-model="statusUpdate" style="width:140px;margin-right:8px">
                            <el-option label="调查中" value="investigating" />
                            <el-option label="已拦截" value="blocked" />
                            <el-option label="已解决" value="resolved" />
                            <el-option label="误报" value="false_positive" />
                        </el-select>
                        <el-button type="primary" @click="handleUpdateStatus">更新状态</el-button>
                        <el-button type="danger" @click="handleBlockIp(selectedEvent.source_ip)" v-if="selectedEvent.source_ip">封禁此IP</el-button>
                    </div>

                    <el-card v-if="selectedEvent.context" shadow="never" style="margin-top:16px">
                        <template #header><span>上下文数据</span></template>
                        <pre class="json-view">{{ JSON.stringify(selectedEvent.context, null, 2) }}</pre>
                    </el-card>
                </div>
            </el-tab-pane>

            <el-tab-pane label="IP封禁" name="blocks">
                <div class="toolbar">
                    <el-button type="primary" @click="showBlockDialog = true">手动封禁IP</el-button>
                    <el-button @click="loadIpBlocks">刷新</el-button>
                </div>
                <el-table :data="ipBlocks" v-loading="blocksLoading" stripe>
                    <el-table-column prop="ip" label="IP" width="150" />
                    <el-table-column prop="reason" label="原因" min-width="250" show-overflow-tooltip />
                    <el-table-column prop="attack_type" label="类型" width="100" />
                    <el-table-column prop="confidence" label="可信度" width="80"><template #default="{row}">{{ (row.confidence * 100).toFixed(0) }}%</template></el-table-column>
                    <el-table-column label="永久" width="70" align="center"><template #default="{row}"><el-tag :type="row.is_permanent?'danger':'info'" size="small">{{ row.is_permanent?'是':'否' }}</el-tag></template></el-table-column>
                    <el-table-column prop="expires_at" label="过期时间" width="170" />
                    <el-table-column label="操作" width="100">
                        <template #default="{row}">
                            <el-button link type="danger" size="small" @click="handleUnblockIp(row.ip)">解封</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <el-dialog v-model="showBlockDialog" title="手动封禁IP" width="450px">
                    <el-form :model="blockForm" label-width="100px">
                        <el-form-item label="IP地址"><el-input v-model="blockForm.ip" placeholder="8.8.8.8" /></el-form-item>
                        <el-form-item label="原因"><el-input v-model="blockForm.reason" type="textarea" :rows="2" /></el-form-item>
                        <el-form-item label="封禁时长">
                            <el-select v-model="blockForm.duration_minutes" style="width:100%">
                                <el-option label="30分钟" :value="30" />
                                <el-option label="1小时" :value="60" />
                                <el-option label="6小时" :value="360" />
                                <el-option label="24小时" :value="1440" />
                                <el-option label="永久" :value="0" />
                            </el-select>
                        </el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showBlockDialog = false">取消</el-button><el-button type="danger" @click="handleBlockConfirm">封禁</el-button></template>
                </el-dialog>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { getAttackDashboard, getAttackEvents, getAttackEventDetail, updateAttackEventStatus, getIpBlocks, blockIp, unblockIp } from '@/api/attackDetection';

const activeTab = ref('events');
const stats = ref({});
const events = ref([]);
const loading = ref(false);
const selectedEvent = ref(null);
const statusUpdate = ref('investigating');
const filterType = ref('');
const filterSeverity = ref('');
const detectorConfig = ref({});
const ipBlocks = ref([]);
const blocksLoading = ref(false);
const showBlockDialog = ref(false);
const blockForm = reactive({ ip: '', reason: '', duration_minutes: 60 });

async function loadDashboard() {
    try { stats.value = await getAttackDashboard(); } catch (e) { console.error(e); }
}
async function loadEvents() {
    loading.value = true;
    try {
        const params = { per_page: 50 };
        if (filterType.value) params.attack_type = filterType.value;
        if (filterSeverity.value) params.severity = filterSeverity.value;
        const r = await getAttackEvents(params);
        events.value = r.data || [];
    } catch (e) { console.error(e); } finally { loading.value = false; }
}
async function loadIpBlocks() {
    blocksLoading.value = true;
    try { const r = await getIpBlocks({ per_page: 50 }); ipBlocks.value = r.data || []; }
    catch (e) { console.error(e); } finally { blocksLoading.value = false; }
}
async function showDetail(row) {
    try { const r = await getAttackEventDetail(row.id); selectedEvent.value = r; statusUpdate.value = r.status; activeTab.value = 'detail'; }
    catch (e) { ElMessage.error('获取详情失败'); }
}
async function handleUpdateStatus() {
    try { await updateAttackEventStatus(selectedEvent.value.id, statusUpdate.value); ElMessage.success('状态已更新'); loadEvents(); }
    catch (e) { ElMessage.error('更新失败'); }
}
async function handleBlockIp(ip) {
    try {
        await ElMessageBox.confirm(`确定封禁IP ${ip}？`, '确认');
        await blockIp({ ip, reason: '攻击事件关联封禁', duration_minutes: 60 });
        ElMessage.success('IP已封禁');
        loadIpBlocks();
    } catch (e) { if (e !== 'cancel') ElMessage.error('封禁失败'); }
}
async function handleBlockConfirm() {
    try {
        await blockIp({
            ip: blockForm.ip,
            reason: blockForm.reason,
            duration_minutes: blockForm.duration_minutes || 60,
            is_permanent: blockForm.duration_minutes === 0,
        });
        ElMessage.success('IP已封禁');
        showBlockDialog.value = false;
        loadIpBlocks();
    } catch (e) { ElMessage.error('封禁失败'); }
}
async function handleUnblockIp(ip) {
    try { await unblockIp(ip); ElMessage.success('已解封'); loadIpBlocks(); }
    catch (e) { ElMessage.error('解封失败'); }
}

onMounted(() => {
    loadDashboard(); loadEvents(); loadIpBlocks();
});
</script>

<style scoped>
.attack-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; padding: 6px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #409eff; }
.stat-value.danger { color: #f56c6c; }
.stat-value.warning { color: #e6a23c; }
.stat-value.success { color: #67c23a; }
.stat-value.info { color: #909399; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.toolbar { margin-bottom: 16px; display: flex; align-items: center; flex-wrap: wrap; }
.json-view { background: #f5f7fa; padding: 12px; border-radius: 4px; font-size: 12px; max-height: 300px; overflow: auto; }
</style>
