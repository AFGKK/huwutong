<template>
  <div class="metered-deep-page">
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6"><el-card shadow="hover">
        <div class="stat-item"><div class="stat-value">{{ stats.active_pricings }}</div><div class="stat-label">活跃定价方案</div></div>
      </el-card></el-col>
      <el-col :span="6"><el-card shadow="hover">
        <div class="stat-item"><div class="stat-value">{{ stats.active_alerts }}</div><div class="stat-label">活跃预警</div></div>
      </el-card></el-col>
      <el-col :span="6"><el-card shadow="hover">
        <div class="stat-item"><div class="stat-value">{{ stats.active_switch_rules }}</div><div class="stat-label">自动切换规则</div></div>
      </el-card></el-col>
      <el-col :span="6"><el-card shadow="hover">
        <div class="stat-item"><div class="stat-value">{{ stats.total_alert_histories }}</div><div class="stat-label">预警历史</div></div>
      </el-card></el-col>
    </el-row>

    <el-tabs v-model="activeTab">
      <!-- 分层定价 -->
      <el-tab-pane label="分层定价" name="pricing">
        <el-card>
          <template #header>
            <div class="flex items-center justify-between">
              <span>分层定价方案</span>
              <el-button type="primary" size="small" @click="showPricingDialog = true">新建方案</el-button>
            </div>
          </template>
          <el-empty v-if="!loading && pricings.length === 0" description="暂无定价方案" />
          <el-table v-else :data="pricings" stripe size="small">
            <el-table-column prop="name" label="名称" width="160" />
            <el-table-column prop="metric_key" label="计量指标" width="120" />
            <el-table-column prop="tier_type" label="阶梯类型" width="100">
              <template #default="{ row }">{{ row.tier_type === 'volume' ? '总量' : '梯度' }}</template>
            </el-table-column>
            <el-table-column prop="billing_period" label="周期" width="80" />
            <el-table-column label="阶梯详情" min-width="200">
              <template #default="{ row }">
                  <span v-for="t in row.tiers" :key="t.id" class="tier-badge">
                    {{ formatNum(t.from_unit) }}-{{ t.to_unit ? formatNum(t.to_unit) : '∞' }}: ¥{{ t.unit_price }}/{{ t.price_model === 'flat' ? '次' : '单位' }}
                  </span>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="70">
              <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag></template>
            </el-table-column>
            <el-table-column label="操作" width="100">
              <template #default="{ row }">
                <el-button size="small" type="danger" plain @click="confirmDeletePricing(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>

      <!-- 超额预警 -->
      <el-tab-pane label="超额预警" name="alerts">
        <el-card>
          <template #header>
            <div class="flex items-center justify-between">
              <span>超额预警配置</span>
              <div class="flex gap-2">
                <el-button size="small" @click="handleEvaluateAlerts">立即评估</el-button>
                <el-button type="primary" size="small" @click="showAlertDialog = true">新建预警</el-button>
              </div>
            </div>
          </template>
          <el-empty v-if="!loading && alerts.length === 0" description="暂无预警" />
          <el-table v-else :data="alerts" stripe size="small">
            <el-table-column prop="name" label="名称" width="140" />
            <el-table-column prop="metric_key" label="指标" width="100" />
            <el-table-column label="触发条件" width="160">
              <template #default="{ row }">{{ row.threshold_type === 'percentage' ? row.percentage + '%' : row.threshold_value }} ({{ row.direction === 'above' ? '超过' : '低于' }})</template>
            </el-table-column>
            <el-table-column prop="window_type" label="窗口" width="80" />
            <el-table-column label="通知渠道" width="120">
              <template #default="{ row }">
                <el-tag v-for="c in (row.notify_channels || ['email'])" :key="c" size="small" class="mr-1">{{ c }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="70">
              <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag></template>
            </el-table-column>
            <el-table-column label="操作" width="160">
              <template #default="{ row }">
                <el-button size="small" @click="viewAlertHistory(row)">日志</el-button>
                <el-button size="small" type="danger" plain @click="confirmDeleteAlert(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>

      <!-- 自动切换套餐 -->
      <el-tab-pane label="自动切换套餐" name="switch">
        <el-card>
          <template #header>
            <div class="flex items-center justify-between">
              <span>自动切换套餐规则</span>
              <div class="flex gap-2">
                <el-button size="small" @click="handleEvaluateSwitch">立即评估</el-button>
                <el-button type="primary" size="small" @click="showSwitchDialog = true">新建规则</el-button>
              </div>
            </div>
          </template>
          <el-empty v-if="!loading && switchRules.length === 0" description="暂无规则" />
          <el-table v-else :data="switchRules" stripe size="small">
            <el-table-column prop="name" label="名称" width="140" />
            <el-table-column prop="metric_key" label="指标" width="100" />
            <el-table-column label="条件" width="160">
              <template #default="{ row }">{{ condLabel(row.condition_type) }} > {{ row.condition_value }} / {{ row.condition_days }}天</template>
            </el-table-column>
            <el-table-column prop="action" label="动作" width="80">
              <template #default="{ row }"><el-tag :type="row.action === 'upgrade' ? 'warning' : 'info'" size="small">{{ row.action === 'upgrade' ? '升级' : '降级' }}</el-tag></template>
            </el-table-column>
            <el-table-column prop="target_plan_slug" label="目标套餐" width="120" />
            <el-table-column label="需确认" width="70">
              <template #default="{ row }">{{ row.require_confirmation ? '是' : '否' }}</template>
            </el-table-column>
            <el-table-column label="状态" width="70">
              <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag></template>
            </el-table-column>
            <el-table-column label="操作" width="100">
              <template #default="{ row }">
                <el-button size="small" type="danger" plain @click="confirmDeleteSwitchRule(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>
    </el-tabs>

    <!-- 新建定价对话框 -->
    <el-dialog v-model="showPricingDialog" title="新建分层定价" width="550px" destroy-on-close>
      <el-form ref="pfRef" :model="pf" :rules="pfRules" label-width="100px">
        <el-form-item label="名称" prop="name"><el-input v-model="pf.name" /></el-form-item>
        <el-form-item label="计量指标" prop="metric_key">
          <el-select v-model="pf.metric_key" style="width:100%">
            <el-option label="API 调用" value="api_call" />
            <el-option label="设备验证" value="device_verify" />
            <el-option label="令牌发放" value="token_issue" />
            <el-option label="存储 (GB)" value="storage_gb" />
            <el-option label="带宽 (GB)" value="bandwidth_gb" />
          </el-select>
        </el-form-item>
        <el-form-item label="阶梯类型" prop="tier_type">
          <el-radio-group v-model="pf.tier_type">
            <el-radio value="volume">总量阶梯</el-radio>
            <el-radio value="graduated">梯度阶梯</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="周期" prop="billing_period">
          <el-select v-model="pf.billing_period" style="width:100%">
            <el-option label="月度" value="monthly" />
            <el-option label="年度" value="yearly" />
            <el-option label="一次性" value="one_time" />
          </el-select>
        </el-form-item>
        <el-form-item label="阶梯">
          <div v-for="(t, i) in pf.tiers" :key="i" class="tier-form-row">
            <el-input-number v-model="t.from_unit" :min="0" size="small" style="width:100px" placeholder="起始" />
            <span class="mx-1">~</span>
            <el-input-number v-model="t.to_unit" :min="t.from_unit" size="small" style="width:100px" placeholder="结束(空=∞)" :controls="false" />
            <el-input-number v-model="t.unit_price" :min="0" :step="0.01" size="small" style="width:110px" placeholder="单价" />
            <el-button size="small" type="danger" plain @click="pf.tiers.splice(i,1)">×</el-button>
          </div>
          <el-button size="small" @click="pf.tiers.push({from_unit:0,to_unit:null,unit_price:0,price_model:'per_unit',flat_fee:0})">+ 添加阶梯</el-button>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPricingDialog = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleCreatePricing">创建</el-button>
      </template>
    </el-dialog>

    <!-- 新建预警对话框 -->
    <el-dialog v-model="showAlertDialog" title="新建超额预警" width="480px" destroy-on-close>
      <el-form ref="afRef" :model="af" :rules="afRules" label-width="110px">
        <el-form-item label="名称" prop="name"><el-input v-model="af.name" /></el-form-item>
        <el-form-item label="计量指标" prop="metric_key">
          <el-select v-model="af.metric_key" style="width:100%">
            <el-option label="API 调用" value="api_call" /><el-option label="设备验证" value="device_verify" />
            <el-option label="令牌发放" value="token_issue" /><el-option label="存储 (GB)" value="storage_gb" />
          </el-select>
        </el-form-item>
        <el-form-item label="阈值类型" prop="threshold_type">
          <el-select v-model="af.threshold_type" style="width:100%">
            <el-option label="数量" value="quantity" /><el-option label="百分比" value="percentage" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="af.threshold_type === 'percentage'" label="百分比" prop="percentage">
          <el-slider v-model="af.percentage" :min="0" :max="100" show-input style="width:200px" />
        </el-form-item>
        <el-form-item v-else label="阈值" prop="threshold_value">
          <el-input-number v-model="af.threshold_value" :min="0" style="width:200px" />
        </el-form-item>
        <el-form-item label="方向" prop="direction">
          <el-radio-group v-model="af.direction">
            <el-radio value="above">超过</el-radio>
            <el-radio value="below">低于</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="窗口" prop="window_type">
          <el-select v-model="af.window_type" style="width:100%">
            <el-option label="日" value="daily" /><el-option label="月" value="monthly" /><el-option label="账单周期" value="billing_period" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAlertDialog = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleCreateAlert">创建</el-button>
      </template>
    </el-dialog>

    <!-- 新建切换规则对话框 -->
    <el-dialog v-model="showSwitchDialog" title="新建自动切换规则" width="500px" destroy-on-close>
      <el-form ref="sfRef" :model="sf" :rules="sfRules" label-width="110px">
        <el-form-item label="名称" prop="name"><el-input v-model="sf.name" /></el-form-item>
        <el-form-item label="计量指标" prop="metric_key">
          <el-select v-model="sf.metric_key" style="width:100%">
            <el-option label="API 调用" value="api_call" /><el-option label="设备验证" value="device_verify" />
          </el-select>
        </el-form-item>
        <el-form-item label="条件类型" prop="condition_type">
          <el-select v-model="sf.condition_type" style="width:100%">
            <el-option label="连续用量超标" value="usage_consecutive" />
            <el-option label="平均用量超标" value="usage_average" />
          </el-select>
        </el-form-item>
        <el-form-item label="阈值" prop="condition_value">
          <el-input-number v-model="sf.condition_value" :min="0" style="width:200px" />
        </el-form-item>
        <el-form-item label="持续天数" prop="condition_days">
          <el-input-number v-model="sf.condition_days" :min="1" :max="90" style="width:200px" />
        </el-form-item>
        <el-form-item label="动作" prop="action">
          <el-radio-group v-model="sf.action">
            <el-radio value="upgrade">升级</el-radio>
            <el-radio value="downgrade">降级</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="目标套餐" prop="target_plan_slug">
          <el-input v-model="sf.target_plan_slug" placeholder="例: pro_plan" />
        </el-form-item>
        <el-form-item label="需确认">
          <el-switch v-model="sf.require_confirmation" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showSwitchDialog = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleCreateSwitchRule">创建</el-button>
      </template>
    </el-dialog>

    <!-- 预警历史 -->
    <el-dialog v-model="showHistoryDialog" title="预警历史" width="650px" destroy-on-close>
      <el-table :data="histories" stripe size="small" max-height="400">
        <el-table-column prop="triggered_at" label="触发时间" width="160" />
        <el-table-column prop="current_value" label="当前值" width="80" />
        <el-table-column prop="threshold_value" label="阈值" width="80" />
        <el-table-column prop="channel" label="渠道" width="70" />
        <el-table-column prop="status" label="状态" width="70">
          <template #default="{ row }"><el-tag :type="row.status === 'sent' ? 'success' : 'danger'" size="small">{{ row.status }}</el-tag></template>
        </el-table-column>
        <el-table-column prop="message" label="消息" min-width="200" show-overflow-tooltip />
      </el-table>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  getMeteredStats, getTieredPricings, createTieredPricing, deleteTieredPricing,
  getAlerts, createAlert, deleteAlert, getAlertHistories, evaluateAlerts,
  getAutoSwitchRules, createAutoSwitchRule, deleteAutoSwitchRule, evaluateAutoSwitch,
} from '../../api/meteredBillingDeep';

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
const pfRules = { name: [{ required: true, message: '必填' }], metric_key: [{ required: true }] };
const af = reactive({ name: '', metric_key: 'api_call', threshold_type: 'quantity', threshold_value: 10000, percentage: 80, direction: 'above', window_type: 'monthly', notify_channels: ['email'] });
const afRules = { name: [{ required: true }], metric_key: [{ required: true }] };
const sf = reactive({ name: '', metric_key: 'api_call', condition_type: 'usage_consecutive', condition_value: 1000, condition_days: 3, action: 'upgrade', target_plan_slug: '', require_confirmation: true });
const sfRules = { name: [{ required: true }], target_plan_slug: [{ required: true, message: '必填' }] };

function formatNum(n) { return n >= 1000 ? (n / 1000).toFixed(0) + 'k' : n; }
function condLabel(t) { return { usage_consecutive: '连续超标', usage_average: '平均超标', spend_threshold: '消费超标' }[t] || t; }

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
    ElMessage.success('创建成功');
    showPricingDialog.value = false;
    pf.name = ''; pf.tiers = [{ from_unit: 0, to_unit: 1000, unit_price: 0.01, price_model: 'per_unit', flat_fee: 0 }];
    await loadPricings(); await loadStats();
  } catch (e) { ElMessage.error(e.response?.data?.message || '失败'); }
  finally { submitting.value = false; }
}

function confirmDeletePricing(row) {
  ElMessageBox.confirm(`删除定价「${row.name}」？`, '确认', { type: 'warning' })
    .then(async () => { await deleteTieredPricing(row.id); ElMessage.success('已删除'); await loadPricings(); await loadStats(); })
    .catch(() => {});
}

async function handleCreateAlert() {
  submitting.value = true;
  try {
    await createAlert({ ...af });
    ElMessage.success('创建成功');
    showAlertDialog.value = false;
    af.name = ''; af.threshold_value = 10000;
    await loadAlerts(); await loadStats();
  } catch (e) { ElMessage.error(e.response?.data?.message || '失败'); }
  finally { submitting.value = false; }
}

function confirmDeleteAlert(row) {
  ElMessageBox.confirm(`删除预警「${row.name}」？`, '确认', { type: 'warning' })
    .then(async () => { await deleteAlert(row.id); ElMessage.success('已删除'); await loadAlerts(); await loadStats(); })
    .catch(() => {});
}

async function viewAlertHistory(row) {
  showHistoryDialog.value = true;
  try { const { data } = await getAlertHistories(row.id); histories.value = data.data.data || data.data; } catch { histories.value = []; }
}

async function handleEvaluateAlerts() {
  try { const { data } = await evaluateAlerts(); ElMessage.success(`评估完成，触发了 ${data.data.length} 条预警`); } catch {}
}

async function handleCreateSwitchRule() {
  submitting.value = true;
  try {
    await createAutoSwitchRule({ ...sf });
    ElMessage.success('创建成功');
    showSwitchDialog.value = false;
    sf.name = ''; sf.target_plan_slug = '';
    await loadSwitchRules(); await loadStats();
  } catch (e) { ElMessage.error(e.response?.data?.message || '失败'); }
  finally { submitting.value = false; }
}

function confirmDeleteSwitchRule(row) {
  ElMessageBox.confirm(`删除规则「${row.name}」？`, '确认', { type: 'warning' })
    .then(async () => { await deleteAutoSwitchRule(row.id); ElMessage.success('已删除'); await loadSwitchRules(); await loadStats(); })
    .catch(() => {});
}

async function handleEvaluateSwitch() {
  try { const { data } = await evaluateAutoSwitch(); ElMessage.success(`评估完成，${data.data.length} 条建议`); } catch {}
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
