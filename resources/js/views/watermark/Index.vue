<template>
  <div class="watermark-management">
    <el-page-header :content="t('watermark_page.title')" @back="$router.back()" />

    <!-- 仪表盘概览 -->
    <el-row :gutter="16" class="dashboard-cards">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-label">{{ t('watermark_page.stats.active_watermarks') }}</div>
            <div class="stat-value">{{ dashboard.active_watermarks ?? 0 }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-label">{{ t('watermark_page.stats.total_watermarks') }}</div>
            <div class="stat-value">{{ dashboard.total_watermarks ?? 0 }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-label">{{ t('watermark_page.stats.unresolved_events') }}</div>
            <div class="stat-value warn">{{ dashboard.unresolved_events ?? 0 }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-label">{{ t('watermark_page.stats.today_events') }}</div>
            <div class="stat-value">{{ dashboard.today_events ?? 0 }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 选项卡 -->
    <el-tabs v-model="activeTab" type="border-card" class="watermark-tabs">
      <!-- 水印列表 -->
      <el-tab-pane :label="t('watermark_page.tabs.watermarks')" name="watermarks">
        <div class="tab-actions">
          <el-button type="primary" @click="showEmbedDialog = true">
            <el-icon><Plus /></el-icon>{{ t('watermark_page.embed_btn') }}
          </el-button>
          <el-input
            v-model="searchKeyword"
            :placeholder="t('watermark_page.search_ph')"
            clearable
            style="width:260px"
            @keyup.enter="loadWatermarks"
          />
        </div>
        <el-table :data="watermarks.data" v-loading="loadingWatermarks" stripe>
          <el-table-column prop="watermark_key" :label="t('watermark_page.cols.watermark_key')" min-width="180" show-overflow-tooltip />
          <el-table-column prop="algorithm" :label="t('watermark_page.cols.algorithm')" width="140">
            <template #default="{ row }">
              <el-tag :type="row.algorithm === 'forensic_stealth' ? 'warning' : 'info'" effect="plain">
                {{ algorithmLabel(row.algorithm) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="embed_location" :label="t('watermark_page.cols.embed_location')" width="120">
            <template #default="{ row }">{{ embedLocationLabel(row.embed_location) }}</template>
          </el-table-column>
          <el-table-column :label="t('watermark_page.cols.license')" min-width="140">
            <template #default="{ row }">
              <span>{{ row.license?.license_key ?? '-' }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="extraction_attempts" :label="t('watermark_page.cols.extraction_attempts')" width="100" align="center" />
          <el-table-column prop="status" :label="t('watermark_page.cols.status')" width="100">
            <template #default="{ row }">
              <el-tag :type="row.status === 'active' ? 'success' : 'danger'" effect="plain" size="small">
                {{ statusLabel(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" :label="t('watermark_page.cols.created_at')" width="170" />
          <el-table-column :label="t('watermark_page.cols.actions')" width="250" fixed="right">
            <template #default="{ row }">
              <el-button size="small" text @click="handleExtract(row)">{{ t('watermark_page.extract') }}</el-button>
              <el-button size="small" text @click="handleTrace(row)">{{ t('watermark_page.trace') }}</el-button>
              <el-button
                size="small"
                text
                type="danger"
                :disabled="row.status !== 'active'"
                @click="handleRevoke(row)"
              >{{ t('watermark_page.revoke') }}</el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="pagination-wrap">
          <el-pagination
            v-model:current-page="watermarkPage"
            :page-size="20"
            :total="watermarks.total ?? 0"
            layout="prev, pager, next"
            background
            small
            @current-change="loadWatermarks"
          />
        </div>
      </el-tab-pane>

      <!-- 溯源审计 -->
      <el-tab-pane :label="t('watermark_page.tabs.traces')" name="traces">
        <div class="tab-actions">
          <el-button type="primary" @click="showCreateTraceDialog = true">
            <el-icon><Plus /></el-icon>{{ t('watermark_page.create_trace') }}
          </el-button>
        </div>
        <el-table :data="traces.data" v-loading="loadingTraces" stripe>
          <el-table-column :label="t('watermark_page.cols.watermark')" min-width="150">
            <template #default="{ row }">
              {{ row.watermark?.watermark_key ?? '-' }}
            </template>
          </el-table-column>
          <el-table-column prop="trace_type" :label="t('watermark_page.cols.trace_type')" width="100">
            <template #default="{ row }">
              <el-tag effect="plain" size="small">{{ traceTypeLabel(row.trace_type) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="source" :label="t('watermark_page.cols.source')" width="120" />
          <el-table-column prop="leak_url" :label="t('watermark_page.cols.leak_url')" min-width="200" show-overflow-tooltip />
          <el-table-column prop="confidence" :label="t('watermark_page.cols.confidence')" width="110">
            <template #default="{ row }">
              <el-tag :type="confidenceType(row.confidence)" effect="plain" size="small">
                {{ confidenceLabel(row.confidence) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="notes" :label="t('watermark_page.cols.notes')" min-width="160" show-overflow-tooltip />
          <el-table-column prop="operator?.name" :label="t('watermark_page.cols.operator')" width="120" />
          <el-table-column prop="created_at" :label="t('watermark_page.cols.created_at')" width="170" />
        </el-table>
        <div class="pagination-wrap">
          <el-pagination
            v-model:current-page="tracePage"
            :page-size="20"
            :total="traces.total ?? 0"
            layout="prev, pager, next"
            background
            small
            @current-change="loadTraces"
          />
        </div>
      </el-tab-pane>

      <!-- 防篡改事件 -->
      <el-tab-pane :label="t('watermark_page.tabs.events')" name="events">
        <el-table :data="events" v-loading="loadingEvents" stripe>
          <el-table-column prop="event_type" :label="t('watermark_page.cols.event_type')" width="130" />
          <el-table-column prop="severity" :label="t('watermark_page.cols.severity')" width="100">
            <template #default="{ row }">
              <el-tag :type="severityType(row.severity)" effect="plain" size="small">{{ severityLabel(row.severity) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="license_key" :label="t('watermark_page.cols.license')" width="160" />
          <el-table-column prop="source_ip" :label="t('watermark_page.cols.source_ip')" width="130" />
          <el-table-column :label="t('watermark_page.cols.status')" width="100">
            <template #default="{ row }">
              <el-tag :type="row.is_resolved ? 'success' : 'danger'" effect="plain" size="small">
                {{ row.is_resolved ? t('watermark_page.status.resolved') : t('watermark_page.status.unresolved') }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="resolution" :label="t('watermark_page.cols.resolution')" min-width="160" show-overflow-tooltip />
          <el-table-column prop="created_at" :label="t('watermark_page.cols.created_at')" width="170" />
          <el-table-column :label="t('watermark_page.cols.actions')" width="120">
            <template #default="{ row }">
              <el-button size="small" text :disabled="row.is_resolved" @click="handleResolveEvent(row)">{{ t('watermark_page.resolve') }}</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 防篡改策略 -->
      <el-tab-pane :label="t('watermark_page.tabs.policies')" name="policies">
        <el-table :data="policies" v-loading="loadingPolicies" stripe>
          <el-table-column prop="rule_name" :label="t('watermark_page.cols.rule_name')" min-width="160" />
          <el-table-column prop="rule_type" :label="t('watermark_page.cols.rule_type')" width="100" />
          <el-table-column prop="threshold" :label="t('watermark_page.cols.threshold')" width="80" align="center" />
          <el-table-column prop="cooldown_seconds" :label="t('watermark_page.cols.cooldown_seconds')" width="120">
            <template #default="{ row }">{{ row.cooldown_seconds }}{{ t('watermark_page.unit_seconds') }}</template>
          </el-table-column>
          <el-table-column prop="severity" :label="t('watermark_page.cols.severity')" width="90">
            <template #default="{ row }">
              <el-tag :type="severityType(row.severity)" effect="plain" size="small">{{ severityLabel(row.severity) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="is_active" :label="t('watermark_page.cols.status')" width="90">
            <template #default="{ row }">
              <el-switch :model-value="row.is_active" @change="val => handleTogglePolicy(row, val)" />
            </template>
          </el-table-column>
          <el-table-column :label="t('watermark_page.cols.auto_recovery')" width="100">
            <template #default="{ row }">
              <el-tag v-if="row.auto_recovery?.length" type="success" effect="plain" size="small">{{ t('watermark_page.configured') }}</el-tag>
              <span v-else class="text-muted">-</span>
            </template>
          </el-table-column>
          <el-table-column prop="description" :label="t('watermark_page.cols.description')" min-width="200" show-overflow-tooltip />
          <el-table-column :label="t('watermark_page.cols.actions')" width="100">
            <template #default="{ row }">
              <el-button size="small" text @click="handleEditPolicy(row)">{{ t('actions.edit') }}</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 嵌入暗水印对话框 -->
    <el-dialog v-model="showEmbedDialog" :title="t('watermark_page.dialogs.embed_title')" width="500px">
      <el-form ref="embedForm" :model="embedFormData" :rules="embedRules" label-width="120px">
        <el-form-item :label="t('watermark_page.cols.license')" prop="license_id">
          <el-select v-model="embedFormData.license_id" filterable remote
            :remote-method="searchLicenses" :loading="searchingLicense"
            style="width:100%"
          >
            <el-option v-for="l in licenseOptions" :key="l.id" :label="`#${l.id} ${l.license_key}`" :value="l.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('watermark_page.cols.algorithm')" prop="algorithm">
          <el-select v-model="embedFormData.algorithm" style="width:100%">
            <el-option
              v-for="opt in algorithmOptions"
              :key="opt.value"
              :label="opt.label"
              :value="opt.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('watermark_page.cols.embed_location')" prop="embed_location">
          <el-select v-model="embedFormData.embed_location" style="width:100%">
            <el-option
              v-for="opt in embedLocationOptions"
              :key="opt.value"
              :label="opt.label"
              :value="opt.value"
            />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showEmbedDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="embedding" @click="handleEmbed">{{ t('actions.confirm') }}</el-button>
      </template>
    </el-dialog>

    <!-- 提取暗水印结果 -->
    <el-dialog v-model="showExtractDialog" :title="t('watermark_page.dialogs.extract_title')" width="550px">
      <el-descriptions v-if="extractResult.found" :column="1" border>
        <el-descriptions-item :label="t('watermark_page.extract.watermark_id')">{{ extractResult.watermark_id }}</el-descriptions-item>
        <el-descriptions-item :label="t('watermark_page.cols.algorithm')">{{ algorithmLabel(extractResult.algorithm) }}</el-descriptions-item>
        <el-descriptions-item :label="t('watermark_page.extract.validity')">
          <el-tag :type="extractResult.valid ? 'success' : 'danger'" effect="plain">
            {{ extractResult.valid ? t('watermark_page.extract.valid') : t('watermark_page.extract.invalid') }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('watermark_page.extract.embedded_at')">{{ extractResult.embedded_at }}</el-descriptions-item>
        <el-descriptions-item :label="t('watermark_page.extract.customer_id')">{{ extractResult.customer_id }}</el-descriptions-item>
        <el-descriptions-item :label="t('watermark_page.extract.device_fingerprint')">{{ extractResult.device_fingerprint }}</el-descriptions-item>
        <el-descriptions-item :label="t('watermark_page.extract.ip_address')">{{ extractResult.ip_address }}</el-descriptions-item>
        <el-descriptions-item :label="t('watermark_page.extract.extraction_count')">{{ extractResult.extraction_count }}</el-descriptions-item>
        <el-descriptions-item :label="t('watermark_page.extract.last_extracted')">{{ extractResult.last_extracted }}</el-descriptions-item>
      </el-descriptions>
      <el-alert v-else type="warning" :title="extractResult.message" show-icon />
      <template #footer>
        <el-button @click="showExtractDialog = false">{{ t('actions.close') }}</el-button>
      </template>
    </el-dialog>

    <!-- 溯源结果 -->
    <el-dialog v-model="showTraceDialog" :title="t('watermark_page.dialogs.trace_title')" width="550px">
      <template v-if="traceResult">
        <el-descriptions :column="1" border>
          <el-descriptions-item :label="t('watermark_page.trace_result.customer')">{{ traceResult.customer?.name ?? traceResult.source_info?.customer_id ?? '-' }}</el-descriptions-item>
          <el-descriptions-item :label="t('watermark_page.trace_result.license_key')">{{ traceResult.license?.license_key ?? '-' }}</el-descriptions-item>
          <el-descriptions-item :label="t('watermark_page.trace_result.embed_time')">{{ traceResult.embed_time }}</el-descriptions-item>
        </el-descriptions>
        <h4 style="margin-top:16px">{{ t('watermark_page.trace_result.source_info') }}</h4>
        <pre class="json-view">{{ JSON.stringify(traceResult.source_info, null, 2) }}</pre>
      </template>
      <template #footer>
        <el-button @click="showTraceDialog = false">{{ t('actions.close') }}</el-button>
      </template>
    </el-dialog>

    <!-- 创建溯源记录对话框 -->
    <el-dialog v-model="showCreateTraceDialog" :title="t('watermark_page.dialogs.create_trace_title')" width="500px">
      <el-form ref="traceFormRef" :model="traceForm" :rules="traceRules" label-width="120px">
        <el-form-item :label="t('watermark_page.cols.watermark')" prop="watermark_id">
          <el-select v-model="traceForm.watermark_id" filterable style="width:100%">
            <el-option v-for="w in watermarks.data" :key="w.id" :label="w.watermark_key" :value="w.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('watermark_page.cols.trace_type')" prop="trace_type">
          <el-select v-model="traceForm.trace_type" style="width:100%">
            <el-option
              v-for="opt in traceTypeOptions"
              :key="opt.value"
              :label="opt.label"
              :value="opt.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('watermark_page.cols.source')" prop="source">
          <el-input v-model="traceForm.source" />
        </el-form-item>
        <el-form-item :label="t('watermark_page.cols.leak_url')" prop="leak_url">
          <el-input v-model="traceForm.leak_url" />
        </el-form-item>
        <el-form-item :label="t('watermark_page.cols.confidence')" prop="confidence">
          <el-select v-model="traceForm.confidence" style="width:100%">
            <el-option
              v-for="opt in confidenceOptions"
              :key="opt.value"
              :label="opt.label"
              :value="opt.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('watermark_page.cols.notes')" prop="notes">
          <el-input v-model="traceForm.notes" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateTraceDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="creatingTrace" @click="handleCreateTrace">{{ t('actions.confirm') }}</el-button>
      </template>
    </el-dialog>

    <!-- 解决事件对话框 -->
    <el-dialog v-model="showResolveDialog" :title="t('watermark_page.dialogs.resolve_title')" width="450px">
      <el-input v-model="resolveResolution" type="textarea" :rows="4" :placeholder="t('watermark_page.resolve_ph')" />
      <template #footer>
        <el-button @click="showResolveDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="resolving" @click="handleDoResolve">{{ t('actions.confirm') }}</el-button>
      </template>
    </el-dialog>

    <!-- 编辑策略对话框 -->
    <el-dialog v-model="showPolicyDialog" :title="t('watermark_page.dialogs.edit_policy_title')" width="500px">
      <el-form ref="policyFormRef" :model="policyForm" label-width="130px">
        <el-form-item :label="t('watermark_page.cols.threshold')">
          <el-input-number v-model="policyForm.threshold" :min="1" />
        </el-form-item>
        <el-form-item :label="t('watermark_page.cols.cooldown_seconds')">
          <el-input-number v-model="policyForm.cooldown_seconds" :min="0" /> {{ t('watermark_page.unit_seconds') }}
        </el-form-item>
        <el-form-item :label="t('watermark_page.cols.severity')">
          <el-select v-model="policyForm.severity" style="width:100%">
            <el-option
              v-for="opt in severityOptions"
              :key="opt.value"
              :label="opt.label"
              :value="opt.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('watermark_page.cols.status')">
          <el-switch v-model="policyForm.is_active" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPolicyDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="savingPolicy" @click="handleSavePolicy">{{ t('actions.confirm') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Plus } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getWatermarkDashboard,
  getWatermarks,
  embedWatermark,
  extractWatermark,
  traceWatermark,
  revokeWatermark,
  getTraces,
  createTrace,
  getTamperEvents,
  resolveTamperEvent,
  getPolicies,
  updatePolicy,
} from '@/api/watermark'

const { t } = useI18n()

// ─── 仪表盘 ───
const dashboard = ref({})

// ─── 选项卡 ───
const activeTab = ref('watermarks')

// ─── 选项映射 ───
const algorithmOptions = computed(() => [
  { label: t('watermark_page.algorithm.stealth'), value: 'stealth' },
  { label: t('watermark_page.algorithm.forensic_stealth'), value: 'forensic_stealth' },
])

const embedLocationOptions = computed(() => [
  { label: t('watermark_page.embed_location.metadata'), value: 'metadata' },
  { label: t('watermark_page.embed_location.license_key'), value: 'license_key' },
  { label: t('watermark_page.embed_location.integrity_hash'), value: 'integrity_hash' },
  { label: t('watermark_page.embed_location.sdk_response'), value: 'sdk_response' },
])

const traceTypeOptions = computed(() => [
  { label: t('watermark_page.trace_type.manual'), value: 'manual' },
  { label: t('watermark_page.trace_type.auto'), value: 'auto' },
  { label: t('watermark_page.trace_type.api'), value: 'api' },
  { label: t('watermark_page.trace_type.webhook'), value: 'webhook' },
])

const confidenceOptions = computed(() => [
  { label: t('watermark_page.confidence.low'), value: 'low' },
  { label: t('watermark_page.confidence.medium'), value: 'medium' },
  { label: t('watermark_page.confidence.high'), value: 'high' },
  { label: t('watermark_page.confidence.confirmed'), value: 'confirmed' },
])

const severityOptions = computed(() => [
  { label: t('watermark_page.severity.low'), value: 'low' },
  { label: t('watermark_page.severity.medium'), value: 'medium' },
  { label: t('watermark_page.severity.high'), value: 'high' },
  { label: t('watermark_page.severity.critical'), value: 'critical' },
])

const algorithmLabels = computed(() => ({
  stealth: t('watermark_page.algorithm.stealth'),
  forensic_stealth: t('watermark_page.algorithm.forensic_stealth'),
}))

const embedLocationLabels = computed(() => ({
  metadata: t('watermark_page.embed_location.metadata'),
  license_key: t('watermark_page.embed_location.license_key'),
  integrity_hash: t('watermark_page.embed_location.integrity_hash'),
  sdk_response: t('watermark_page.embed_location.sdk_response'),
}))

const traceTypeLabels = computed(() => ({
  manual: t('watermark_page.trace_type.manual'),
  auto: t('watermark_page.trace_type.auto'),
  api: t('watermark_page.trace_type.api'),
  webhook: t('watermark_page.trace_type.webhook'),
}))

const confidenceLabels = computed(() => ({
  low: t('watermark_page.confidence.low'),
  medium: t('watermark_page.confidence.medium'),
  high: t('watermark_page.confidence.high'),
  confirmed: t('watermark_page.confidence.confirmed'),
}))

const severityLabels = computed(() => ({
  low: t('watermark_page.severity.low'),
  medium: t('watermark_page.severity.medium'),
  high: t('watermark_page.severity.high'),
  critical: t('watermark_page.severity.critical'),
}))

const statusLabels = computed(() => ({
  active: t('watermark_page.status.active'),
  revoked: t('watermark_page.status.revoked'),
}))

function algorithmLabel(val) {
  return algorithmLabels.value[val] ?? val
}

function embedLocationLabel(val) {
  return embedLocationLabels.value[val] ?? val
}

function traceTypeLabel(val) {
  return traceTypeLabels.value[val] ?? val
}

function confidenceLabel(val) {
  return confidenceLabels.value[val] ?? val
}

function severityLabel(val) {
  return severityLabels.value[val] ?? val
}

function statusLabel(val) {
  return statusLabels.value[val] ?? val
}

// ─── 水印列表 ───
const watermarks = ref({ data: [], total: 0 })
const watermarkPage = ref(1)
const loadingWatermarks = ref(false)
const searchKeyword = ref('')

function loadWatermarks() {
  loadingWatermarks.value = true
  getWatermarks({ page: watermarkPage.value, per_page: 20, search: searchKeyword.value || undefined })
    .then(res => {
      watermarks.value = res.data ?? res
    })
    .finally(() => { loadingWatermarks.value = false })
}

// ─── 嵌入水印 ───
const showEmbedDialog = ref(false)
const embedFormData = ref({ license_id: null, algorithm: 'forensic_stealth', embed_location: 'metadata' })
const embedding = ref(false)
const embedRules = computed(() => ({
  license_id: [{ required: true, message: t('watermark_page.validation.license_required'), trigger: 'change' }],
}))

const licenseOptions = ref([])
const searchingLicense = ref(false)

function searchLicenses(query) {
  if (!query) return
  searchingLicense.value = true
  import('@/api/license').then(m => m.searchLicenses?.(query) ?? m.default.searchLicenses?.(query))
    .then(res => { licenseOptions.value = res.data ?? [] })
    .catch(() => {})
    .finally(() => { searchingLicense.value = false })
}

function handleEmbed() {
  embedding.value = true
  embedWatermark(embedFormData.value)
    .then(() => {
      ElMessage.success(t('watermark_page.messages.embed_success'))
      showEmbedDialog.value = false
      loadWatermarks()
    })
    .catch(() => {})
    .finally(() => { embedding.value = false })
}

// ─── 提取水印 ───
const showExtractDialog = ref(false)
const extractResult = ref({})

function handleExtract(row) {
  extractWatermark(row.id).then(res => {
    extractResult.value = res.data ?? res
    showExtractDialog.value = true
  })
}

// ─── 溯源 ───
const showTraceDialog = ref(false)
const traceResult = ref(null)

function handleTrace(row) {
  traceWatermark(row.id).then(res => {
    traceResult.value = res.data ?? res
    showTraceDialog.value = true
  })
}

// ─── 吊销水印 ───
function handleRevoke(row) {
  ElMessageBox.confirm(
    t('watermark_page.messages.revoke_confirm'),
    t('watermark_page.messages.revoke_title'),
    { type: 'warning' },
  ).then(() => {
    revokeWatermark(row.id).then(() => {
      ElMessage.success(t('watermark_page.messages.revoke_success'))
      loadWatermarks()
    })
  }).catch(() => {})
}

// ─── 溯源审计 ───
const traces = ref({ data: [], total: 0 })
const tracePage = ref(1)
const loadingTraces = ref(false)

function loadTraces() {
  loadingTraces.value = true
  getTraces({ page: tracePage.value, per_page: 20 })
    .then(res => { traces.value = res.data ?? res })
    .finally(() => { loadingTraces.value = false })
}

const showCreateTraceDialog = ref(false)
const traceForm = ref({ watermark_id: null, trace_type: 'manual', source: '', leak_url: '', confidence: 'medium', notes: '' })
const traceRules = computed(() => ({
  watermark_id: [{ required: true, message: t('watermark_page.validation.watermark_required'), trigger: 'change' }],
  trace_type: [{ required: true, message: t('watermark_page.validation.trace_type_required'), trigger: 'change' }],
}))
const creatingTrace = ref(false)

function handleCreateTrace() {
  creatingTrace.value = true
  createTrace(traceForm.value)
    .then(() => {
      ElMessage.success(t('watermark_page.messages.trace_created'))
      showCreateTraceDialog.value = false
      traceForm.value = { watermark_id: null, trace_type: 'manual', source: '', leak_url: '', confidence: 'medium', notes: '' }
      loadTraces()
    })
    .catch(() => {})
    .finally(() => { creatingTrace.value = false })
}

// ─── 防篡改事件 ───
const events = ref([])
const loadingEvents = ref(false)
const showResolveDialog = ref(false)
const resolveEvent = ref(null)
const resolveResolution = ref('')
const resolving = ref(false)

function loadEvents() {
  loadingEvents.value = true
  getTamperEvents({ limit: 50 })
    .then(res => { events.value = res.data ?? res })
    .finally(() => { loadingEvents.value = false })
}

function handleResolveEvent(row) {
  resolveEvent.value = row
  resolveResolution.value = ''
  showResolveDialog.value = true
}

function handleDoResolve() {
  if (!resolveResolution.value) {
    ElMessage.warning(t('watermark_page.messages.resolve_note_required'))
    return
  }
  resolving.value = true
  resolveTamperEvent(resolveEvent.value.id, resolveResolution.value)
    .then(() => {
      ElMessage.success(t('watermark_page.messages.event_resolved'))
      showResolveDialog.value = false
      loadEvents()
    })
    .catch(() => {})
    .finally(() => { resolving.value = false })
}

// ─── 策略管理 ───
const policies = ref([])
const loadingPolicies = ref(false)
const showPolicyDialog = ref(false)
const editingPolicy = ref(null)
const policyForm = ref({ threshold: 1, cooldown_seconds: 300, severity: 'medium', is_active: true })
const savingPolicy = ref(false)

function loadPolicies() {
  loadingPolicies.value = true
  getPolicies()
    .then(res => { policies.value = res.data ?? res })
    .finally(() => { loadingPolicies.value = false })
}

function handleTogglePolicy(row, val) {
  updatePolicy(row.id, { is_active: val }).then(() => {
    ElMessage.success(val ? t('watermark_page.messages.policy_enabled') : t('watermark_page.messages.policy_disabled'))
  }).catch(() => {})
}

function handleEditPolicy(row) {
  editingPolicy.value = row
  policyForm.value = {
    threshold: row.threshold,
    cooldown_seconds: row.cooldown_seconds,
    severity: row.severity,
    is_active: row.is_active,
  }
  showPolicyDialog.value = true
}

function handleSavePolicy() {
  savingPolicy.value = true
  updatePolicy(editingPolicy.value.id, policyForm.value)
    .then(() => {
      ElMessage.success(t('watermark_page.messages.policy_updated'))
      showPolicyDialog.value = false
      loadPolicies()
    })
    .catch(() => {})
    .finally(() => { savingPolicy.value = false })
}

// ─── 辅助 ───
function confidenceType(val) {
  return { low: 'danger', medium: 'warning', high: 'primary', confirmed: 'success' }[val] ?? 'info'
}
function severityType(val) {
  return { low: 'info', medium: 'warning', high: 'danger', critical: 'danger' }[val] ?? 'info'
}

// ─── 初始化 ───
onMounted(() => {
  getWatermarkDashboard().then(res => { dashboard.value = res.data ?? res })
  loadWatermarks()
  loadTraces()
  loadEvents()
  loadPolicies()
})
</script>

<style scoped>
.watermark-management {
  padding: 20px;
}
.dashboard-cards {
  margin: 16px 0;
}
.stat-card {
  text-align: center;
  padding: 8px 0;
}
.stat-label {
  font-size: 13px;
  color: var(--el-text-color-secondary);
  margin-bottom: 4px;
}
.stat-value {
  font-size: 28px;
  font-weight: 600;
}
.stat-value.warn {
  color: var(--el-color-danger);
}
.watermark-tabs {
  margin-top: 16px;
}
.tab-actions {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
  align-items: center;
}
.pagination-wrap {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}
.json-view {
  background: var(--el-fill-color-light);
  padding: 12px;
  border-radius: 4px;
  font-size: 12px;
  max-height: 300px;
  overflow: auto;
}
.text-muted {
  color: var(--el-text-color-placeholder);
}
</style>
