<template>
    <div class="maintenance-page">
        <div class="page-header">
            <h2>系统维护模式</h2>
            <div class="header-actions">
                <el-tag v-if="active" type="warning" size="large" effect="dark">
                    ⚠ 维护模式已启用
                </el-tag>
                <el-tag v-else type="success" size="large" effect="plain">
                    ✓ 正常运行
                </el-tag>
            </div>
        </div>

        <el-alert
            title="维护模式将返回 503 状态码，非白名单用户访问将看到维护页面。请确保已经配置好白名单后再启用。"
            type="info"
            show-icon
            :closable="false"
            style="margin-bottom: 16px;"
        />

        <el-card shadow="never">
            <template #header>
                <span>维护配置</span>
            </template>

            <el-form ref="formRef" :model="form" label-width="160px" v-loading="loading">
                <el-form-item label="维护标题">
                    <el-input v-model="form.title" placeholder="例如: 系统升级维护" style="max-width: 500px" />
                </el-form-item>
                <el-form-item label="维护公告">
                    <el-input v-model="form.message" type="textarea" :rows="4"
                        placeholder="向用户展示的维护说明" style="max-width: 500px" />
                </el-form-item>
                <el-form-item label="预计恢复时间">
                    <el-date-picker v-model="form.scheduled_end_at" type="datetime"
                        placeholder="选择预计恢复时间" style="width: 300px" />
                </el-form-item>
                <el-form-item label="自动关闭时间">
                    <el-date-picker v-model="form.auto_disable_at" type="datetime"
                        placeholder="到达此时间自动关闭维护模式" style="width: 300px" />
                </el-form-item>
                <el-form-item label="Retry-After (秒)">
                    <el-input-number v-model="form.retry_after" :min="5" :max="86400" />
                </el-form-item>
                <el-form-item label="IP 白名单">
                    <div class="whitelist-editor">
                        <div v-for="(ip, i) in form.whitelist_ips" :key="i" class="whitelist-item">
                            <el-input v-model="form.whitelist_ips[i]" placeholder="例如 192.168.1.1 或 *" />
                            <el-button @click="form.whitelist_ips.splice(i, 1)" type="danger" :icon="Delete" circle />
                        </div>
                        <el-button @click="form.whitelist_ips.push('')" type="primary" link>
                            + 添加 IP
                        </el-button>
                    </div>
                </el-form-item>
                <el-form-item label="路径白名单">
                    <div class="whitelist-editor">
                        <div v-for="(path, i) in form.whitelist_paths" :key="i" class="whitelist-item">
                            <el-input v-model="form.whitelist_paths[i]" placeholder="例如 api/health/* 或 api/maintenance/*" />
                            <el-button @click="form.whitelist_paths.splice(i, 1)" type="danger" :icon="Delete" circle />
                        </div>
                        <el-button @click="form.whitelist_paths.push('')" type="primary" link>
                            + 添加路径
                        </el-button>
                    </div>
                </el-form-item>
                <el-form-item>
                    <el-button v-if="active" type="danger" @click="handleDisable" :loading="toggling">
                        关闭维护模式
                    </el-button>
                    <el-button v-else type="warning" @click="handleEnable" :loading="toggling">
                        启用维护模式
                    </el-button>
                    <el-button @click="handleUpdate" :loading="saving" v-if="configId">
                        保存配置
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 历史记录 -->
        <el-card shadow="never" style="margin-top: 20px;">
            <template #header>
                <span>维护历史</span>
            </template>
            <el-table :data="history" v-loading="historyLoading" stripe>
                <el-table-column prop="title" label="标题" min-width="150" />
                <el-table-column prop="message" label="公告" min-width="200" show-overflow-tooltip />
                <el-table-column label="状态" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.is_enabled ? 'warning' : 'info'" size="small">
                            {{ row.is_enabled ? '启用' : '已关闭' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="scheduled_end_at" label="预计恢复" width="170" />
                <el-table-column prop="auto_disable_at" label="自动关闭" width="170" />
                <el-table-column prop="created_at" label="创建时间" width="170" />
            </el-table>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Delete } from '@element-plus/icons-vue';
import {
    getMaintenanceStatus, enableMaintenance, disableMaintenance,
    updateMaintenanceConfig, getMaintenanceHistory,
} from '@/api/maintenance';
import { ElMessage, ElMessageBox } from 'element-plus';

const loading = ref(false);
const toggling = ref(false);
const saving = ref(false);
const active = ref(false);
const configId = ref(null);
const history = ref([]);
const historyLoading = ref(false);
const formRef = ref(null);

const form = ref({
    title: '',
    message: '',
    scheduled_end_at: null,
    auto_disable_at: null,
    retry_after: 60,
    whitelist_ips: [''],
    whitelist_paths: ['api/health/*', 'api/maintenance/*'],
});

async function fetchStatus() {
    loading.value = true;
    try {
        const res = await getMaintenanceStatus();
        active.value = res.data?.is_active || false;
        const config = res.data?.config;
        if (config) {
            configId.value = config.id;
            form.value.title = config.title || '';
            form.value.message = config.message || '';
            form.value.scheduled_end_at = config.scheduled_end_at || null;
            form.value.auto_disable_at = config.auto_disable_at || null;
            form.value.retry_after = config.retry_after ?? 60;
            form.value.whitelist_ips = config.whitelist_ips?.length ? [...config.whitelist_ips] : [''];
            form.value.whitelist_paths = config.whitelist_paths?.length ? [...config.whitelist_paths] : ['api/health/*', 'api/maintenance/*'];
        }
    } catch (e) {
        ElMessage.error('获取维护状态失败');
    } finally {
        loading.value = false;
    }
}

async function fetchHistory() {
    historyLoading.value = true;
    try {
        const res = await getMaintenanceHistory();
        history.value = res.data?.data || [];
    } catch (e) {
        // ignore
    } finally {
        historyLoading.value = false;
    }
}

async function handleEnable() {
    try {
        await ElMessageBox.confirm('确定启用维护模式？所有非白名单用户将无法访问系统。', '确认启用', {
            confirmButtonText: '启用',
            cancelButtonText: '取消',
            type: 'warning',
        });
    } catch {
        return;
    }

    toggling.value = true;
    try {
        const payload = {
            title: form.value.title || undefined,
            message: form.value.message || undefined,
            scheduled_end_at: form.value.scheduled_end_at || undefined,
            auto_disable_at: form.value.auto_disable_at || undefined,
            retry_after: form.value.retry_after,
            whitelist_ips: form.value.whitelist_ips.filter(i => i.trim()),
            whitelist_paths: form.value.whitelist_paths.filter(p => p.trim()),
        };
        await enableMaintenance(payload);
        ElMessage.success('维护模式已启用');
        await fetchStatus();
        await fetchHistory();
    } catch (e) {
        ElMessage.error('启用维护模式失败');
    } finally {
        toggling.value = false;
    }
}

async function handleDisable() {
    try {
        await ElMessageBox.confirm('确定关闭维护模式？所有用户将恢复正常访问。', '确认关闭', {
            confirmButtonText: '关闭',
            cancelButtonText: '取消',
            type: 'info',
        });
    } catch {
        return;
    }

    toggling.value = true;
    try {
        await disableMaintenance();
        ElMessage.success('维护模式已关闭');
        await fetchStatus();
        await fetchHistory();
    } catch (e) {
        ElMessage.error('关闭维护模式失败');
    } finally {
        toggling.value = false;
    }
}

async function handleUpdate() {
    if (!configId.value) return;

    saving.value = true;
    try {
        await updateMaintenanceConfig(configId.value, {
            title: form.value.title || null,
            message: form.value.message || null,
            scheduled_end_at: form.value.scheduled_end_at || null,
            auto_disable_at: form.value.auto_disable_at || null,
            retry_after: form.value.retry_after,
            whitelist_ips: form.value.whitelist_ips.filter(i => i.trim()),
            whitelist_paths: form.value.whitelist_paths.filter(p => p.trim()),
        });
        ElMessage.success('配置已保存');
        await fetchStatus();
    } catch (e) {
        ElMessage.error('保存失败');
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    fetchStatus();
    fetchHistory();
});
</script>

<style scoped>
.maintenance-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.header-actions { display: flex; gap: 8px; align-items: center; }
.whitelist-editor { width: 100%; }
.whitelist-item { display: flex; gap: 8px; margin-bottom: 8px; }
.whitelist-item .el-input { flex: 1; }
</style>
