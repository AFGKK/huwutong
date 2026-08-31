<template>
  <div class="metered-deep-page">
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6"><el-card shadow="hover">
        <div class="stat-item"><div class="stat-value">{{ stats.active_pricings }}</div><div class="stat-label">{{ t('metered_billing_page.stat_active_pricings') }}</div></div>
      </el-card></el-col>
      <el-col :span="6"><el-card shadow="hover">
        <div class="stat-item"><div class="stat-value">{{ stats.active_alerts }}</div><div class="stat-label">{{ t('metered_billing_page.stat_active_alerts') }}</div></div>
      </el-card></el-col>
      <el-col :span="6"><el-card shadow="hover">
        <div class="stat-item"><div class="stat-value">{{ stats.active_switch_rules }}</div><div class="stat-label">{{ t('metered_billing_page.stat_active_switch_rules') }}</div></div>
      </el-card></el-col>
      <el-col :span="6"><el-card shadow="hover">
        <div class="stat-item"><div class="stat-value">{{ stats.total_alert_histories }}</div><div class="stat-label">{{ t('metered_billing_page.stat_alert_histories') }}</div></div>
      </el-card></el-col>
    </el-row>

    <el-tabs v-model="activeTab">
      <!-- 分层定价 -->
      <el-tab-pane :label="t('metered_billing_page.tab_pricing')" name="pricing">
        <el-card>
          <template #header>
            <div class="flex items-center justify-between">
              <span>{{ t('metered_billing_page.pricing_title') }}</span>
              <el-button type="primary" size="small" @click="showPricingDialog = true">{{ t('metered_billing_page.btn_new_plan') }}</el-button>
            </div>
          </template>
          <el-empty v-if="!loading && pricings.length === 0" :description="t('metered_billing_page.empty_pricing')" />
          <el-table v-else :data="pricings" stripe size="small">
            <el-table-column prop="name" :label="t('metered_billing_page.col_name')" width="160" />
            <el-table-column prop="metric_key" :label="t('metered_billing_page.col_metric')" width="120" />
            <el-table-column prop="tier_type" :label="t('metered_billing_page.col_tier_type')" width="100">
              <template #default="{ row }">{{ row.tier_type === 'volume' ? t('metered_billing_page.tier_volume') : t('metered_billing_page.tier_graduated') }}</template>
            </el-table-column>
            <el-table-column prop="billing_period" :label="t('metered_billing_page.col_period')" width="80" />
            <el-table-column :label="t('metered_billing_page.col_tier_detail')" min-width="200">
              <template #default="{ row }">
                  <span v-for="tier in row.tiers" :key="tier.id" class="tier-badge">
                    {{ formatNum(tier.from_unit) }}-{{ tier.to_unit ? formatNum(tier.to_unit) : t('metered_billing_page.infinity') }}: ¥{{ tier.unit_price }}/{{ tier.price_model === 'flat' ? t('metered_billing_page.price_per_occurrence') : t('metered_billing_page.price_per_unit') }}
                  </span>
              </template>
            </el-table-column>
            <el-table-column :label="t('metered_billing_page.col_status')" width="70">
              <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? t('metered_billing_page.status_active') : t('metered_billing_page.status_inactive') }}</el-tag></template>
            </el-table-column>
            <el-table-column :label="t('metered_billing_page.col_actions')" width="100">
              <template #default="{ row }">
                <el-button size="small" type="danger" plain @click="confirmDeletePricing(row)">{{ t('actions.delete') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>

      <!-- 超额预警 -->
      <el-tab-pane :label="t('metered_billing_page.tab_alerts')" name="alerts">
        <el-card>
          <template #header>
            <div class="flex items-center justify-between">
              <span>{{ t('metered_billing_page.alerts_title') }}</span>
              <div class="flex gap-2">
                <el-button size="small" @click="handleEvaluateAlerts">{{ t('metered_billing_page.btn_evaluate_now') }}</el-button>
                <el-button type="primary" size="small" @click="showAlertDialog = true">{{ t('metered_billing_page.btn_new_alert') }}</el-button>
              </div>
            </div>
          </template>
          <el-empty v-if="!loading && alerts.length === 0" :description="t('metered_billing_page.empty_alerts')" />
          <el-table v-else :data="alerts" stripe size="small">
            <el-table-column prop="name" :label="t('metered_billing_page.col_name')" width="140" />
            <el-table-column prop="metric_key" :label="t('metered_billing_page.col_metric_short')" width="100" />
            <el-table-column :label="t('metered_billing_page.col_trigger')" width="160">
              <template #default="{ row }">{{ row.threshold_type === 'percentage' ? row.percentage + '%' : row.threshold_value }} ({{ row.direction === 'above' ? t('metered_billing_page.direction_above') : t('metered_billing_page.direction_below') }})</template>
            </el-table-column>
            <el-table-column prop="window_type" :label="t('metered_billing_page.col_window')" width="80" />
            <el-table-column :label="t('metered_billing_page.col_notify_channels')" width="120">
              <template #default="{ row }">
                <el-tag v-for="c in (row.notify_channels || ['email'])" :key="c" size="small" class="mr-1">{{ c }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('metered_billing_page.col_status')" width="70">
              <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? t('metered_billing_page.status_active') : t('metered_billing_page.status_inactive') }}</el-tag></template>
            </el-table-column>
            <el-table-column :label="t('metered_billing_page.col_actions')" width="160">
              <template #default="{ row }">
                <el-button size="small" @click="viewAlertHistory(row)">{{ t('metered_billing_page.btn_logs') }}</el-button>
                <el-button size="small" type="danger" plain @click="confirmDeleteAlert(row)">{{ t('actions.delete') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>

      <!-- 自动切换套餐 -->
      <el-tab-pane :label="t('metered_billing_page.tab_switch')" name="switch">
        <el-card>
          <template #header>
            <div class="flex items-center justify-between">
              <span>{{ t('metered_billing_page.switch_title') }}</span>
              <div class="flex gap-2">
                <el-button size="small" @click="handleEvaluateSwitch">{{ t('metered_billing_page.btn_evaluate_now') }}</el-button>
                <el-button type="primary" size="small" @click="showSwitchDialog = true">{{ t('metered_billing_page.btn_new_rule') }}</el-button>
              </div>
            </div>
          </template>
          <el-empty v-if="!loading && switchRules.length === 0" :description="t('metered_billing_page.empty_rules')" />
          <el-table v-else :data="switchRules" stripe size="small">
            <el-table-column prop="name" :label="t('metered_billing_page.col_name')" width="140" />
            <el-table-column prop="metric_key" :label="t('metered_billing_page.col_metric_short')" width="100" />
            <el-table-column :label="t('metered_billing_page.col_condition')" width="160">
              <template #default="{ row }">{{ condLabel(row.condition_type) }} > {{ row.condition_value }} / {{ row.condition_days }}{{ t('metered_billing_page.days_suffix') }}</template>
            </el-table-column>
            <el-table-column prop="action" :label="t('metered_billing_page.col_action')" width="80">
              <template #default="{ row }"><el-tag :type="row.action === 'upgrade' ? 'warning' : 'info'" size="small">{{ row.action === 'upgrade' ? t('metered_billing_page.action_upgrade') : t('metered_billing_page.action_downgrade') }}</el-tag></template>
            </el-table-column>
            <el-table-column prop="target_plan_slug" :label="t('metered_billing_page.col_target_plan')" width="120" />
            <el-table-column :label="t('metered_billing_page.col_require_confirm')" width="70">
              <template #default="{ row }">{{ row.require_confirmation ? t('metered_billing_page.yes') : t('metered_billing_page.no') }}</template>
            </el-table-column>
            <el-table-column :label="t('metered_billing_page.col_status')" width="70">
              <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? t('metered_billing_page.status_active') : t('metered_billing_page.status_inactive') }}</el-tag></template>
            </el-table-column>
            <el-table-column :label="t('metered_billing_page.col_actions')" width="100">
              <template #default="{ row }">
                <el-button size="small" type="danger" plain @click="confirmDeleteSwitchRule(row)">{{ t('actions.delete') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>
    </el-tabs>

    <!-- 新建定价对话框 -->
    <el-dialog v-model="showPricingDialog" :title="t('metered_billing_page.dialog_new_pricing')" width="550px" destroy-on-close>
      <el-form ref="pfRef" :model="pf" :rules="pfRules" label-width="100px">
        <el-form-item :label="t('metered_billing_page.col_name')" prop="name"><el-input v-model="pf.name" /></el-form-item>
        <el-form-item :label="t('metered_billing_page.col_metric')" prop="metric_key">
          <el-select v-model="pf.metric_key" style="width:100%">
            <el-option v-for="opt in pricingMetricOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('metered_billing_page.col_tier_type')" prop="tier_type">
          <el-radio-group v-model="pf.tier_type">
            <el-radio v-for="opt in tierTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item :label="t('metered_billing_page.col_period')" prop="billing_period">
          <el-select v-model="pf.billing_period" style="width:100%">
            <el-option v-for="opt in billingPeriodOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('metered_billing_page.label_tiers')">
          <div v-for="(tier, i) in pf.tiers" :key="i" class="tier-form-row">
            <el-input-number v-model="tier.from_unit" :min="0" size="small" style="width:100px" :placeholder="t('metered_billing_page.ph_from')" />
            <span class="mx-1">~</span>
            <el-input-number v-model="tier.to_unit" :min="tier.from_unit" size="small" style="width:100px" :placeholder="t('metered_billing_page.ph_to')" :controls="false" />
            <el-input-number v-model="tier.unit_price" :min="0" :step="0.01" size="small" style="width:110px" :placeholder="t('metered_billing_page.ph_unit_price')" />
            <el-button size="small" type="danger" plain @click="pf.tiers.splice(i,1)">×</el-button>
          </div>
          <el-button size="small" @click="pf.tiers.push({from_unit:0,to_unit:null,unit_price:0,price_model:'per_unit',flat_fee:0})">{{ t('metered_billing_page.btn_add_tier') }}</el-button>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPricingDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="submitting" @click="handleCreatePricing">{{ t('actions.create') }}</el-button>
      </template>
    </el-dialog>

    <!-- 新建预警对话框 -->
    <el-dialog v-model="showAlertDialog" :title="t('metered_billing_page.dialog_new_alert')" width="480px" destroy-on-close>
      <el-form ref="afRef" :model="af" :rules="afRules" label-width="110px">
        <el-form-item :label="t('metered_billing_page.col_name')" prop="name"><el-input v-model="af.name" /></el-form-item>
        <el-form-item :label="t('metered_billing_page.col_metric')" prop="metric_key">
          <el-select v-model="af.metric_key" style="width:100%">
            <el-option v-for="opt in alertMetricOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('metered_billing_page.label_threshold_type')" prop="threshold_type">
          <el-select v-model="af.threshold_type" style="width:100%">
            <el-option v-for="opt in thresholdTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="af.threshold_type === 'percentage'" :label="t('metered_billing_page.label_percentage')" prop="percentage">
          <el-slider v-model="af.percentage" :min="0" :max="100" show-input style="width:200px" />
        </el-form-item>
        <el-form-item v-else :label="t('metered_billing_page.label_threshold')" prop="threshold_value">
          <el-input-number v-model="af.threshold_value" :min="0" style="width:200px" />
        </el-form-item>
        <el-form-item :label="t('metered_billing_page.label_direction')" prop="direction">
          <el-radio-group v-model="af.direction">
            <el-radio v-for="opt in directionOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item :label="t('metered_billing_page.col_window')" prop="window_type">
          <el-select v-model="af.window_type" style="width:100%">
            <el-option v-for="opt in windowTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAlertDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="submitting" @click="handleCreateAlert">{{ t('actions.create') }}</el-button>
      </template>
    </el-dialog>

    <!-- 新建切换规则对话框 -->
    <el-dialog v-model="showSwitchDialog" :title="t('metered_billing_page.dialog_new_switch')" width="500px" destroy-on-close>
      <el-form ref="sfRef" :model="sf" :rules="sfRules" label-width="110px">
        <el-form-item :label="t('metered_billing_page.col_name')" prop="name"><el-input v-model="sf.name" /></el-form-item>
        <el-form-item :label="t('metered_billing_page.col_metric')" prop="metric_key">
          <el-select v-model="sf.metric_key" style="width:100%">
            <el-option v-for="opt in switchMetricOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('metered_billing_page.label_condition_type')" prop="condition_type">
          <el-select v-model="sf.condition_type" style="width:100%">
            <el-option v-for="opt in conditionTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('metered_billing_page.label_threshold')" prop="condition_value">
          <el-input-number v-model="sf.condition_value" :min="0" style="width:200px" />
        </el-form-item>
        <el-form-item :label="t('metered_billing_page.label_condition_days')" prop="condition_days">
          <el-input-number v-model="sf.condition_days" :min="1" :max="90" style="width:200px" />
        </el-form-item>
        <el-form-item :label="t('metered_billing_page.col_action')" prop="action">
          <el-radio-group v-model="sf.action">
            <el-radio v-for="opt in switchActionOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item :label="t('metered_billing_page.col_target_plan')" prop="target_plan_slug">
          <el-input v-model="sf.target_plan_slug" :placeholder="t('metered_billing_page.ph_target_plan')" />
        </el-form-item>
        <el-form-item :label="t('metered_billing_page.col_require_confirm')">
          <el-switch v-model="sf.require_confirmation" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showSwitchDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="submitting" @click="handleCreateSwitchRule">{{ t('actions.create') }}</el-button>
      </template>
    </el-dialog>

    <!-- 预警历史 -->
    <el-dialog v-model="showHistoryDialog" :title="t('metered_billing_page.dialog_alert_history')" width="650px" destroy-on-close>
      <el-table :data="histories" stripe size="small" max-height="400">
        <el-table-column prop="triggered_at" :label="t('metered_billing_page.col_triggered_at')" width="160" />
        <el-table-column prop="current_value" :label="t('metered_billing_page.col_current_value')" width="80" />
        <el-table-column prop="threshold_value" :label="t('metered_billing_page.col_threshold')" width="80" />
        <el-table-column prop="channel" :label="t('metered_billing_page.col_channel')" width="70" />
        <el-table-column prop="status" :label="t('metered_billing_page.col_status')" width="70">
          <template #default="{ row }"><el-tag :type="row.status === 'sent' ? 'success' : 'danger'" size="small">{{ row.status }}</el-tag></template>
        </el-table-column>
        <el-table-column prop="message" :label="t('metered_billing_page.col_message')" min-width="200" show-overflow-tooltip />
      </el-table>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  getMeteredStats, getTieredPricings, createTieredPricing, deleteTieredPricing,
  getAlerts, createAlert, deleteAlert, getAlertHistories, evaluateAlerts,
  getAutoSwitchRules, createAutoSwitchRule, deleteAutoSwitchRule, evaluateAutoSwitch,
} from '../../api/meteredBillingDeep';

const { t } = useI18n();

const activeTab = ref('pricing');
const loading = ref(false);
const submitting = ref(false);
const pricings = ref([]);
const alerts = ref([]);
const switchRules = ref([]);
const histories = ref([]);
const stats = reactive({ active_pricings: 0, active_alerts: 0, active_switch_rules: 0, total_alert_histories: 0 });

const showPricingDialog = ref(false);
const showAlertDialog = ref(false);
const showSwitchDialog = ref(false);
const showHistoryDialog = ref(false);

const pf = reactive({ name: '', metric_key: 'api_call', tier_type: 'volume', billing_period: 'monthly', tiers: [{ from_unit: 0, to_unit: 1000, unit_price: 0.01, price_model: 'per_unit', flat_fee: 0 }] });
const af = reactive({ name: '', metric_key: 'api_call', threshold_type: 'quantity', threshold_value: 10000, percentage: 80, direction: 'above', window_type: 'monthly', notify_channels: ['email'] });
const sf = reactive({ name: '', metric_key: 'api_call', condition_type: 'usage_consecutive', condition_value: 1000, condition_days: 3, action: 'upgrade', target_plan_slug: '', require_confirmation: true });

const pfRules = computed(() => ({
  name: [{ required: true, message: t('metered_billing_page.validation_name_required') }],
  metric_key: [{ required: true }],
}));
const afRules = computed(() => ({
  name: [{ required: true, message: t('metered_billing_page.validation_name_required') }],
  metric_key: [{ required: true }],
}));
const sfRules = computed(() => ({
  name: [{ required: true, message: t('metered_billing_page.validation_name_required') }],
  target_plan_slug: [{ required: true, message: t('metered_billing_page.validation_target_plan_required') }],
}));

const pricingMetricOptions = computed(() => [
  { value: 'api_call', label: t('metered_billing_page.metric_api_call') },
  { value: 'device_verify', label: t('metered_billing_page.metric_device_verify') },
  { value: 'token_issue', label: t('metered_billing_page.metric_token_issue') },
  { value: 'storage_gb', label: t('metered_billing_page.metric_storage_gb') },
  { value: 'bandwidth_gb', label: t('metered_billing_page.metric_bandwidth_gb') },
]);

const alertMetricOptions = computed(() => pricingMetricOptions.value.filter(o => o.value !== 'bandwidth_gb'));

const switchMetricOptions = computed(() => pricingMetricOptions.value.filter(o => ['api_call', 'device_verify'].includes(o.value)));

const tierTypeOptions = computed(() => [
  { value: 'volume', label: t('metered_billing_page.tier_type_volume') },
  { value: 'graduated', label: t('metered_billing_page.tier_type_graduated') },
]);

const billingPeriodOptions = computed(() => [
  { value: 'monthly', label: t('metered_billing_page.period_monthly') },
  { value: 'yearly', label: t('metered_billing_page.period_yearly') },
  { value: 'one_time', label: t('metered_billing_page.period_one_time') },
]);

const thresholdTypeOptions = computed(() => [
  { value: 'quantity', label: t('metered_billing_page.threshold_quantity') },
  { value: 'percentage', label: t('metered_billing_page.threshold_percentage') },
]);

const directionOptions = computed(() => [
  { value: 'above', label: t('metered_billing_page.direction_above') },
  { value: 'below', label: t('metered_billing_page.direction_below') },
]);

const windowTypeOptions = computed(() => [
  { value: 'daily', label: t('metered_billing_page.window_daily') },
  { value: 'monthly', label: t('metered_billing_page.window_monthly') },
  { value: 'billing_period', label: t('metered_billing_page.window_billing_period') },
]);

const conditionTypeOptions = computed(() => [
  { value: 'usage_consecutive', label: t('metered_billing_page.cond_usage_consecutive') },
  { value: 'usage_average', label: t('metered_billing_page.cond_usage_average') },
]);

const switchActionOptions = computed(() => [
  { value: 'upgrade', label: t('metered_billing_page.action_upgrade') },
  { value: 'downgrade', label: t('metered_billing_page.action_downgrade') },
]);

const condLabels = computed(() => ({
  usage_consecutive: t('metered_billing_page.cond_usage_consecutive'),
  usage_average: t('metered_billing_page.cond_usage_average'),
  spend_threshold: t('metered_billing_page.cond_spend_threshold'),
}));

function formatNum(n) { return n >= 1000 ? (n / 1000).toFixed(0) + 'k' : n; }
function condLabel(type) { return condLabels.value[type] || type; }

async function loadStats() {
  try { const { data } = await getMeteredStats(); Object.assign(stats, data.data); } catch {}
}
async function loadPricings() {
  try { const { data } = await getTieredPricings(); pricings.value = data.data; } catch {}
}
async function loadAlerts() {
  try { const { data } = await getAlerts(); alerts.value = data.data; } catch {}
}
async function loadSwitchRules() {
  try { const { data } = await getAutoSwitchRules(); switchRules.value = data.data; } catch {}
}

async function handleCreatePricing() {
  submitting.value = true;
  try {
    await createTieredPricing({ ...pf });
    ElMessage.success(t('metered_billing_page.msg_created'));
    showPricingDialog.value = false;
    pf.name = ''; pf.tiers = [{ from_unit: 0, to_unit: 1000, unit_price: 0.01, price_model: 'per_unit', flat_fee: 0 }];
    await loadPricings(); await loadStats();
  } catch (e) { ElMessage.error(e.response?.data?.message || t('messages.failed')); }
  finally { submitting.value = false; }
}

function confirmDeletePricing(row) {
  ElMessageBox.confirm(t('metered_billing_page.confirm_delete_pricing', { name: row.name }), t('actions.confirm'), { type: 'warning' })
    .then(async () => { await deleteTieredPricing(row.id); ElMessage.success(t('metered_billing_page.msg_deleted')); await loadPricings(); await loadStats(); })
    .catch(() => {});
}

async function handleCreateAlert() {
  submitting.value = true;
  try {
    await createAlert({ ...af });
    ElMessage.success(t('metered_billing_page.msg_created'));
    showAlertDialog.value = false;
    af.name = ''; af.threshold_value = 10000;
    await loadAlerts(); await loadStats();
  } catch (e) { ElMessage.error(e.response?.data?.message || t('messages.failed')); }
  finally { submitting.value = false; }
}

function confirmDeleteAlert(row) {
  ElMessageBox.confirm(t('metered_billing_page.confirm_delete_alert', { name: row.name }), t('actions.confirm'), { type: 'warning' })
    .then(async () => { await deleteAlert(row.id); ElMessage.success(t('metered_billing_page.msg_deleted')); await loadAlerts(); await loadStats(); })
    .catch(() => {});
}

async function viewAlertHistory(row) {
  showHistoryDialog.value = true;
  try { const { data } = await getAlertHistories(row.id); histories.value = data.data.data || data.data; } catch { histories.value = []; }
}

async function handleEvaluateAlerts() {
  try {
    const { data } = await evaluateAlerts();
    ElMessage.success(t('metered_billing_page.msg_eval_alerts_done', { count: data.data.length }));
  } catch {}
}

async function handleCreateSwitchRule() {
  submitting.value = true;
  try {
    await createAutoSwitchRule({ ...sf });
    ElMessage.success(t('metered_billing_page.msg_created'));
    showSwitchDialog.value = false;
    sf.name = ''; sf.target_plan_slug = '';
    await loadSwitchRules(); await loadStats();
  } catch (e) { ElMessage.error(e.response?.data?.message || t('messages.failed')); }
  finally { submitting.value = false; }
}

function confirmDeleteSwitchRule(row) {
  ElMessageBox.confirm(t('metered_billing_page.confirm_delete_rule', { name: row.name }), t('actions.confirm'), { type: 'warning' })
    .then(async () => { await deleteAutoSwitchRule(row.id); ElMessage.success(t('metered_billing_page.msg_deleted')); await loadSwitchRules(); await loadStats(); })
    .catch(() => {});
}

async function handleEvaluateSwitch() {
  try {
    const { data } = await evaluateAutoSwitch();
    ElMessage.success(t('metered_billing_page.msg_eval_switch_done', { count: data.data.length }));
  } catch {}
}

onMounted(() => { loadStats(); loadPricings(); loadAlerts(); loadSwitchRules(); });
</script>

<style scoped>
.metered-deep-page { padding: 20px; }
.stat-item { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: var(--el-color-primary); }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.mb-4 { margin-bottom: 16px; }
.flex { display: flex; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.gap-2 { gap: 8px; }
.mr-1 { margin-right: 4px; }
.mx-1 { margin: 0 4px; }
.tier-badge { display: inline-block; padding: 2px 6px; margin: 2px; background: var(--el-fill-color-light); border-radius: 4px; font-size: 12px; }
.tier-form-row { display: flex; align-items: center; gap: 6px; margin-bottom: 8px; }
</style>
