<template>
    <div class="vm-detection-page">
        <div class="page-header">
            <h2>{{ t(`${P}.title`) }}</h2>
            <el-button type="primary" @click="showConfig = true">
                <el-icon><Setting /></el-icon> {{ t(`${P}.config_btn`) }}
            </el-button>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #f56c6c">{{ stats.total_detected || 0 }}</div>
                        <div class="stat-label">{{ t(`${P}.stats.detected`) }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #e6a23c">{{ stats.total_checked || 0 }}</div>
                        <div class="stat-label">{{ t(`${P}.stats.checked`) }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #f56c6c">{{ stats.blocked_count || 0 }}</div>
                        <div class="stat-label">{{ t(`${P}.stats.blocked`) }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #0f172a">{{ stats.recent_detections_7d || 0 }}</div>
                        <div class="stat-label">{{ t(`${P}.stats.recent_7d`) }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 策略信息 -->
        <el-card shadow="never" class="mb-4">
            <template #header>
                <span>{{ t(`${P}.strategy.title`) }}</span>
            </template>
            <el-tag v-if="stats.strategy === 'block'" type="danger" size="large">{{ t(`${P}.strategy.block`) }}</el-tag>
            <el-tag v-else-if="stats.strategy === 'reduce_trust'" type="warning" size="large">{{ t(`${P}.strategy.reduce_trust`) }}</el-tag>
            <el-tag v-else type="info" size="large">{{ t(`${P}.strategy.log_only`) }}</el-tag>
            <el-tag :type="stats.enabled ? 'success' : 'info'" size="large" class="ml-2">{{ stats.enabled ? t(`${P}.enabled`) : t(`${P}.disabled`) }}</el-tag>
        </el-card>

        <!-- 虚拟环境类型分布 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="12">
                <el-card shadow="never">
                    <template #header><span>{{ t(`${P}.sections.type_distribution`) }}</span></template>
                    <el-table :data="typeStatsList" stripe v-if="typeStatsList.length > 0">
                        <el-table-column prop="type" :label="t(`${P}.cols.type`)" min-width="120" />
                        <el-table-column prop="count" :label="t(`${P}.cols.count`)" width="100" />
                    </el-table>
                    <el-empty v-else :description="t('messages.no_data')" />
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="never">
                    <template #header><span>{{ t(`${P}.sections.checks_status`) }}</span></template>
                    <div v-if="vmConfig.checks" class="check-grid">
                        <div v-for="(enabled, key) in vmConfig.checks" :key="key" class="check-item">
                            <el-tag :type="enabled ? 'success' : 'info'" size="small">
                                {{ checkLabel(key) }}: {{ enabled ? t(`${P}.enabled`) : t(`${P}.disabled`) }}
                            </el-tag>
                        </div>
                    </div>
                    <el-empty v-else :description="t(`${P}.empty.no_config`)" />
                </el-card>
            </el-col>
        </el-row>

        <!-- 设备列表 -->
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>{{ t(`${P}.sections.devices`) }}</span>
                    <div>
                        <el-select v-model="filterType" clearable :placeholder="t(`${P}.filter_type`)" style="width:150px" size="small" @change="loadDevices(1)">
                            <el-option v-for="(count, type) in stats.type_stats || {}" :key="type" :label="typeLabel(type)" :value="type" />
                        </el-select>
                        <el-input v-model="searchText" :placeholder="t(`${P}.search_devices`)" clearable style="width:200px;margin-left:8px" size="small" @clear="loadDevices(1)" @keyup.enter="loadDevices(1)" />
                    </div>
                </div>
            </template>
            <el-table :data="devices" stripe v-loading="loading">
                <el-table-column type="index" label="#" width="50" />
                <el-table-column prop="name" :label="t(`${P}.cols.name`)" min-width="160" show-overflow-tooltip />
                <el-table-column prop="fingerprint" :label="t(`${D}.col_fingerprint`)" width="180" show-overflow-tooltip />
                <el-table-column prop="vm_info" :label="t(`${P}.cols.vm_type`)" width="140">
                    <template #default="{ row }">
                        <el-tag v-if="row.vm_info?.type" type="warning" size="small">
                            {{ typeLabel(row.vm_info.type) }}
                        </el-tag>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.confidence`)" width="100">
                    <template #default="{ row }">
                        <el-tag v-if="row.vm_info?.confidence" :type="row.vm_info.confidence > 70 ? 'danger' : 'warning'" size="small">
                            {{ row.vm_info.confidence }}%
                        </el-tag>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="trust_score" :label="t(`${D}.col_trust_score`)" width="80">
                    <template #default="{ row }">
                        <el-tag :type="(row.trust_score || 0) > 50 ? 'success' : 'danger'" size="small">{{ row.trust_score || 0 }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="platform" :label="t(`${D}.col_platform`)" width="100" />
                <el-table-column prop="updated_at" :label="t(`${P}.cols.detected_at`)" width="170" />
                <el-table-column :label="t(`${D}.col_actions`)" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button text type="primary" size="small" @click="handleDetect(row)">{{ t(`${P}.actions.redetect`) }}</el-button>
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
        <el-dialog v-model="showConfig" :title="t(`${P}.config.title`)" width="500px">
            <el-form :model="configForm" label-width="140px">
                <el-form-item :label="t(`${P}.config.enable`)">
                    <el-switch v-model="configForm.enabled" />
                </el-form-item>
                <el-form-item :label="t(`${P}.config.strategy`)">
                    <el-select v-model="configForm.strategy" style="width:100%">
                        <el-option :label="t(`${P}.strategy_options.block`)" value="block" />
                        <el-option :label="t(`${P}.strategy_options.reduce_trust`)" value="reduce_trust" />
                        <el-option :label="t(`${P}.strategy_options.log_only`)" value="log_only" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t(`${P}.config.vm_trust_score`)">
                    <el-input-number v-model="configForm.vm_trust_score" :min="0" :max="100" style="width:200px" />
                </el-form-item>
                <el-form-item :label="t(`${P}.config.detection_threshold`)">
                    <el-input-number v-model="configForm.detection_threshold" :min="1" :max="10" style="width:200px" />
                    <span class="text-muted ml-2">{{ t(`${P}.config.threshold_hint`) }}</span>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showConfig = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="savingConfig" @click="saveConfig">{{ t(`${P}.config.save`) }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Setting } from '@element-plus/icons-vue';
import { getVmDashboard, getVmDevices, detectVmDevice, getVmConfig, updateVmConfig } from '@/api/vmDetection';

const P = 'vm_detection_page';
const D = 'devices_page';
const { t } = useI18n();

const CHECK_KEYS = ['docker', 'vmware', 'virtualbox', 'kvm', 'hyper_v', 'xen', 'qemu', 'parallels', 'wsl', 'android_emulator', 'ios_simulator', 'container'];
const TYPE_KEYS = ['vmware', 'virtualbox', 'qemu', 'kvm', 'hyper-v', 'xen', 'parallels', 'docker', 'wsl', 'android_emulator', 'ios_simulator', 'container', 'unknown'];

const checkLabelsMap = computed(() => {
    const map = {};
    for (const key of CHECK_KEYS) {
        map[key] = t(`${P}.vm_types.${key}`);
    }
    return map;
});

const typeLabelsMap = computed(() => {
    const map = {};
    for (const key of TYPE_KEYS) {
        if (key === 'unknown') {
            map[key] = t(`${D}.unknown`);
        } else if (key === 'hyper-v') {
            map[key] = t(`${P}.vm_types.hyper_v`);
        } else {
            map[key] = t(`${P}.vm_types.${key}`);
        }
    }
    return map;
});

function checkLabel(key) {
    return checkLabelsMap.value[key] || key;
}

function typeLabel(type) {
    return typeLabelsMap.value[type] || type;
}

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

const typeStatsList = computed(() => {
    const ts = stats.value.type_stats || {};
    return Object.entries(ts).map(([type, count]) => ({
        type: typeLabel(type),
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
            ElMessage.success(t(`${P}.messages.detect_success`));
            loadDevices(page.value);
            loadDashboard();
        }
    } catch (e) {
        ElMessage.error(t(`${P}.messages.detect_failed`));
    }
};

const saveConfig = async () => {
    savingConfig.value = true;
    try {
        const res = await updateVmConfig(configForm);
        if (res.data.success) {
            ElMessage.success(t(`${P}.messages.config_updated`));
            showConfig.value = false;
            loadDashboard();
            loadConfig();
        }
    } catch (e) {
        ElMessage.error(t(`${P}.messages.save_failed`));
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
