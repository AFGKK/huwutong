<template>
    <div class="vm-detection-page">
        <div class="page-header">
            <h2>虚拟环境/模拟器检测</h2>
            <el-button type="primary" @click="showConfig = true">
                <el-icon><Setting /></el-icon> 检测配置
            </el-button>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #f56c6c">{{ stats.total_detected || 0 }}</div>
                        <div class="stat-label">已检测到虚拟环境</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #e6a23c">{{ stats.total_checked || 0 }}</div>
                        <div class="stat-label">已检测设备总数</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #f56c6c">{{ stats.blocked_count || 0 }}</div>
                        <div class="stat-label">已阻止数量</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #409eff">{{ stats.recent_detections_7d || 0 }}</div>
                        <div class="stat-label">近7天新增</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 策略信息 -->
        <el-card shadow="never" class="mb-4">
            <template #header>
                <span>当前检测策略</span>
            </template>
            <el-tag v-if="stats.strategy === 'block'" type="danger" size="large">阻止模式：检测到虚拟环境禁止激活</el-tag>
            <el-tag v-else-if="stats.strategy === 'reduce_trust'" type="warning" size="large">降信任模式：检测到虚拟环境降低信任分</el-tag>
            <el-tag v-else type="info" size="large">仅记录模式：仅记录不干预</el-tag>
            <el-tag :type="stats.enabled ? 'success' : 'info'" size="large" class="ml-2">{{ stats.enabled ? '已启用' : '已禁用' }}</el-tag>
        </el-card>

        <!-- 虚拟环境类型分布 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="12">
                <el-card shadow="never">
                    <template #header><span>虚拟环境类型分布</span></template>
                    <el-table :data="typeStatsList" stripe v-if="typeStatsList.length > 0">
                        <el-table-column prop="type" label="类型" min-width="120" />
                        <el-table-column prop="count" label="数量" width="100" />
                    </el-table>
                    <el-empty v-else description="暂无数据" />
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="never">
                    <template #header><span>检测项启用状态</span></template>
                    <div v-if="vmConfig.checks" class="check-grid">
                        <div v-for="(enabled, key) in vmConfig.checks" :key="key" class="check-item">
                            <el-tag :type="enabled ? 'success' : 'info'" size="small">
                                {{ checkLabels[key] || key }}: {{ enabled ? '已启用' : '已禁用' }}
                            </el-tag>
                        </div>
                    </div>
                    <el-empty v-else description="暂无配置" />
                </el-card>
            </el-col>
        </el-row>

        <!-- 设备列表 -->
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>已检测到虚拟环境的设备</span>
                    <div>
                        <el-select v-model="filterType" clearable placeholder="筛选类型" style="width:150px" size="small" @change="loadDevices(1)">
                            <el-option v-for="(count, type) in stats.type_stats || {}" :key="type" :label="typeLabels[type] || type" :value="type" />
                        </el-select>
                        <el-input v-model="searchText" placeholder="搜索设备" clearable style="width:200px;margin-left:8px" size="small" @clear="loadDevices(1)" @keyup.enter="loadDevices(1)" />
                    </div>
                </div>
            </template>
            <el-table :data="devices" stripe v-loading="loading">
                <el-table-column type="index" label="#" width="50" />
                <el-table-column prop="name" label="设备名称" min-width="160" show-overflow-tooltip />
                <el-table-column prop="fingerprint" label="设备指纹" width="180" show-overflow-tooltip />
                <el-table-column prop="vm_info" label="虚拟环境类型" width="140">
                    <template #default="{ row }">
                        <el-tag v-if="row.vm_info?.type" type="warning" size="small">
                            {{ typeLabels[row.vm_info.type] || row.vm_info.type }}
                        </el-tag>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column label="置信度" width="100">
                    <template #default="{ row }">
                        <el-tag v-if="row.vm_info?.confidence" :type="row.vm_info.confidence > 70 ? 'danger' : 'warning'" size="small">
                            {{ row.vm_info.confidence }}%
                        </el-tag>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="trust_score" label="信任分" width="80">
                    <template #default="{ row }">
                        <el-tag :type="(row.trust_score || 0) > 50 ? 'success' : 'danger'" size="small">{{ row.trust_score || 0 }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="platform" label="平台" width="100" />
                <el-table-column prop="updated_at" label="检测时间" width="170" />
                <el-table-column label="操作" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button text type="primary" size="small" @click="handleDetect(row)">重新检测</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="page"
                    :page-size="perPage"
                    :total="total"
                    layout="total, prev, pager, next"
                    @current-change="loadDevices" />
            </div>
        </el-card>

        <!-- 配置弹窗 -->
        <el-dialog v-model="showConfig" title="检测配置" width="500px">
            <el-form :model="configForm" label-width="140px">
                <el-form-item label="启用检测">
                    <el-switch v-model="configForm.enabled" />
                </el-form-item>
                <el-form-item label="检测策略">
                    <el-select v-model="configForm.strategy" style="width:100%">
                        <el-option label="阻止模式：禁止激活" value="block" />
                        <el-option label="降信任模式：降低信任分" value="reduce_trust" />
                        <el-option label="仅记录模式：不干预" value="log_only" />
                    </el-select>
                </el-form-item>
                <el-form-item label="虚拟环境信任分">
                    <el-input-number v-model="configForm.vm_trust_score" :min="0" :max="100" style="width:200px" />
                </el-form-item>
                <el-form-item label="检测阈值">
                    <el-input-number v-model="configForm.detection_threshold" :min="1" :max="10" style="width:200px" />
                    <span class="text-muted ml-2">命中项数达到此值判定为虚拟环境</span>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showConfig = false">取消</el-button>
                <el-button type="primary" :loading="savingConfig" @click="saveConfig">保存配置</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Setting } from '@element-plus/icons-vue';
import { getVmDashboard, getVmDevices, detectVmDevice, getVmConfig, updateVmConfig } from '@/api/vmDetection';

const loading = ref(false);
const devices = ref([]);
const page = ref(1);
const perPage = ref(15);
const total = ref(0);
const stats = ref({});
const filterType = ref('');
const searchText = ref('');
const showConfig = ref(false);
const savingConfig = ref(false);
const vmConfig = ref({});
const configForm = reactive({
    enabled: true,
    strategy: 'reduce_trust',
    vm_trust_score: 20,
    detection_threshold: 2,
});

const checkLabels = {
    docker: 'Docker',
    vmware: 'VMware',
    virtualbox: 'VirtualBox',
    kvm: 'KVM',
    hyper_v: 'Hyper-V',
    xen: 'Xen',
    qemu: 'QEMU',
    parallels: 'Parallels',
    wsl: 'WSL',
    android_emulator: 'Android模拟器',
    ios_simulator: 'iOS模拟器',
    container: '容器',
};

const typeLabels = {
    vmware: 'VMware',
    virtualbox: 'VirtualBox',
    qemu: 'QEMU/KVM',
    kvm: 'KVM',
    'hyper-v': 'Hyper-V',
    xen: 'Xen',
    parallels: 'Parallels',
    docker: 'Docker',
    wsl: 'WSL',
    android_emulator: 'Android模拟器',
    ios_simulator: 'iOS模拟器',
    container: '容器',
    unknown: '未知',
};

const typeStatsList = computed(() => {
    const ts = stats.value.type_stats || {};
    return Object.entries(ts).map(([type, count]) => ({
        type: typeLabels[type] || type,
        count,
    }));
});

const loadDashboard = async () => {
    try {
        const res = await getVmDashboard();
        if (res.data.success) stats.value = res.data.data;
    } catch (e) { /* ignore */ }
};

const loadConfig = async () => {
    try {
        const res = await getVmConfig();
        if (res.data.success) {
            vmConfig.value = res.data.data;
            configForm.enabled = res.data.data.enabled ?? true;
            configForm.strategy = res.data.data.strategy || 'reduce_trust';
            configForm.vm_trust_score = res.data.data.vm_trust_score ?? 20;
            configForm.detection_threshold = res.data.data.detection_threshold ?? 2;
        }
    } catch (e) { /* ignore */ }
};

const loadDevices = async (p = 1) => {
    page.value = p;
    loading.value = true;
    try {
        const params = { page: page.value, per_page: perPage.value };
        if (filterType.value) params.vm_type = filterType.value;
        if (searchText.value) params.search = searchText.value;

        const res = await getVmDevices(params);
        if (res.data.success) {
            devices.value = res.data.data.items || [];
            total.value = res.data.data.total || 0;
        }
    } catch (e) { /* ignore */ }
    finally { loading.value = false; }
};

const handleDetect = async (row) => {
    try {
        const res = await detectVmDevice(row.id);
        if (res.data.success) {
            ElMessage.success('检测完成');
            loadDevices(page.value);
            loadDashboard();
        }
    } catch (e) {
        ElMessage.error('检测失败');
    }
};

const saveConfig = async () => {
    savingConfig.value = true;
    try {
        const res = await updateVmConfig(configForm);
        if (res.data.success) {
            ElMessage.success('配置已更新');
            showConfig.value = false;
            loadDashboard();
            loadConfig();
        }
    } catch (e) {
        ElMessage.error('保存失败');
    }
    finally { savingConfig.value = false; }
};

onMounted(() => {
    loadDashboard();
    loadConfig();
    loadDevices();
});
</script>

<style scoped>
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }
.ml-2 { margin-left: 8px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.check-grid { display: flex; flex-wrap: wrap; gap: 8px; }
.check-item { display: inline-block; }
.text-muted { color: #909399; font-size: 12px; }
</style>
