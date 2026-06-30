<template>
    <div class="watermark-page">
        <div class="page-header">
            <div class="header-left">
                <h2>License 水印与防篡改</h2>
                <span class="header-subtitle">数字水印、完整性验证、防篡改监控</span>
            </div>
            <div class="header-right">
                <el-button @click="refreshAll">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
            </div>
        </div>

        <!-- 概览统计 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">防篡改事件</div>
                        <div class="stat-value primary">{{ dashboard.total_events || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">未处理事件</div>
                        <div class="stat-value" :class="(dashboard.unresolved_events || 0) > 0 ? 'danger' : 'success'">
                            {{ dashboard.unresolved_events || 0 }}
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">活跃水印</div>
                        <div class="stat-value warning">{{ dashboard.active_watermarks || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">今日事件</div>
                        <div class="stat-value info">{{ dashboard.today_events || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- 仪表盘 -->
            <el-tab-pane label="监控仪表盘" name="dashboard">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header>
                                <div class="card-header"><span>事件类型分布</span></div>
                            </template>
                            <div v-if="!Object.keys(dashboard.events_by_type || {}).length" class="empty-state">暂无事件</div>
                            <div v-else class="type-dist">
                                <div v-for="(count, type) in dashboard.events_by_type" :key="type" class="dist-row">
                                    <span class="dist-label">{{ eventTypeLabel(type) }}</span>
                                    <el-progress :percentage="calcPct(count, dashboard.total_events)" :stroke-width="18" :text-inside="true" />
                                    <span class="dist-count">{{ count }}</span>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header>
                                <div class="card-header"><span>严重级别分布</span></div>
                            </template>
                            <div v-if="!Object.keys(dashboard.events_by_severity || {}).length" class="empty-state">暂无事件</div>
                            <div v-else class="type-dist">
                                <div v-for="(count, sev) in dashboard.events_by_severity" :key="sev" class="dist-row">
                                    <span class="dist-label">{{ severityLabel(sev) }}</span>
                                    <el-progress
                                        :percentage="calcPct(count, dashboard.total_events)"
                                        :stroke-width="18"
                                        :text-inside="true"
                                        :status="sev === 'critical' || sev === 'high' ? 'exception' : sev === 'medium' ? 'warning' : 'success'"
                                    />
                                    <span class="dist-count">{{ count }}</span>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>最近事件</span>
                            <el-button text size="small" @click="activeTab = 'events'">查看全部 &gt;</el-button>
                        </div>
                    </template>
                    <el-table :data="dashboard.recent_events || []" stripe size="small">
                        <el-table-column label="时间" width="160">
                            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                        </el-table-column>
                        <el-table-column label="事件类型" width="140">
                            <template #default="{ row }">
                                <el-tag :type="eventTypeTag(row.event_type)" size="small">{{ eventTypeLabel(row.event_type) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="严重级别" width="100">
                            <template #default="{ row }">
                                <el-tag :type="severityTag(row.severity)" size="small">{{ severityLabel(row.severity) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="License" min-width="200" prop="license_key" />
                        <el-table-column label="状态" width="90">
                            <template #default="{ row }">
                                <el-tag :type="row.is_resolved ? 'success' : 'danger'" size="small">
                                    {{ row.is_resolved ? '已处理' : '待处理' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- 水印管理 -->
            <el-tab-pane label="水印管理" name="watermarks">
                <div class="tab-toolbar">
                    <div class="toolbar-left">
                        <el-input v-model="watermarkSearch" placeholder="搜索水印 Key 或 License Key" clearable style="width: 300px" @keyup.enter="handleSearchWatermark" />
                        <el-button @click="handleSearchWatermark">搜索</el-button>
                    </div>
                    <div class="toolbar-right">
                        <el-button type="primary" @click="openEmbedDialog">
                            <el-icon><Plus /></el-icon> 嵌入水印
                        </el-button>
                    </div>
                </div>

                <el-table :data="watermarks" v-loading="loadingWatermarks" stripe>
                    <el-table-column label="水印 Key" min-width="280">
                        <template #default="{ row }">
                            <code class="mono-text">{{ row.watermark_key }}</code>
                        </template>
                    </el-table-column>
                    <el-table-column label="License" min-width="200" prop="license?.license_key" />
                    <el-table-column label="客户" width="150">
                        <template #default="{ row }">{{ row.license?.customer?.name || '-' }}</template>
                    </el-table-column>
                    <el-table-column label="算法" width="100">
                        <template #default="{ row }">
                            <el-tag size="small">{{ row.algorithm }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="90">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">
                                {{ row.status === 'active' ? '活跃' : '已吊销' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="嵌入时间" width="170">
                        <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                    </el-table-column>
                    <el-table-column label="操作" width="150" fixed="right">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="showWatermarkDetail(row)">详情</el-button>
                            <el-button v-if="row.status === 'active'" text size="small" type="danger" @click="handleRevokeWatermark(row)">吊销</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="pagination-wrap" v-if="watermarkMeta.total > watermarkMeta.per_page">
                    <el-pagination
                        v-model:current-page="watermarkPage"
                        :page-size="watermarkMeta.per_page"
                        :total="watermarkMeta.total"
                        layout="total, prev, pager, next"
                        @current-change="fetchWatermarks"
                    />
                </div>

                <!-- 嵌入水印对话框 -->
                <el-dialog v-model="showEmbedDialog" title="嵌入 License 水印" width="500px">
                    <el-form ref="embedFormRef" :model="embedForm" :rules="embedRules" label-width="120px">
                        <el-form-item label="License ID" prop="license_id">
                            <el-input-number v-model="embedForm.license_id" :min="1" style="width: 200px" />
                        </el-form-item>
                        <el-form-item label="溯源信息">
                            <el-input v-model="embedForm.source_note" type="textarea" :rows="3" placeholder="可选，例如：分发对象、使用场景等" />
                        </el-form-item>
                        <el-form-item>
                            <el-alert type="info" :closable="false" show-icon>
                                <template #title>嵌入水印后，License 的 metadata 将包含不可见标记，可用于溯源泄密来源</template>
                            </el-alert>
                        </el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showEmbedDialog = false">取消</el-button>
                        <el-button type="primary" :loading="embedding" @click="handleEmbedWatermark">确认嵌入</el-button>
                    </template>
                </el-dialog>

                <!-- 水印详情弹窗 -->
                <el-dialog v-model="showWatermarkDetailDialog" title="水印详情" width="600px">
                    <template v-if="watermarkDetail">
                        <el-descriptions :column="2" border size="small">
                            <el-descriptions-item label="水印 Key" :span="2">
                                <code class="mono-text">{{ watermarkDetail.watermark_key }}</code>
                            </el-descriptions-item>
                            <el-descriptions-item label="License Key">{{ watermarkDetail.license?.license_key }}</el-descriptions-item>
                            <el-descriptions-item label="客户">{{ watermarkDetail.license?.customer?.name || '-' }}</el-descriptions-item>
                            <el-descriptions-item label="算法">{{ watermarkDetail.algorithm }}</el-descriptions-item>
                            <el-descriptions-item label="状态">
                                <el-tag :type="watermarkDetail.status === 'active' ? 'success' : 'danger'" size="small">
                                    {{ watermarkDetail.status === 'active' ? '活跃' : '已吊销' }}
                                </el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item label="嵌入位置">{{ watermarkDetail.embed_location }}</el-descriptions-item>
                            <el-descriptions-item label="嵌入时间" :span="2">{{ formatTime(watermarkDetail.created_at) }}</el-descriptions-item>
                        </el-descriptions>
                        <div v-if="watermarkDetail.watermark_data" class="detail-section">
                            <h4>水印数据（溯源信息）</h4>
                            <pre class="json-block">{{ JSON.stringify(watermarkDetail.watermark_data, null, 2) }}</pre>
                        </div>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <!-- 防篡改事件 -->
            <el-tab-pane label="防篡改事件" name="events">
                <div class="tab-toolbar">
                    <div class="toolbar-left">
                        <el-select v-model="eventFilter.event_type" placeholder="事件类型" clearable style="width: 160px" @change="fetchEvents">
                            <el-option label="签名失败" value="signature" />
                            <el-option label="水印不匹配" value="watermark" />
                            <el-option label="完整性破坏" value="integrity" />
                            <el-option label="设备异常" value="device" />
                            <el-option label="时间回滚" value="time_rollback" />
                        </el-select>
                        <el-select v-model="eventFilter.severity" placeholder="严重级别" clearable style="width: 130px" @change="fetchEvents">
                            <el-option label="低" value="low" />
                            <el-option label="中" value="medium" />
                            <el-option label="高" value="high" />
                            <el-option label="严重" value="critical" />
                        </el-select>
                        <el-select v-model="eventFilter.is_resolved" placeholder="处理状态" clearable style="width: 130px" @change="fetchEvents">
                            <el-option label="待处理" value="false" />
                            <el-option label="已处理" value="true" />
                        </el-select>
                        <el-input v-model="eventFilter.license_key" placeholder="License Key" clearable style="width: 200px" @keyup.enter="fetchEvents" />
                        <el-button @click="fetchEvents">查询</el-button>
                        <el-button @click="resetEventFilter">重置</el-button>
                    </div>
                </div>

                <el-table :data="tamperEvents" v-loading="loadingEvents" stripe>
                    <el-table-column label="时间" width="160">
                        <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                    </el-table-column>
                    <el-table-column label="事件类型" width="140">
                        <template #default="{ row }">
                            <el-tag :type="eventTypeTag(row.event_type)" size="small">{{ eventTypeLabel(row.event_type) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="严重级别" width="90">
                        <template #default="{ row }">
                            <el-tag :type="severityTag(row.severity)" size="small">{{ severityLabel(row.severity) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="License" min-width="200" prop="license_key" />
                    <el-table-column label="来源 IP" width="130" prop="source_ip" />
                    <el-table-column label="状态" width="90">
                        <template #default="{ row }">
                            <el-tag :type="row.is_resolved ? 'success' : 'danger'" size="small">
                                {{ row.is_resolved ? '已处理' : '待处理' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="处理说明" min-width="180" prop="resolution" />
                    <el-table-column label="操作" width="130" fixed="right">
                        <template #default="{ row }">
                            <el-button v-if="!row.is_resolved" text size="small" type="primary" @click="openResolveDialog(row)">处理</el-button>
                            <el-button text size="small" @click="showEventDetail(row)">详情</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- 处理事件对话框 -->
                <el-dialog v-model="showResolveDialog" title="处理防篡改事件" width="450px">
                    <el-form>
                        <el-form-item label="事件类型">
                            <el-tag :type="eventTypeTag(resolvingEvent?.event_type)" size="small">
                                {{ eventTypeLabel(resolvingEvent?.event_type) }}
                            </el-tag>
                        </el-form-item>
                        <el-form-item label="License">
                            <code>{{ resolvingEvent?.license_key }}</code>
                        </el-form-item>
                        <el-form-item label="处理说明">
                            <el-input v-model="resolveNote" type="textarea" :rows="3" placeholder="请说明处理方式" />
                        </el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showResolveDialog = false">取消</el-button>
                        <el-button type="primary" :loading="resolving" @click="handleResolveEvent">确认处理</el-button>
                    </template>
                </el-dialog>

                <!-- 事件详情对话框 -->
                <el-dialog v-model="showEventDetailDialog" title="事件详情" width="650px">
                    <template v-if="eventDetail">
                        <el-descriptions :column="2" border size="small">
                            <el-descriptions-item label="事件类型">
                                <el-tag :type="eventTypeTag(eventDetail.event_type)" size="small">{{ eventTypeLabel(eventDetail.event_type) }}</el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item label="严重级别">
                                <el-tag :type="severityTag(eventDetail.severity)" size="small">{{ severityLabel(eventDetail.severity) }}</el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item label="License Key" :span="2"><code>{{ eventDetail.license_key || '-' }}</code></el-descriptions-item>
                            <el-descriptions-item label="来源 IP">{{ eventDetail.source_ip || '-' }}</el-descriptions-item>
                            <el-descriptions-item label="设备指纹" :span="1"><code>{{ eventDetail.source_fingerprint || '-' }}</code></el-descriptions-item>
                            <el-descriptions-item label="处理状态">{{ eventDetail.is_resolved ? '已处理' : '待处理' }}</el-descriptions-item>
                            <el-descriptions-item label="处理说明">{{ eventDetail.resolution || '-' }}</el-descriptions-item>
                            <el-descriptions-item label="发生时间" :span="2">{{ formatTime(eventDetail.created_at) }}</el-descriptions-item>
                        </el-descriptions>
                        <div v-if="eventDetail.event_data" class="detail-section">
                            <h4>事件数据</h4>
                            <pre class="json-block">{{ JSON.stringify(eventDetail.event_data, null, 2) }}</pre>
                        </div>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <!-- 防篡改策略 -->
            <el-tab-pane label="防篡改策略" name="policies">
                <el-alert title="防篡改策略定义了在检测到可疑行为时系统自动触发的动作" type="info" :closable="false" show-icon class="mb-4" />
                <el-table :data="policies" v-loading="loadingPolicies" stripe>
                    <el-table-column label="规则名称" min-width="200" prop="rule_name" />
                    <el-table-column label="规则类型" width="120">
                        <template #default="{ row }">
                            <el-tag size="small">{{ row.rule_type }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="严重级别" width="100">
                        <template #default="{ row }">
                            <el-tag :type="severityTag(row.severity)" size="small">{{ severityLabel(row.severity) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="触发阈值" width="100" align="center" prop="threshold" />
                    <el-table-column label="冷却(秒)" width="100" align="center" prop="cooldown_seconds" />
                    <el-table-column label="触发动作" min-width="180">
                        <template #default="{ row }">
                            <el-tag v-for="act in (row.actions || [])" :key="act.type" size="small" style="margin-right:4px">
                                {{ act.type === 'alert' ? '告警' : act.type === 'suspend_license' ? '暂停License' : act.type === 'notify_admin' ? '通知管理员' : act.type }}
                            </el-tag>
                            <span v-if="!row.actions?.length">—</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="启用" width="80" align="center">
                        <template #default="{ row }">
                            <el-switch v-model="row.is_active" @change="(val) => handleTogglePolicy(row, val)" />
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="120" fixed="right">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="openPolicyDialog(row)">编辑</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- 编辑策略对话框 -->
                <el-dialog v-model="showPolicyDialog" :title="'编辑策略: ' + (editingPolicy?.rule_name || '')" width="550px">
                    <el-form label-width="120px">
                        <el-form-item label="规则类型">
                            <el-tag>{{ editingPolicy?.rule_type }}</el-tag>
                        </el-form-item>
                        <el-form-item label="严重级别">
                            <el-select v-model="policyForm.severity" style="width: 100%">
                                <el-option label="低" value="low" />
                                <el-option label="中" value="medium" />
                                <el-option label="高" value="high" />
                                <el-option label="严重" value="critical" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="触发阈值">
                            <el-input-number v-model="policyForm.threshold" :min="1" :max="100" style="width: 200px" />
                            <div class="form-help">在冷却时间内触发此次数后执行动作</div>
                        </el-form-item>
                        <el-form-item label="冷却时间(秒)">
                            <el-input-number v-model="policyForm.cooldown_seconds" :min="10" :max="86400" :step="30" style="width: 200px" />
                        </el-form-item>
                        <el-form-item label="描述">
                            <el-input v-model="policyForm.description" type="textarea" :rows="2" maxlength="500" />
                        </el-form-item>
                        <el-form-item label="启用">
                            <el-switch v-model="policyForm.is_active" />
                        </el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showPolicyDialog = false">取消</el-button>
                        <el-button type="primary" :loading="savingPolicy" @click="handleSavePolicy">保存</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Plus } from '@element-plus/icons-vue';
import watermarkApi from '@/api/watermarkTamper';

// ─── 标签切换 ───
const activeTab = ref('dashboard');

// ─── 仪表盘 ───
const dashboard = reactive({
    total_events: 0, unresolved_events: 0, today_events: 0,
    active_watermarks: 0, total_watermarks: 0,
    events_by_type: {}, events_by_severity: {},
    recent_events: [],
    verification_stats: {},
    policies: [],
});

async function fetchDashboard() {
    try {
        const res = await watermarkApi.dashboard();
        if (res.success) Object.assign(dashboard, res.data || {});
    } catch { /* ignore */ }
}

// ─── 水印管理 ───
const watermarks = ref([]);
const watermarkMeta = reactive({ total: 0, per_page: 20, current_page: 1 });
const watermarkPage = ref(1);
const loadingWatermarks = ref(false);
const watermarkSearch = ref('');
const showEmbedDialog = ref(false);
const embedding = ref(false);
const embedFormRef = ref(null);
const embedForm = reactive({ license_id: null, source_note: '' });
const embedRules = { license_id: [{ required: true, message: '请输入 License ID', trigger: 'blur' }] };
const showWatermarkDetailDialog = ref(false);
const watermarkDetail = ref(null);

async function fetchWatermarks() {
    loadingWatermarks.value = true;
    try {
        const res = await watermarkApi.watermarks({ page: watermarkPage.value, per_page: 20 });
        if (res.success) {
            watermarks.value = res.data || [];
            Object.assign(watermarkMeta, res.meta || {});
        }
    } catch {
        ElMessage.error('加载水印列表失败');
    } finally {
        loadingWatermarks.value = false;
    }
}

async function handleSearchWatermark() {
    if (!watermarkSearch.value) {
        await fetchWatermarks();
        return;
    }
    loadingWatermarks.value = true;
    try {
        const res = await watermarkApi.searchWatermarks(watermarkSearch.value);
        if (res.success) watermarks.value = res.data || [];
    } catch {
        ElMessage.error('搜索失败');
    } finally {
        loadingWatermarks.value = false;
    }
}

function openEmbedDialog() {
    embedForm.license_id = null;
    embedForm.source_note = '';
    showEmbedDialog.value = true;
}

async function handleEmbedWatermark() {
    if (!embedFormRef.value) return;
    const valid = await embedFormRef.value.validate().catch(() => false);
    if (!valid) return;

    embedding.value = true;
    try {
        const sourceInfo = {};
        if (embedForm.source_note) sourceInfo.note = embedForm.source_note;
        const res = await watermarkApi.embedWatermark(embedForm.license_id, sourceInfo);
        if (res.success) {
            ElMessage.success('水印嵌入成功');
            showEmbedDialog.value = false;
            await fetchWatermarks();
            await fetchDashboard();
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '嵌入失败');
    } finally {
        embedding.value = false;
    }
}

function showWatermarkDetail(row) {
    watermarkDetail.value = row;
    showWatermarkDetailDialog.value = true;
}

async function handleRevokeWatermark(row) {
    try {
        await ElMessageBox.confirm(`确定吊销此水印吗？吊销后该 License 的水印将失效。`, '确认吊销', { type: 'warning' });
        const res = await watermarkApi.revokeWatermark(row.id);
        if (res.success) {
            ElMessage.success('水印已吊销');
            await fetchWatermarks();
            await fetchDashboard();
        }
    } catch (err) {
        if (err !== 'cancel') ElMessage.error('吊销失败');
    }
}

// ─── 防篡改事件 ───
const tamperEvents = ref([]);
const loadingEvents = ref(false);
const eventFilter = reactive({
    event_type: '', severity: '', is_resolved: '', license_key: '',
});
const showResolveDialog = ref(false);
const resolvingEvent = ref(null);
const resolveNote = ref('');
const resolving = ref(false);
const showEventDetailDialog = ref(false);
const eventDetail = ref(null);

async function fetchEvents() {
    loadingEvents.value = true;
    try {
        const params = {};
        if (eventFilter.event_type) params.event_type = eventFilter.event_type;
        if (eventFilter.severity) params.severity = eventFilter.severity;
        if (eventFilter.is_resolved !== '') params.is_resolved = eventFilter.is_resolved;
        if (eventFilter.license_key) params.license_key = eventFilter.license_key;
        const res = await watermarkApi.tamperEvents(params);
        if (res.success) tamperEvents.value = res.data || [];
    } catch {
        ElMessage.error('加载事件失败');
    } finally {
        loadingEvents.value = false;
    }
}

function resetEventFilter() {
    eventFilter.event_type = '';
    eventFilter.severity = '';
    eventFilter.is_resolved = '';
    eventFilter.license_key = '';
    fetchEvents();
}

function openResolveDialog(row) {
    resolvingEvent.value = row;
    resolveNote.value = '';
    showResolveDialog.value = true;
}

async function handleResolveEvent() {
    if (!resolveNote.value.trim()) {
        ElMessage.warning('请输入处理说明');
        return;
    }
    resolving.value = true;
    try {
        const res = await watermarkApi.resolveEvent(resolvingEvent.value.id, resolveNote.value);
        if (res.success) {
            ElMessage.success('事件已处理');
            showResolveDialog.value = false;
            await fetchEvents();
            await fetchDashboard();
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '处理失败');
    } finally {
        resolving.value = false;
    }
}

function showEventDetail(row) {
    eventDetail.value = row;
    showEventDetailDialog.value = true;
}

// ─── 防篡改策略 ───
const policies = ref([]);
const loadingPolicies = ref(false);
const showPolicyDialog = ref(false);
const editingPolicy = ref(null);
const savingPolicy = ref(false);
const policyForm = reactive({
    severity: 'medium', threshold: 5, cooldown_seconds: 300,
    is_active: true, description: '',
});

async function fetchPolicies() {
    loadingPolicies.value = true;
    try {
        const res = await watermarkApi.tamperPolicies();
        if (res.success) policies.value = res.data || [];
    } catch {
        ElMessage.error('加载策略失败');
    } finally {
        loadingPolicies.value = false;
    }
}

function openPolicyDialog(row) {
    editingPolicy.value = row;
    policyForm.severity = row.severity;
    policyForm.threshold = row.threshold;
    policyForm.cooldown_seconds = row.cooldown_seconds;
    policyForm.is_active = row.is_active;
    policyForm.description = row.description || '';
    showPolicyDialog.value = true;
}

async function handleSavePolicy() {
    savingPolicy.value = true;
    try {
        await watermarkApi.updatePolicy(editingPolicy.value.id, { ...policyForm });
        ElMessage.success('策略已更新');
        showPolicyDialog.value = false;
        await fetchPolicies();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '保存失败');
    } finally {
        savingPolicy.value = false;
    }
}

async function handleTogglePolicy(row, val) {
    try {
        await watermarkApi.updatePolicy(row.id, { is_active: val });
        ElMessage.success(val ? '策略已启用' : '策略已停用');
    } catch {
        ElMessage.error('更新失败');
        row.is_active = !val;
    }
}

// ─── 工具函数 ───
function eventTypeLabel(type) {
    const map = {
        signature: '签名失败', watermark: '水印不匹配',
        integrity: '完整性破坏', device: '设备异常',
        time_rollback: '时间回滚', tamper_detected: '篡改检测',
    };
    return map[type] || type;
}

function eventTypeTag(type) {
    const map = { signature: 'warning', watermark: 'danger', integrity: 'danger', device: 'warning', time_rollback: 'danger', tamper_detected: 'danger' };
    return map[type] || 'info';
}

function severityLabel(sev) {
    const map = { low: '低', medium: '中', high: '高', critical: '严重' };
    return map[sev] || sev;
}

function severityTag(sev) {
    const map = { low: 'success', medium: 'warning', high: 'danger', critical: 'danger' };
    return map[sev] || 'info';
}

function calcPct(count, total) {
    if (!total) return 0;
    return Math.round((count / total) * 100);
}

function formatTime(time) {
    if (!time) return '—';
    return new Date(time).toLocaleString('zh-CN');
}

async function refreshAll() {
    await Promise.all([
        fetchDashboard(),
        fetchWatermarks(),
        fetchEvents(),
        fetchPolicies(),
    ]);
}

onMounted(async () => {
    await fetchDashboard();
    await fetchWatermarks();
    await fetchEvents();
    await fetchPolicies();
});
</script>

<style scoped>
.watermark-page { padding: 20px; }

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

.mb-4 { margin-bottom: 16px; }

.stat-item { text-align: center; padding: 8px 0; }
.stat-label { font-size: 12px; color: var(--el-text-color-secondary); margin-bottom: 6px; }
.stat-value { font-size: 28px; font-weight: 700; color: var(--el-text-color-primary); }
.stat-value.primary { color: var(--el-color-primary); }
.stat-value.success { color: var(--el-color-success); }
.stat-value.danger { color: var(--el-color-danger); }
.stat-value.warning { color: var(--el-color-warning); }
.stat-value.info { color: var(--el-color-info); }

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-header span { font-weight: 600; font-size: 14px; }

.tab-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    gap: 12px;
    flex-wrap: wrap;
}
.toolbar-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.toolbar-right {
    display: flex;
    align-items: center;
    gap: 8px;
}

.pagination-wrap {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
}

.type-dist { display: flex; flex-direction: column; gap: 12px; }
.dist-row { display: flex; align-items: center; gap: 12px; }
.dist-label { min-width: 80px; font-size: 13px; white-space: nowrap; }
.dist-count { min-width: 40px; text-align: right; font-weight: 600; font-size: 13px; }

.detail-section { margin-top: 20px; }
.detail-section h4 { font-size: 14px; font-weight: 600; margin: 0 0 8px; }

.json-block {
    background: #f5f7fa;
    padding: 12px;
    border-radius: 6px;
    font-size: 12px;
    max-height: 250px;
    overflow: auto;
    white-space: pre-wrap;
    word-break: break-all;
}

.mono-text { font-family: 'Courier New', monospace; font-size: 12px; word-break: break-all; }
.empty-state { text-align: center; padding: 30px 0; color: var(--el-text-color-placeholder); font-size: 13px; }
.form-help { font-size: 12px; color: var(--el-text-color-secondary); margin-top: 4px; }

:deep(.el-card__body) { padding: 16px; }
</style>
