<template>
    <div class="license-detail" v-loading="loading">
        <el-page-header @back="$router.push('/licenses')" :content="pageTitle" />

        <!-- 状态信息卡 -->
        <el-card class="mt-4" shadow="never">
            <div class="status-bar">
                <div class="status-section">
                    <div class="status-label">{{ t('license_detail_page.current_status') }}</div>
                    <el-tag :type="statusType(license.status)" size="large" effect="dark">
                        {{ statusLabel(license.status) }}
                    </el-tag>
                </div>
                <div class="status-section">
                    <div class="status-label">{{ t('licenses_page.license_key') }}</div>
                    <code class="license-key">{{ license.license_key }}</code>
                    <el-button text size="small" @click="copyKey">{{ t('actions.copy') }}</el-button>
                </div>
                <div class="status-section">
                    <div class="status-label">{{ t('licenses_page.type') }}</div>
                    <el-tag v-if="license.type === 'trial'" type="warning" size="small">{{ t('licenses_page.type_trial') }}</el-tag>
                    <el-tag v-else-if="license.type === 'enterprise'" type="success" size="small">{{ t('licenses_page.type_enterprise') }}</el-tag>
                    <el-tag v-else-if="license.type === 'development'" size="small">{{ t('licenses_page.type_development') }}</el-tag>
                    <span v-else>{{ t('licenses_page.type_standard') }}</span>
                </div>
                <div class="status-section status-actions">
                    <div class="status-label">{{ t('license_detail_page.quick_actions') }}</div>
                    <div class="quick-actions">
                        <el-button size="small" type="danger" plain v-if="can('revoke')" @click="handleAction('revoke')" :icon="Remove">{{ t('licenses_page.revoke') }}</el-button>
                        <el-button size="small" type="warning" plain v-if="can('suspend')" @click="handleAction('suspend')" :icon="VideoPause">{{ t('licenses_page.suspend') }}</el-button>
                        <el-button size="small" type="warning" plain v-if="can('freeze')" @click="handleAction('freeze')" :icon="ColdDrink">{{ t('licenses_page.freeze') }}</el-button>
                        <el-button size="small" type="success" plain v-if="can('restore')" @click="handleAction('restore')" :icon="Refresh">{{ t('licenses_page.restore') }}</el-button>
                        <el-button size="small" text @click="openEdit" :icon="Edit">{{ t('actions.edit') }}</el-button>
                    </div>
                </div>
            </div>
            <div class="status-info-bar" v-if="statusInfo">
                <div class="info-item">
                    <span class="info-label">{{ t('license_detail_page.transition_label') }}：</span>
                    <template v-if="statusInfo.available_transitions?.length">
                        <el-tag v-for="st in statusInfo.available_transitions" :key="st" size="small" style="margin-right: 4px">
                            {{ statusLabel(st) }}
                        </el-tag>
                    </template>
                    <span v-else class="info-value">{{ t('license_detail_page.terminal_state') }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">{{ t('license_detail_page.active_devices') }}：</span>
                    <span class="info-value">{{ statusInfo.device_count }} / {{ statusInfo.max_devices }}</span>
                </div>
            </div>
        </el-card>

        <!-- 详情 + 编辑 -->
        <el-card class="mt-4">
            <template #header>
                <div class="card-header">
                    <span>{{ t('license_detail_page.basic_info') }}</span>
                    <el-button size="small" @click="openEdit">{{ t('actions.edit') }}</el-button>
                </div>
            </template>
            <el-descriptions :column="3" border>
                <el-descriptions-item :label="t('licenses_page.product')" :span="1">{{ license.product?.name || '-' }}</el-descriptions-item>
                <el-descriptions-item :label="t('licenses_page.customer')" :span="1">{{ license.customer?.name || '-' }}</el-descriptions-item>
                <el-descriptions-item :label="t('licenses_page.max_devices')" :span="1">{{ license.max_devices }}</el-descriptions-item>
                <el-descriptions-item :label="t('license_detail_page.activated_devices')" :span="1">{{ statusInfo?.device_count || 0 }}</el-descriptions-item>
                <el-descriptions-item :label="t('licenses_page.seats')" :span="1">{{ license.seats || 1 }}</el-descriptions-item>
                <el-descriptions-item :label="t('licenses_page.expires_at')" :span="1">
                    <span :class="expiryClass">{{ license.expires_at || t('licenses_page.permanent') }}</span>
                </el-descriptions-item>
                <el-descriptions-item :label="t('licenses_page.created_at')" :span="1">{{ license.created_at }}</el-descriptions-item>
                <el-descriptions-item :label="t('portal.activated_at')" :span="1">{{ license.activated_at || t('license_detail_page.never_activated') }}</el-descriptions-item>
                <el-descriptions-item :label="t('license_detail_page.last_modified')" :span="1">{{ license.updated_at }}</el-descriptions-item>
            </el-descriptions>
        </el-card>

        <!-- 交付物 -->
        <el-card class="mt-4" shadow="never" v-if="deliverables.length > 0">
            <template #header>
                <span>{{ t('license_detail_page.deliverables') }}</span>
            </template>
            <div class="deliverables-grid">
                <div v-for="(d, idx) in deliverables" :key="idx" class="deliverable-card">
                    <div class="dlv-header">
                        <span class="dlv-icon">{{ typeIcon(d.type) }}</span>
                        <el-tag size="small" class="dlv-category">{{ categoryLabel(d.category) }}</el-tag>
                    </div>
                    <div class="dlv-name">{{ d.name }}</div>
                    <div v-if="d.description" class="dlv-desc">{{ d.description }}</div>

                    <div v-if="d.type === 'file' && d.file_url" class="dlv-action">
                        <el-button size="small" type="primary" plain @click="downloadDeliverable(d)">
                            <el-icon><Download /></el-icon> {{ t('actions.download') }}
                        </el-button>
                        <span v-if="d.file_size" class="dlv-size">{{ formatFileSize(d.file_size) }}</span>
                    </div>
                    <div v-else-if="d.type === 'link' && d.file_url" class="dlv-action">
                        <el-button size="small" type="primary" link @click="openLink(d.file_url)">
                            <el-icon><Link /></el-icon> {{ t('portal.open_link') }}
                        </el-button>
                    </div>
                    <div v-else-if="d.type === 'text' && d.content" class="dlv-action">
                        <el-button size="small" type="primary" link @click="copyText(d.content)">
                            <el-icon><CopyDocument /></el-icon> {{ t('portal.copy_content') }}
                        </el-button>
                    </div>
                </div>
            </div>
        </el-card>

        <!-- 标签 -->
        <el-card class="mt-4" shadow="never">
            <template #header>
                <span>{{ t('license_detail_page.tags') }}</span>
            </template>
            <TagSelector
                taggable-type="license"
                :taggable-id="license.id"
                :tags="license.tags || []"
            />
        </el-card>

        <!-- 自定义字段 -->
        <el-card class="mt-4" shadow="never">
            <template #header>
                <span>{{ t('license_detail_page.custom_fields') }}</span>
                <el-button size="small" type="primary" plain @click="editCustomFields" v-if="customFields.length > 0">
                    {{ t('actions.edit') }}
                </el-button>
            </template>
            <div v-if="customFields.length === 0" class="empty-text">{{ t('license_detail_page.no_custom_fields') }}</div>
            <el-descriptions v-else :column="2" border size="small">
                <el-descriptions-item v-for="field in customFields" :key="field.id" :label="field.name">
                    <template v-if="field.field_type === 'boolean'">
                        <el-tag :type="field.value === '1' || field.value === 'true' ? 'success' : 'info'" size="small">
                            {{ field.value === '1' || field.value === 'true' ? t('product_detail_page.yes') : t('product_detail_page.no') }}
                        </el-tag>
                    </template>
                    <template v-else>
                        {{ field.value || '-' }}
                    </template>
                </el-descriptions-item>
            </el-descriptions>
        </el-card>

        <!-- 内部备注 -->
        <el-card class="mt-4" shadow="never">
            <template #header>
                <span>{{ t('license_detail_page.internal_notes') }}</span>
            </template>
            <NotesTimeline :license-id="license.id" />
        </el-card>

        <!-- 时段限制配置 -->
        <div class="mt-4">
            <TimeRestrictionTab :license-id="license.id" />
        </div>

        <!-- 状态操作 -->
        <el-card class="mt-4">
            <template #header>
                <span>{{ t('licenses_page.status_actions') }}</span>
            </template>
            <div class="action-buttons">
                <el-button type="danger" v-if="can('revoke')" @click="handleAction('revoke')" :icon="Remove">
                    {{ t('licenses_page.revoke') }}
                </el-button>
                <el-button type="warning" v-if="can('suspend')" @click="handleAction('suspend')" :icon="VideoPause">
                    {{ t('licenses_page.suspend') }}
                </el-button>
                <el-button type="warning" v-if="can('freeze')" @click="handleAction('freeze')" :icon="ColdDrink">
                    {{ t('licenses_page.freeze') }}
                </el-button>
                <el-button type="success" v-if="can('restore')" @click="handleAction('restore')" :icon="Refresh">
                    {{ t('licenses_page.restore') }}
                </el-button>
                <el-button type="danger" v-if="can('blacklist')" @click="handleAction('blacklist')" :icon="WarningFilled">
                    {{ t('licenses_page.blacklist') }}
                </el-button>
                <el-button type="info" v-if="can('refund')" @click="handleAction('refund')" :icon="Money">
                    {{ t('licenses_page.refund') }}
                </el-button>
                <el-divider direction="vertical" />
                <el-button type="danger" plain @click="handleDelete" :icon="Delete">
                    {{ t('actions.delete') }}
                </el-button>
                <el-button type="warning" plain v-if="license.deleted_at" @click="handleRestoreFromTrash" :icon="Refresh">
                    {{ t('license_detail_page.restore_from_trash') }}
                </el-button>
            </div>
        </el-card>

        <!-- 关联设备 -->
        <el-card class="mt-4">
            <template #header>
                <span>{{ t('license_detail_page.related_devices', { n: devices.length }) }}</span>
            </template>
            <el-table v-if="devices.length" :data="devices" stripe>
                <el-table-column prop="fingerprint" :label="t('devices_page.col_fingerprint')" min-width="200">
                    <template #default="{ row }">
                        <code class="small-text">{{ row.fingerprint }}</code>
                    </template>
                </el-table-column>
                <el-table-column prop="hostname" :label="t('devices_page.col_hostname')" width="150" />
                <el-table-column prop="platform" :label="t('devices_page.col_platform')" width="120" />
                <el-table-column prop="trust_score" :label="t('devices_page.col_trust_score')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="scoreType(row.trust_score)" size="small">{{ row.trust_score }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="last_activated_at" :label="t('license_detail_page.col_last_activation')" width="170" />
            </el-table>
            <el-empty v-else :description="t('license_detail_page.no_related_devices')" />
        </el-card>

        <!-- 激活记录 -->
        <el-card class="mt-4">
            <template #header>
                <span>{{ t('license_detail_page.activation_log', { n: activations.length }) }}</span>
            </template>
            <el-table v-if="activations.length" :data="activations" stripe>
                <el-table-column prop="device_fingerprint" :label="t('devices_page.col_fingerprint')" min-width="200">
                    <template #default="{ row }">
                        <code class="small-text">{{ row.device_fingerprint }}</code>
                    </template>
                </el-table-column>
                <el-table-column prop="ip_address" :label="t('license_detail_page.col_ip')" width="140" />
                <el-table-column prop="action" :label="t('licenses_page.col_actions')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.action === 'activate' ? 'success' : 'danger'" size="small">
                            {{ row.action === 'activate' ? t('licenses_page.act_activate') : t('licenses_page.act_deactivate') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" :label="t('license_detail_page.col_time')" width="170" />
            </el-table>
            <el-empty v-else :description="t('license_detail_page.no_activation_log')" />
        </el-card>

        <!-- 编辑对话框 -->
        <el-dialog v-model="showEdit" :title="t('licenses_page.edit_title')" width="560px">
            <el-form ref="editFormRef" :model="editForm" label-width="100px">
                <el-form-item :label="t('licenses_page.product')">
                    <el-select v-model="editForm.product_id" :placeholder="t('licenses_page.select_product')" filterable style="width: 100%">
                        <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('licenses_page.customer')">
                    <el-select v-model="editForm.customer_id" :placeholder="t('licenses_page.select_customer')" filterable style="width: 100%">
                        <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('licenses_page.type')">
                    <el-select v-model="editForm.type" style="width: 100%">
                        <el-option :label="t('licenses_page.type_standard')" value="standard" />
                        <el-option :label="t('licenses_page.type_trial')" value="trial" />
                        <el-option :label="t('licenses_page.type_enterprise')" value="enterprise" />
                        <el-option :label="t('licenses_page.type_development')" value="development" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('licenses_page.expires_at')">
                    <el-date-picker
                        v-model="editForm.expires_at"
                        type="datetime"
                        :placeholder="t('licenses_page.expires_ph')"
                        value-format="YYYY-MM-DD HH:mm:ss"
                        style="width: 100%"
                    />
                </el-form-item>
                <el-form-item :label="t('licenses_page.max_devices')">
                    <el-input-number v-model="editForm.max_devices" :min="1" :max="9999" />
                </el-form-item>
                <el-form-item :label="t('licenses_page.seats')">
                    <el-input-number v-model="editForm.seats" :min="1" :max="99999" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEdit = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="updating" @click="confirmEdit">{{ t('licenses_page.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- 自定义字段编辑对话框 -->
        <el-dialog v-model="showCustomFieldEdit" :title="t('license_detail_page.edit_custom_fields_title')" width="550px">
            <el-form label-width="120px">
                <template v-for="field in customFields" :key="field.id">
                    <el-form-item :label="field.name" :required="field.is_required">
                        <!-- text -->
                        <el-input
                            v-if="field.field_type === 'text'"
                            v-model="customFieldForm[field.field_definition_id]"
                            :placeholder="field.placeholder || ''"
                        />
                        <!-- textarea -->
                        <el-input
                            v-else-if="field.field_type === 'textarea'"
                            v-model="customFieldForm[field.field_definition_id]"
                            type="textarea"
                            :rows="3"
                            :placeholder="field.placeholder || ''"
                        />
                        <!-- number -->
                        <el-input-number
                            v-else-if="field.field_type === 'number'"
                            v-model="customFieldForm[field.field_definition_id]"
                            style="width: 200px"
                        />
                        <!-- select -->
                        <el-select
                            v-else-if="field.field_type === 'select'"
                            v-model="customFieldForm[field.field_definition_id]"
                            :placeholder="field.placeholder || t('license_detail_page.select_placeholder')"
                            style="width: 100%"
                            clearable
                        >
                            <el-option v-for="opt in field.options" :key="opt" :label="opt" :value="opt" />
                        </el-select>
                        <!-- multi_select -->
                        <el-select
                            v-else-if="field.field_type === 'multi_select'"
                            v-model="customFieldForm[field.field_definition_id]"
                            multiple
                            :placeholder="field.placeholder || t('license_detail_page.select_placeholder')"
                            style="width: 100%"
                        >
                            <el-option v-for="opt in field.options" :key="opt" :label="opt" :value="opt" />
                        </el-select>
                        <!-- date -->
                        <el-date-picker
                            v-else-if="field.field_type === 'date'"
                            v-model="customFieldForm[field.field_definition_id]"
                            type="date"
                            value-format="YYYY-MM-DD"
                            :placeholder="field.placeholder || t('license_detail_page.select_date')"
                            style="width: 100%"
                        />
                        <!-- boolean -->
                        <el-switch
                            v-else-if="field.field_type === 'boolean'"
                            v-model="customFieldForm[field.field_definition_id]"
                            active-value="1"
                            inactive-value="0"
                        />
                    </el-form-item>
                </template>
            </el-form>
            <template #footer>
                <el-button @click="showCustomFieldEdit = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="savingFields" @click="saveCustomFields">{{ t('license_detail_page.save_fields') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import licenseApi from '@/api/license';
import productApi from '@/api/product';
import customerApi from '@/api/customer';
import TagSelector from '@/components/TagSelector.vue';
import NotesTimeline from '@/components/NotesTimeline.vue';
import TimeRestrictionTab from '@/views/licenses/TimeRestrictionTab.vue';
import customFieldApi from '@/api/customField';
import {
    Remove, VideoPause, ColdDrink, Refresh,
    WarningFilled, Money, Delete, Edit,
    Download, Link, CopyDocument,
} from '@element-plus/icons-vue';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const id = computed(() => route.params.id);
const pageTitle = computed(() => t('license_detail_page.page_title', { id: id.value }));
const loading = ref(false);
const updating = ref(false);
const showEdit = ref(false);
const editFormRef = ref(null);
const license = ref({});
const statusInfo = ref(null);
const devices = ref([]);
const activations = ref([]);
const products = ref([]);
const customers = ref([]);
const deliverables = ref([]);

const editForm = reactive({
    product_id: null,
    customer_id: null,
    type: 'standard',
    expires_at: null,
    max_devices: 1,
    seats: 1,
});

const STATUS_TYPES = {
    pending: 'info',
    active: 'success',
    suspended: 'warning',
    frozen: 'warning',
    expired: 'info',
    revoked: 'danger',
    refunded: 'danger',
    blacklisted: 'danger',
};

const CATEGORY_KEYS = {
    software: 'products_page.cat_software',
    document: 'products_page.cat_document',
    template: 'products_page.cat_template',
    api: 'products_page.cat_api',
    tutorial: 'products_page.cat_tutorial',
    other: 'products_page.cat_other',
};

function statusType(status) { return STATUS_TYPES[status] || 'info'; }
function statusLabel(status) {
    const key = `licenses_page.st_${status}`;
    const translated = t(key);
    return translated !== key ? translated : status;
}
function scoreType(score) {
    if (score >= 80) return 'success';
    if (score >= 60) return 'warning';
    return 'danger';
}

function actionLabel(action) {
    const key = `licenses_page.${action}`;
    const translated = t(key);
    return translated !== key ? translated : action;
}

// ─── 交付物辅助 ───
function typeIcon(type) {
    const icons = { file: '📦', link: '🔗', text: '📝' };
    return icons[type] || '📄';
}
function categoryLabel(cat) {
    const key = CATEGORY_KEYS[cat] || CATEGORY_KEYS.other;
    return t(key);
}
function formatFileSize(bytes) {
    if (!bytes) return '';
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let uid = 0;
    while (size >= 1024 && uid < units.length - 1) { size /= 1024; uid++; }
    return size.toFixed(1) + ' ' + units[uid];
}
function downloadDeliverable(d) {
    if (d.file_url) window.open(d.file_url, '_blank');
}
function openLink(url) {
    if (url) window.open(url, '_blank');
}
async function copyText(text) {
    const copiedMsg = t('portal.copied_clipboard');
    try {
        await navigator.clipboard.writeText(text);
        ElMessage.success(copiedMsg);
    } catch {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        ElMessage.success(copiedMsg);
    }
}

const expiryClass = computed(() => {
    if (!license.value.expires_at) return '';
    const now = Date.now();
    const expiry = new Date(license.value.expires_at).getTime();
    if (expiry < now) return 'expired-text';
    return (expiry - now) / 86400000 <= 7 ? 'expiring-text' : '';
});

function can(action) {
    const s = license.value.status;
    const allowed = {
        revoke: ['active', 'suspended', 'frozen', 'pending'],
        suspend: ['active'],
        freeze: ['active'],
        restore: ['suspended', 'frozen'],
        blacklist: ['active', 'suspended', 'frozen', 'expired', 'pending'],
        refund: ['active', 'suspended', 'frozen'],
    };
    return allowed[action]?.includes(s);
}

async function loadOptions() {
    try {
        const [pRes, cRes] = await Promise.all([
            productApi.list({ per_page: 999 }),
            customerApi.list({ per_page: 999 }),
        ]);
        products.value = pRes.data?.data || [];
        customers.value = cRes.data?.data || [];
    } catch {
        // ignore
    }
}

async function fetchDetail() {
    loading.value = true;
    try {
        const { data: res } = await licenseApi.show(id.value);
        license.value = res.data?.license || res.data;
        statusInfo.value = res.data?.status_info || null;
        devices.value = res.data?.devices || license.value?.devices || [];
        activations.value = res.data?.activations || license.value?.activations || [];
        deliverables.value = res.data?.deliverables || [];
        await loadCustomFields();
    } catch {
        ElMessage.error(t('license_detail_page.load_failed'));
    } finally {
        loading.value = false;
    }
}

// ─── 自定义字段 ───
const customFields = ref([]);
const showCustomFieldEdit = ref(false);
const customFieldForm = ref({});
const savingFields = ref(false);

async function loadCustomFields() {
    try {
        const res = await customFieldApi.licenseValues(id.value);
        customFields.value = res.data?.data || [];
    } catch {
        customFields.value = [];
    }
}

function editCustomFields() {
    const form = {};
    for (const f of customFields.value) {
        form[f.field_definition_id] = f.value || '';
    }
    customFieldForm.value = form;
    showCustomFieldEdit.value = true;
}

async function saveCustomFields() {
    savingFields.value = true;
    try {
        await customFieldApi.updateLicenseValues(id.value, customFieldForm.value);
        ElMessage.success(t('license_detail_page.fields_saved'));
        showCustomFieldEdit.value = false;
        await loadCustomFields();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('messages.failed'));
    } finally {
        savingFields.value = false;
    }
}

function copyKey() {
    navigator.clipboard.writeText(license.value.license_key);
    ElMessage.success(t('licenses_page.key_copied'));
}

function openEdit() {
    Object.assign(editForm, {
        product_id: license.value.product_id,
        customer_id: license.value.customer_id,
        type: license.value.type,
        expires_at: license.value.expires_at,
        max_devices: license.value.max_devices || 1,
        seats: license.value.seats || 1,
    });
    showEdit.value = true;
}

async function confirmEdit() {
    updating.value = true;
    try {
        const payload = {};
        if (editForm.product_id) payload.product_id = editForm.product_id;
        if (editForm.customer_id) payload.customer_id = editForm.customer_id;
        if (editForm.type) payload.type = editForm.type;
        payload.expires_at = editForm.expires_at || null;
        payload.max_devices = editForm.max_devices;
        payload.seats = editForm.seats;

        await licenseApi.update(id.value, payload);
        ElMessage.success(t('licenses_page.update_ok'));
        showEdit.value = false;
        fetchDetail();
    } catch {
        ElMessage.error(t('licenses_page.update_fail'));
    } finally {
        updating.value = false;
    }
}

async function handleAction(action) {
    const label = actionLabel(action);
    try {
        await ElMessageBox.confirm(
            t('license_detail_page.action_confirm', { action: label }),
            t('licenses_page.confirm_action_title'),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning' },
        );
        const apiMap = {
            revoke: licenseApi.revoke, suspend: licenseApi.suspend, freeze: licenseApi.freeze,
            restore: licenseApi.restore, blacklist: licenseApi.blacklist, refund: licenseApi.refund,
        };
        await apiMap[action](id.value);
        ElMessage.success(t('licenses_page.action_ok', { action: label }));
        fetchDetail();
    } catch {
        // cancelled
    }
}

async function handleDelete() {
    try {
        await ElMessageBox.confirm(
            t('license_detail_page.delete_confirm'),
            t('licenses_page.delete_title'),
            { confirmButtonText: t('licenses_page.confirm_delete'), cancelButtonText: t('actions.cancel'), type: 'warning' },
        );
        await licenseApi.destroy(id.value);
        ElMessage.success(t('licenses_page.deleted_ok'));
        router.push('/licenses');
    } catch {
        // cancelled
    }
}

async function handleRestoreFromTrash() {
    try {
        await ElMessageBox.confirm(
            t('license_detail_page.restore_trash_confirm'),
            t('license_detail_page.restore_trash_title'),
            { confirmButtonText: t('license_detail_page.confirm_restore'), cancelButtonText: t('actions.cancel'), type: 'warning' },
        );
        await licenseApi.restoreFromTrash(id.value);
        ElMessage.success(t('license_detail_page.restored_from_trash'));
        fetchDetail();
    } catch {
        // cancelled
    }
}

onMounted(() => {
    loadOptions();
    fetchDetail();
});
</script>

<style scoped>
.mt-4 { margin-top: 16px; }
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.status-bar {
    display: flex;
    align-items: center;
    gap: 40px;
    flex-wrap: wrap;
}
.status-section {
    display: flex;
    align-items: center;
    gap: 8px;
}
.status-section.status-actions {
    margin-left: auto;
}
.quick-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.status-label { font-size: 13px; color: #909399; }
.license-key { font-size: 14px; letter-spacing: 1px; }
.status-info-bar {
    display: flex;
    gap: 32px;
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid #ebeef5;
    flex-wrap: wrap;
}
.info-item {
    display: flex;
    align-items: center;
    gap: 6px;
}
.info-label { font-size: 13px; color: #909399; }
.info-value { font-size: 13px; color: #303133; }
.action-buttons { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
.small-text { font-size: 11px; }
.expired-text { color: #f56c6c; text-decoration: line-through; }
.expiring-text { color: #e6a23c; font-weight: 600; }

/* ─── 交付物卡片 ─── */
.deliverables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 12px;
}
.deliverable-card {
    background: #fafafa;
    border: 1px solid #ebeef5;
    border-radius: 8px;
    padding: 14px;
    transition: all 0.2s;
}
.deliverable-card:hover {
    border-color: #0f172a;
    box-shadow: 0 2px 8px rgba(15,23,42,0.1);
}
.dlv-header {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 8px;
}
.dlv-icon { font-size: 20px; }
.dlv-category { font-size: 11px; }
.dlv-name { font-weight: 600; font-size: 14px; margin-bottom: 4px; }
.dlv-desc { font-size: 12px; color: #909399; margin-bottom: 10px; line-height: 1.4; }
.dlv-action {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #f0f0f0;
}
.dlv-size { font-size: 11px; color: #909399; }
</style>
