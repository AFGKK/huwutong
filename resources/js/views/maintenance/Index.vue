<template>
    <div class="maintenance-page">
        <div class="page-header">
            <h2>{{ t('maintenance_page.title') }}</h2>
            <div class="header-actions">
                <el-tag v-if="active" type="warning" size="large" effect="dark">
                    {{ t('maintenance_page.status_active') }}
                </el-tag>
                <el-tag v-else type="success" size="large" effect="plain">
                    {{ t('maintenance_page.status_normal') }}
                </el-tag>
            </div>
        </div>

        <el-alert
            :title="t('maintenance_page.info_alert')"
            type="info"
            show-icon
            :closable="false"
            style="margin-bottom: 16px;"
        />

        <el-card shadow="never">
            <template #header>
                <span>{{ t('maintenance_page.config_title') }}</span>
            </template>

            <el-form ref="formRef" :model="form" label-width="160px" v-loading="loading">
                <el-form-item :label="t('maintenance_page.label_title')">
                    <el-input v-model="form.title" :placeholder="t('maintenance_page.title_ph')" style="max-width: 500px" />
                </el-form-item>
                <el-form-item :label="t('maintenance_page.label_message')">
                    <el-input v-model="form.message" type="textarea" :rows="4"
                        :placeholder="t('maintenance_page.message_ph')" style="max-width: 500px" />
                </el-form-item>
                <el-form-item :label="t('maintenance_page.label_scheduled_end')">
                    <el-date-picker v-model="form.scheduled_end_at" type="datetime"
                        :placeholder="t('maintenance_page.scheduled_end_ph')" style="width: 300px" />
                </el-form-item>
                <el-form-item :label="t('maintenance_page.label_auto_disable')">
                    <el-date-picker v-model="form.auto_disable_at" type="datetime"
                        :placeholder="t('maintenance_page.auto_disable_ph')" style="width: 300px" />
                </el-form-item>
                <el-form-item :label="t('maintenance_page.label_retry_after')">
                    <el-input-number v-model="form.retry_after" :min="5" :max="86400" />
                </el-form-item>
                <el-form-item :label="t('maintenance_page.label_ip_whitelist')">
                    <div class="whitelist-editor">
                        <div v-for="(ip, i) in form.whitelist_ips" :key="i" class="whitelist-item">
                            <el-input v-model="form.whitelist_ips[i]" :placeholder="t('maintenance_page.ip_ph')" />
                            <el-button @click="form.whitelist_ips.splice(i, 1)" type="danger" :icon="Delete" circle />
                        </div>
                        <el-button @click="form.whitelist_ips.push('')" type="primary" link>
                            + {{ t('maintenance_page.add_ip') }}
                        </el-button>
                    </div>
                </el-form-item>
                <el-form-item :label="t('maintenance_page.label_path_whitelist')">
                    <div class="whitelist-editor">
                        <div v-for="(path, i) in form.whitelist_paths" :key="i" class="whitelist-item">
                            <el-input v-model="form.whitelist_paths[i]" :placeholder="t('maintenance_page.path_ph')" />
                            <el-button @click="form.whitelist_paths.splice(i, 1)" type="danger" :icon="Delete" circle />
                        </div>
                        <el-button @click="form.whitelist_paths.push('')" type="primary" link>
                            + {{ t('maintenance_page.add_path') }}
                        </el-button>
                    </div>
                </el-form-item>
                <el-form-item>
                    <el-button v-if="active" type="danger" @click="handleDisable" :loading="toggling">
                        {{ t('maintenance_page.btn_disable') }}
                    </el-button>
                    <el-button v-else type="warning" @click="handleEnable" :loading="toggling">
                        {{ t('maintenance_page.btn_enable') }}
                    </el-button>
                    <el-button @click="handleUpdate" :loading="saving" v-if="configId">
                        {{ t('maintenance_page.save_config') }}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card shadow="never" style="margin-top: 20px;">
            <template #header>
                <span>{{ t('maintenance_page.history_title') }}</span>
            </template>
            <el-table :data="history" v-loading="historyLoading" stripe>
                <el-table-column prop="title" :label="t('maintenance_page.col_title')" min-width="150" />
                <el-table-column prop="message" :label="t('maintenance_page.col_message')" min-width="200" show-overflow-tooltip />
                <el-table-column :label="t('maintenance_page.col_status')" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.is_enabled ? 'warning' : 'info'" size="small">
                            {{ row.is_enabled ? t('maintenance_page.status_enabled') : t('maintenance_page.status_disabled') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="scheduled_end_at" :label="t('maintenance_page.col_scheduled_end')" width="170" />
                <el-table-column prop="auto_disable_at" :label="t('maintenance_page.col_auto_disable')" width="170" />
                <el-table-column prop="created_at" :label="t('maintenance_page.col_created_at')" width="170" />
            </el-table>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Delete } from '@element-plus/icons-vue';
import {
    getMaintenanceStatus, enableMaintenance, disableMaintenance,
    updateMaintenanceConfig, getMaintenanceHistory,
} from '@/api/maintenance';
import { ElMessage, ElMessageBox } from 'element-plus';

const { t } = useI18n();

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
        ElMessage.error(t('maintenance_page.msg_fetch_failed'));
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
        await ElMessageBox.confirm(t('maintenance_page.enable_confirm'), t('maintenance_page.enable_confirm_title'), {
            confirmButtonText: t('maintenance_page.btn_enable'),
            cancelButtonText: t('actions.cancel'),
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
        ElMessage.success(t('maintenance_page.msg_enabled'));
        await fetchStatus();
        await fetchHistory();
    } catch (e) {
        ElMessage.error(t('maintenance_page.msg_enable_failed'));
    } finally {
        toggling.value = false;
    }
}

async function handleDisable() {
    try {
        await ElMessageBox.confirm(t('maintenance_page.disable_confirm'), t('maintenance_page.disable_confirm_title'), {
            confirmButtonText: t('maintenance_page.confirm_disable'),
            cancelButtonText: t('actions.cancel'),
            type: 'info',
        });
    } catch {
        return;
    }

    toggling.value = true;
    try {
        await disableMaintenance();
        ElMessage.success(t('maintenance_page.msg_disabled'));
        await fetchStatus();
        await fetchHistory();
    } catch (e) {
        ElMessage.error(t('maintenance_page.msg_disable_failed'));
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
        ElMessage.success(t('maintenance_page.msg_saved'));
        await fetchStatus();
    } catch (e) {
        ElMessage.error(t('maintenance_page.msg_save_failed'));
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
