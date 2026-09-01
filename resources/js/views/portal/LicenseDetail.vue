<template>
    <div class="license-detail" v-loading="loading">
        <el-page-header @back="$router.push('/portal/licenses')" :content="$t('portal.license_detail')" />

        <!-- 状态信息卡 -->
        <el-card class="mt-4" shadow="never">
            <div class="status-bar">
                <div class="status-section">
                    <div class="status-label">{{ $t('portal.current_status') }}</div>
                    <el-tag :type="statusType(license.status)" size="large" effect="dark">
                        {{ statusLabel(license.status) }}
                    </el-tag>
                </div>
                <div class="status-section">
                    <div class="status-label">License Key</div>
                    <code class="license-key">{{ license.license_key }}</code>
                    <el-button text size="small" @click="copyKey">{{ $t('portal.copy') }}</el-button>
                    <el-button text size="small" type="primary" @click="showQrCode">
                        {{ $t('portal.scan_activate') }}
                    </el-button>
                </div>
                <div class="status-section">
                    <div class="status-label">{{ $t('portal.type') }}</div>
                    <el-tag v-if="license.type === 'trial'" type="warning" size="small">{{ $t('portal.type_trial') }}</el-tag>
                    <el-tag v-else-if="license.type === 'enterprise'" type="success" size="small">{{ $t('portal.type_enterprise') }}</el-tag>
                    <el-tag v-else-if="license.type === 'development'" size="small">{{ $t('portal.type_development') }}</el-tag>
                    <span v-else>{{ $t('portal.type_standard') }}</span>
                </div>
            </div>
        </el-card>

        <el-row :gutter="16" class="mt-4">
            <!-- 基本信息 -->
            <el-col :span="16">
                <el-card>
                    <template #header>
                        <span>{{ $t('portal.basic_info') }}</span>
                    </template>
                    <el-descriptions :column="2" border>
                        <el-descriptions-item :label="$t('portal.product')" :span="1">{{ license.product?.name || '-' }}</el-descriptions-item>
                        <el-descriptions-item :label="$t('portal.seats')" :span="1">{{ license.seats || 1 }}</el-descriptions-item>
                        <el-descriptions-item :label="$t('portal.device_limit')" :span="1">{{ license.max_devices }}</el-descriptions-item>
                        <el-descriptions-item :label="$t('portal.activated_devices')" :span="1">{{ deviceCount }}</el-descriptions-item>
                        <el-descriptions-item :label="$t('portal.created_at')" :span="1">{{ license.created_at }}</el-descriptions-item>
                        <el-descriptions-item :label="$t('portal.activated_at')" :span="1">{{ license.activated_at || $t('portal.never_activated') }}</el-descriptions-item>
                        <el-descriptions-item :label="$t('portal.expires_at')" :span="2">
                            <span v-if="license.expires_at" :class="{ 'expiring-text': isExpiring(license.expires_at) }">
                                {{ license.expires_at }}
                            </span>
                            <span v-else>{{ $t('portal.lifetime') }}</span>
                        </el-descriptions-item>
                    </el-descriptions>

                    <!-- 元数据 -->
                    <div v-if="license.metadata" class="mt-4">
                        <el-divider />
                        <h4 class="section-title">{{ $t('portal.custom_metadata') }}</h4>
                        <pre class="metadata-json">{{ formatJson(license.metadata) }}</pre>
                    </div>

                    <!-- 交付物 -->
                    <div v-if="deliverables.length > 0" class="mt-4">
                        <el-divider />
                        <h4 class="section-title">{{ $t('portal.deliverables_title') }}</h4>
                        <div class="deliverables-grid">
                            <div v-for="(d, idx) in deliverables" :key="idx" class="portal-deliverable-card">
                                <div class="dlv-header">
                                    <span class="dlv-icon">{{ typeIcon(d.type) }}</span>
                                    <el-tag size="small" class="dlv-category">{{ categoryLabel(d.category) }}</el-tag>
                                </div>
                                <div class="dlv-name">{{ d.name }}</div>
                                <div v-if="d.description" class="dlv-desc">{{ d.description }}</div>

                                <div v-if="d.type === 'file' && d.file_url" class="dlv-action">
                                    <el-button size="small" type="primary" @click="openUrl(d.file_url)">
                                        <el-icon><Download /></el-icon> {{ $t('portal.download') }}
                                    </el-button>
                                    <span v-if="d.file_size" class="dlv-size">{{ formatFileSize(d.file_size) }}</span>
                                </div>
                                <div v-else-if="d.type === 'link' && d.file_url" class="dlv-action">
                                    <el-button size="small" type="primary" link @click="openUrl(d.file_url)">
                                        <el-icon><Link /></el-icon> {{ $t('portal.open_link') }}
                                    </el-button>
                                </div>
                                <div v-else-if="d.type === 'text' && d.content" class="dlv-action">
                                    <el-button size="small" type="primary" link @click="copyPortalText(d.content)">
                                        <el-icon><CopyDocument /></el-icon> {{ $t('portal.copy_content') }}
                                    </el-button>
                                </div>
                            </div>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <!-- 右侧：设备概览 & 操作 -->
            <el-col :span="8">
                <!-- 设备使用情况 -->
                <el-card class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>{{ $t('portal.device_usage') }}</span>
                            <el-link type="primary" :underline="'never'" @click="$router.push('/portal/devices')">{{ $t('portal.manage') }}</el-link>
                        </div>
                    </template>
                    <div class="device-usage">
                        <el-progress
                            :percentage="devicePercent"
                            :status="devicePercent >= 80 ? 'exception' : devicePercent >= 60 ? 'warning' : 'success'"
                            :stroke-width="20"
                            :text-inside="true"
                        >
                            {{ deviceCount }} / {{ license.max_devices }}
                        </el-progress>
                        <p class="usage-hint">
                            <template v-if="devicePercent >= 80">
                                {{ $t('portal.device_near_limit') }}
                            </template>
                            <template v-else>
                                {{ $t('portal.device_slots_left', { n: license.max_devices - deviceCount }) }}
                            </template>
                        </p>
                    </div>
                </el-card>

                <!-- 关联的设备 -->
                <el-card>
                    <template #header>
                        <span>{{ $t('portal.bound_devices') }}</span>
                    </template>
                    <div v-if="devices.length">
                        <div v-for="dev in devices" :key="dev.id" class="device-item">
                            <div class="device-info">
                                <el-icon><Monitor /></el-icon>
                                <div>
                                    <div class="device-name">{{ dev.name || dev.hostname || $t('portal.unknown_device') }}</div>
                                    <div class="device-fingerprint">
                                        <code>{{ dev.fingerprint?.substring(0, 16) }}...</code>
                                    </div>
                                </div>
                            </div>
                            <el-button
                                text
                                type="danger"
                                size="small"
                                @click="handleDeactivate(dev)"
                                :loading="deactivatingId === dev.id"
                            >
                                {{ $t('portal.unbind') }}
                            </el-button>
                        </div>
                    </div>
                    <el-empty v-else :description="$t('portal.no_bound_devices')" :image-size="60" />
                </el-card>

                <!-- 自助操作 -->
                <el-card class="mt-4">
                    <template #header>
                        <span>{{ $t('portal.self_service') }}</span>
                    </template>
                    <div class="self-service-actions">
                        <el-button
                            v-if="canRenew"
                            type="primary"
                            class="action-btn"
                            @click="handleRenew"
                            :icon="Refresh"
                        >
                            {{ $t('portal.renew_license') }}
                        </el-button>
                        <el-button
                            v-if="canUpgrade"
                            type="warning"
                            class="action-btn"
                            @click="handleUpgrade"
                            :icon="ArrowUp"
                        >
                            {{ $t('portal.upgrade_plan') }}
                        </el-button>
                        <el-button
                            v-if="canRefund"
                            type="danger"
                            class="action-btn"
                            plain
                            @click="handleRequestRefund"
                            :icon="Money"
                        >
                            {{ $t('portal.request_refund') }}
                        </el-button>
                        <p class="action-hint" v-if="!canRenew && !canUpgrade && !canRefund">
                            {{ $t('portal.self_service_unavailable') }}
                        </p>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 扫码激活对话框 -->
        <el-dialog v-model="qrVisible" :title="$t('portal.scan_activate_title')" width="380px" top="15vh" :close-on-click-modal="true" @closed="qrDataUrl = ''">
            <div v-loading="qrLoading" style="text-align:center;padding:16px 0;">
                <div v-if="qrDataUrl" style="background:#fff;display:inline-block;padding:16px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.06);">
                    <img :src="qrDataUrl" style="width:260px;height:260px;display:block" :alt="$t('portal.scan_activate_title')" />
                </div>
                <div v-if="qrDataUrl" style="margin-top:12px;font-size:13px;color:#606266;">
                    <p>{{ $t('portal.scan_activate_hint') }}</p>
                    <p style="font-size:11px;color:#909399;margin-top:4px;">License: {{ license.license_key }}</p>
                </div>
                <div v-if="qrDataUrl" style="margin-top:16px;display:flex;gap:8px;justify-content:center;">
                    <el-button size="small" @click="downloadQrCode">{{ $t('portal.download_qr') }}</el-button>
                    <el-button size="small" text @click="copyKey">{{ $t('portal.copy_license_key') }}</el-button>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import licenseApi from '@/api/license';
import deviceApi from '@/api/device';
import shopApi from '@/api/shop';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Monitor, Refresh, ArrowUp, Money, Download, Link, CopyDocument } from '@element-plus/icons-vue';
import QRCode from 'qrcode';

const route = useRoute();
const router = useRouter();
const { t } = useI18n();

const loading = ref(false);
const deactivatingId = ref(null);
const license = ref({});
const devices = ref([]);
const deliverables = ref([]);

const qrVisible = ref(false);
const qrLoading = ref(false);
const qrDataUrl = ref('');

async function showQrCode() {
    qrVisible.value = true;
    qrLoading.value = true;
    qrDataUrl.value = '';
    try {
        const activationUrl = `${window.location.origin}/activate?key=${encodeURIComponent(license.value.license_key)}`;
        qrDataUrl.value = await QRCode.toDataURL(activationUrl, {
            width: 260,
            margin: 2,
            color: { dark: '#1a1a2e', light: '#ffffff' },
        });
    } catch (e) {
        ElMessage.error(t('portal.qr_failed'));
    } finally {
        qrLoading.value = false;
    }
}

function downloadQrCode() {
    if (!qrDataUrl.value) return;
    const link = document.createElement('a');
    link.download = `license-${license.value.license_key?.substring(0, 8) || 'qr'}.png`;
    link.href = qrDataUrl.value;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

const deviceCount = computed(() => devices.value.length);
const devicePercent = computed(() => {
    const max = license.value.max_devices || 1;
    return Math.min(Math.round((deviceCount.value / max) * 100), 100);
});

function statusType(status) {
    const map = {
        pending: 'info', active: 'success', suspended: 'warning', frozen: 'warning',
        expired: 'info', revoked: 'danger', refunded: 'danger', blacklisted: 'danger',
    };
    return map[status] || 'info';
}

function statusLabel(status) {
    const map = {
        pending: t('portal.st_pending'),
        active: t('portal.st_active'),
        suspended: t('portal.st_suspended'),
        frozen: t('portal.st_frozen'),
        expired: t('portal.st_expired'),
        revoked: t('portal.st_revoked'),
        refunded: t('portal.st_refunded'),
        blacklisted: t('portal.st_blacklisted'),
    };
    return map[status] || status;
}

function isExpiring(dateStr) {
    if (!dateStr) return false;
    const diff = new Date(dateStr) - new Date();
    return diff / (1000 * 60 * 60 * 24) <= 30 && diff >= 0;
}

function formatJson(data) {
    try {
        return typeof data === 'object' ? JSON.stringify(data, null, 2) : data;
    } catch {
        return String(data);
    }
}

function typeIcon(type) {
    const icons = { file: 'F', link: 'L', text: 'T' };
    return icons[type] || 'D';
}

function categoryLabel(cat) {
    const labels = {
        software: t('portal.cat_software'),
        document: t('portal.cat_document'),
        template: t('portal.cat_template'),
        tutorial: t('portal.cat_tutorial'),
        other: t('portal.cat_other'),
    };
    return labels[cat] || cat || t('portal.cat_other');
}

function formatFileSize(bytes) {
    if (!bytes) return '';
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let uid = 0;
    while (size >= 1024 && uid < units.length - 1) { size /= 1024; uid++; }
    return size.toFixed(1) + ' ' + units[uid];
}

function openUrl(url) {
    if (url) window.open(url, '_blank');
}

async function copyPortalText(text) {
    try {
        await navigator.clipboard.writeText(text);
        ElMessage.success(t('portal.copied_clipboard'));
    } catch {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        ElMessage.success(t('portal.copied_clipboard'));
    }
}

const canRenew = computed(() => {
    return ['active', 'expired', 'suspended'].includes(license.value.status);
});

const canUpgrade = computed(() => {
    return ['active'].includes(license.value.status) && license.value.type !== 'enterprise';
});

const canRefund = computed(() => {
    return ['active', 'suspended', 'frozen'].includes(license.value.status);
});

async function handleRenew() {
    try {
        await ElMessageBox.confirm(
            t('portal.renew_confirm_body', { key: license.value.license_key }),
            t('portal.renew_confirm_title'),
            { confirmButtonText: t('portal.go_pay'), cancelButtonText: t('actions.cancel'), type: 'info' }
        );
        const payload = {
            license_id: license.value.id,
            product_id: license.value.product_id,
            billing_cycle: license.value.billing_cycle || 'monthly',
            type: 'renewal',
        };
        const res = await shopApi.quickBuy(payload);
        const order = res.data?.data || res.data;
        const orderId = order?.id || order?.order?.id;
        if (orderId) {
            ElMessage.success(t('portal.renew_order_created'));
            try {
                const payRes = await shopApi.initiatePayment(orderId, 'alipay');
                const payData = payRes.data?.data || payRes.data;
                if (payData?.payment_url) {
                    window.location.href = payData.payment_url;
                    return;
                }
            } catch {}
            window.location.href = `/portal/payment-result/${orderId}`;
        } else {
            ElMessage.error(t('portal.renew_order_failed'));
        }
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || t('portal.renew_failed'));
        }
    }
}

async function handleUpgrade() {
    try {
        await ElMessageBox.confirm(
            t('portal.upgrade_confirm_body'),
            t('portal.upgrade_confirm_title'),
            { confirmButtonText: t('portal.confirm_upgrade'), cancelButtonText: t('actions.cancel'), type: 'info' }
        );

        let targetId = null;
        try {
            const res = await fetch('/api/public/pricing-plans', { headers: { Accept: 'application/json' } });
            const json = await res.json();
            const plans = Array.isArray(json?.data) ? json.data : [];
            const paid = plans
                .filter((p) => Number(p.price_monthly || 0) > 0)
                .sort((a, b) => Number(b.price_monthly || 0) - Number(a.price_monthly || 0));
            const preferred = paid.find((p) => ['enterprise', 'ent', 'pro'].includes(String(p.slug || '')));
            targetId = (preferred || paid[0])?.id || null;
        } catch (_) {
            targetId = null;
        }

        window.location.href = targetId
            ? `/build/subscribe/${targetId}?period=monthly`
            : '/pricing';
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || t('portal.upgrade_failed'));
        }
    }
}

async function handleRequestRefund() {
    try {
        await ElMessageBox.confirm(
            t('portal.refund_license_confirm'),
            t('portal.request_refund'),
            {
                confirmButtonText: t('portal.request_refund'),
                cancelButtonText: t('actions.cancel'),
                type: 'warning',
                confirmButtonClass: 'el-button--danger',
            }
        );
        await licenseApi.refund(license.value.id);
        ElMessage.success(t('portal.refund_submitted'));
        await fetchDetail();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || t('portal.refund_failed'));
        }
    }
}

function copyKey() {
    navigator.clipboard.writeText(license.value.license_key).then(() => {
        ElMessage.success(t('portal.key_copied'));
    }).catch(() => {
        ElMessage.warning(t('portal.copy_failed'));
    });
}

async function fetchDetail() {
    const id = route.params.id;
    if (!id) return;
    loading.value = true;
    try {
        const { data: res } = await licenseApi.show(id);
        license.value = res.data || {};
        deliverables.value = res.data?.deliverables || [];

        const { data: devRes } = await deviceApi.list({ license_id: id, per_page: 50 });
        devices.value = devRes.data?.data || [];
    } catch {
        ElMessage.error(t('portal.license_detail_failed'));
    } finally {
        loading.value = false;
    }
}

async function handleDeactivate(dev) {
    try {
        const name = dev.name || dev.hostname || dev.fingerprint;
        await ElMessageBox.confirm(
            t('portal.unbind_device_confirm', { name }),
            t('portal.unbind_title'),
            { confirmButtonText: t('portal.confirm_unbind'), cancelButtonText: t('actions.cancel'), type: 'warning' }
        );
        deactivatingId.value = dev.id;
        await deviceApi.deactivate(dev.id);
        ElMessage.success(t('portal.unbind_ok'));
        devices.value = devices.value.filter(d => d.id !== dev.id);
    } catch {
        // cancelled or error
    } finally {
        deactivatingId.value = null;
    }
}

onMounted(fetchDetail);
</script>

<style scoped>
.mt-4 { margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }

.status-bar {
    display: flex;
    align-items: center;
    gap: 32px;
    flex-wrap: wrap;
}

.status-section {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.status-label {
    font-size: 12px;
    color: #909399;
}

.license-key {
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
    user-select: all;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.section-title {
    margin: 0 0 8px;
    font-size: 14px;
    color: #606266;
}

.metadata-json {
    background: #f5f7fa;
    border: 1px solid #e4e7ed;
    border-radius: 4px;
    padding: 12px;
    font-size: 12px;
    line-height: 1.6;
    overflow-x: auto;
}

.expiring-text { color: #e6a23c; font-weight: 500; }

.device-usage {
    padding: 8px 0;
}

.usage-hint {
    margin: 12px 0 0;
    font-size: 13px;
    color: #909399;
}

.device-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.device-item:last-child {
    border-bottom: none;
}

.device-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.device-name {
    font-size: 13px;
    font-weight: 500;
    color: #303133;
}

.device-fingerprint code {
    font-size: 11px;
    color: #909399;
}

.self-service-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.self-service-actions .action-btn {
    width: 100%;
}

.action-hint {
    text-align: center;
    color: #909399;
    font-size: 13px;
    margin: 0;
}

/* ─── 交付物卡片(门户) ─── */
.deliverables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 10px;
    margin-top: 12px;
}
.portal-deliverable-card {
    background: #fafafa;
    border: 1px solid #ebeef5;
    border-radius: 8px;
    padding: 12px;
    transition: all 0.2s;
}
.portal-deliverable-card:hover {
    border-color: #0f172a;
    box-shadow: 0 2px 8px rgba(15,23,42,0.1);
}
.portal-deliverable-card .dlv-header {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
}
.portal-deliverable-card .dlv-icon { font-size: 18px; }
.portal-deliverable-card .dlv-category { font-size: 11px; }
.portal-deliverable-card .dlv-name { font-weight: 600; font-size: 13px; margin-bottom: 4px; }
.portal-deliverable-card .dlv-desc { font-size: 12px; color: #909399; margin-bottom: 8px; line-height: 1.4; }
.portal-deliverable-card .dlv-action {
    display: flex;
    align-items: center;
    gap: 8px;
    padding-top: 8px;
    border-top: 1px solid #f0f0f0;
}
.portal-deliverable-card .dlv-size { font-size: 11px; color: #909399; }
</style>
