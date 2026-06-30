<template>
  <div class="watermark-management">
    <el-page-header :content="'暗水印与防篡改'" @back="$router.back()" />

    <!-- 仪表盘概览 -->
    <el-row :gutter="16" class="dashboard-cards">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-label">活跃水印数</div>
            <div class="stat-value">{{ dashboard.active_watermarks ?? 0 }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-label">水印总量</div>
            <div class="stat-value">{{ dashboard.total_watermarks ?? 0 }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-label">未解决事件</div>
            <div class="stat-value warn">{{ dashboard.unresolved_events ?? 0 }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-label">今日新增篡改</div>
            <div class="stat-value">{{ dashboard.today_events ?? 0 }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 选项卡 -->
    <el-tabs v-model="activeTab" type="border-card" class="watermark-tabs">
      <!-- 水印列表 -->
      <el-tab-pane label="水印列表" name="watermarks">
        <div class="tab-actions">
          <el-button type="primary" @click="showEmbedDialog = true">
            <el-icon><Plus /></el-icon>嵌入暗水印
          </el-button>
          <el-input
            v-model="searchKeyword"
            placeholder="搜索水印"
            clearable
            style="width:260px"
            @keyup.enter="loadWatermarks"
          />
        </div>
        <el-table :data="watermarks.data" v-loading="loadingWatermarks" stripe>
          <el-table-column prop="watermark_key" label="水印 Key" min-width="180" show-overflow-tooltip />
          <el-table-column prop="algorithm" label="算法" width="140">
            <template #default="{ row }">
              <el-tag :type="row.algorithm === 'forensic_stealth' ? 'warning' : 'info'" effect="plain">
                {{ row.algorithm }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="embed_location" label="嵌入位置" width="120" />
          <el-table-column label="License" min-width="140">
            <template #default="{ row }">
              <span>{{ row.license?.license_key ?? '-' }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="extraction_attempts" label="提取次数" width="100" align="center" />
          <el-table-column prop="status" label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="row.status === 'active' ? 'success' : 'danger'" effect="plain" size="small">
                {{ row.status }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="创建时间" width="170" />
          <el-table-column label="操作" width="250" fixed="right">
            <template #default="{ row }">
              <el-button size="small" text @click="handleExtract(row)">提取</el-button>
              <el-button size="small" text @click="handleTrace(row)">溯源</el-button>
              <el-button
                size="small"
                text
                type="danger"
                :disabled="row.status !== 'active'"
                @click="handleRevoke(row)"
              >吊销</el-button>
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
      <el-tab-pane label="溯源追踪" name="traces">
        <div class="tab-actions">
          <el-button type="primary" @click="showCreateTraceDialog = true">
            <el-icon><Plus /></el-icon>创建溯源记录
          </el-button>
        </div>
        <el-table :data="traces.data" v-loading="loadingTraces" stripe>
          <el-table-column label="水印" min-width="150">
            <template #default="{ row }">
              {{ row.watermark?.watermark_key ?? '-' }}
            </template>
          </el-table-column>
          <el-table-column prop="trace_type" label="溯源类型" width="100">
            <template #default="{ row }">
              <el-tag effect="plain" size="small">{{ row.trace_type }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="source" label="来源" width="120" />
          <el-table-column prop="leak_url" label="泄露 URL" min-width="200" show-overflow-tooltip />
          <el-table-column prop="confidence" label="可信度" width="110">
            <template #default="{ row }">
              <el-tag :type="confidenceType(row.confidence)" effect="plain" size="small">
                {{ row.confidence }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="notes" label="备注" min-width="160" show-overflow-tooltip />
          <el-table-column prop="operator?.name" label="操作人" width="120" />
          <el-table-column prop="created_at" label="创建时间" width="170" />
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
      <el-tab-pane label="防篡改事件" name="events">
        <el-table :data="events" v-loading="loadingEvents" stripe>
          <el-table-column prop="event_type" label="事件类型" width="130" />
          <el-table-column prop="severity" label="严重级别" width="100">
            <template #default="{ row }">
              <el-tag :type="severityType(row.severity)" effect="plain" size="small">{{ row.severity }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="license_key" label="License" width="160" />
          <el-table-column prop="source_ip" label="来源 IP" width="130" />
          <el-table-column label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="row.is_resolved ? 'success' : 'danger'" effect="plain" size="small">
                {{ row.is_resolved ? '已解决' : '未解决' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="resolution" label="解决方案" min-width="160" show-overflow-tooltip />
          <el-table-column prop="created_at" label="创建时间" width="170" />
          <el-table-column label="操作" width="120">
            <template #default="{ row }">
              <el-button size="small" text :disabled="row.is_resolved" @click="handleResolveEvent(row)">解决</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 防篡改策略 -->
      <el-tab-pane label="策略配置" name="policies">
        <el-table :data="policies" v-loading="loadingPolicies" stripe>
          <el-table-column prop="rule_name" label="规则名称" min-width="160" />
          <el-table-column prop="rule_type" label="规则类型" width="100" />
          <el-table-column prop="threshold" label="阈值" width="80" align="center" />
          <el-table-column prop="cooldown_seconds" label="冷却时间" width="120">
            <template #default="{ row }">{{ row.cooldown_seconds }}s</template>
          </el-table-column>
          <el-table-column prop="severity" label="严重级别" width="90">
            <template #default="{ row }">
              <el-tag :type="severityType(row.severity)" effect="plain" size="small">{{ row.severity }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="is_active" label="状态" width="90">
            <template #default="{ row }">
              <el-switch :model-value="row.is_active" @change="val => handleTogglePolicy(row, val)" />
            </template>
          </el-table-column>
          <el-table-column label="自动恢复" width="100">
            <template #default="{ row }">
              <el-tag v-if="row.auto_recovery?.length" type="success" effect="plain" size="small">已配置</el-tag>
              <span v-else class="text-muted">-</span>
            </template>
          </el-table-column>
          <el-table-column prop="description" label="描述" min-width="200" show-overflow-tooltip />
          <el-table-column label="操作" width="100">
            <template #default="{ row }">
              <el-button size="small" text @click="handleEditPolicy(row)">编辑</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 嵌入暗水印对话框 -->
    <el-dialog v-model="showEmbedDialog" title="嵌入暗水印" width="500px">
      <el-form ref="embedForm" :model="embedFormData" :rules="embedRules" label-width="120px">
        <el-form-item label="License" prop="license_id">
          <el-select v-model="embedFormData.license_id" filterable remote
            :remote-method="searchLicenses" :loading="searchingLicense"
            style="width:100%"
          >
            <el-option v-for="l in licenseOptions" :key="l.id" :label="`#${l.id} ${l.license_key}`" :value="l.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="算法" prop="algorithm">
          <el-select v-model="embedFormData.algorithm" style="width:100%">
            <el-option label="标准水印 (stealth)" value="stealth" />
            <el-option label="隐写暗水印 (forensic_stealth)" value="forensic_stealth" />
          </el-select>
        </el-form-item>
        <el-form-item label="嵌入位置" prop="embed_location">
          <el-select v-model="embedFormData.embed_location" style="width:100%">
            <el-option label="Metadata" value="metadata" />
            <el-option label="License Key" value="license_key" />
            <el-option label="完整性哈希" value="integrity_hash" />
            <el-option label="SDK 响应" value="sdk_response" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showEmbedDialog = false">取消</el-button>
        <el-button type="primary" :loading="embedding" @click="handleEmbed">确认</el-button>
      </template>
    </el-dialog>

    <!-- 提取暗水印结果 -->
    <el-dialog v-model="showExtractDialog" title="提取结果" width="550px">
      <el-descriptions v-if="extractResult.found" :column="1" border>
        <el-descriptions-item label="水印 ID">{{ extractResult.watermark_id }}</el-descriptions-item>
        <el-descriptions-item label="算法">{{ extractResult.algorithm }}</el-descriptions-item>
        <el-descriptions-item label="有效性">
          <el-tag :type="extractResult.valid ? 'success' : 'danger'" effect="plain">
            {{ extractResult.valid ? '有效' : '无效/篡改' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="嵌入时间">{{ extractResult.embedded_at }}</el-descriptions-item>
        <el-descriptions-item label="客户ID">{{ extractResult.customer_id }}</el-descriptions-item>
        <el-descriptions-item label="设备指纹">{{ extractResult.device_fingerprint }}</el-descriptions-item>
        <el-descriptions-item label="IP 地址">{{ extractResult.ip_address }}</el-descriptions-item>
        <el-descriptions-item label="提取次数">{{ extractResult.extraction_count }}</el-descriptions-item>
        <el-descriptions-item label="最后提取">{{ extractResult.last_extracted }}</el-descriptions-item>
      </el-descriptions>
      <el-alert v-else type="warning" :title="extractResult.message" show-icon />
      <template #footer>
        <el-button @click="showExtractDialog = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 溯源结果 -->
    <el-dialog v-model="showTraceDialog" title="溯源结果" width="550px">
      <template v-if="traceResult">
        <el-descriptions :column="1" border>
          <el-descriptions-item label="客户">{{ traceResult.customer?.name ?? traceResult.source_info?.customer_id ?? '-' }}</el-descriptions-item>
          <el-descriptions-item label="License Key">{{ traceResult.license?.license_key ?? '-' }}</el-descriptions-item>
          <el-descriptions-item label="嵌入时间">{{ traceResult.embed_time }}</el-descriptions-item>
        </el-descriptions>
        <h4 style="margin-top:16px">来源信息</h4>
        <pre class="json-view">{{ JSON.stringify(traceResult.source_info, null, 2) }}</pre>
      </template>
      <template #footer>
        <el-button @click="showTraceDialog = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 创建溯源记录对话框 -->
    <el-dialog v-model="showCreateTraceDialog" title="创建溯源记录" width="500px">
      <el-form ref="traceFormRef" :model="traceForm" :rules="traceRules" label-width="120px">
        <el-form-item label="水印" prop="watermark_id">
          <el-select v-model="traceForm.watermark_id" filterable style="width:100%">
            <el-option v-for="w in watermarks.data" :key="w.id" :label="w.watermark_key" :value="w.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="溯源类型" prop="trace_type">
          <el-select v-model="traceForm.trace_type" style="width:100%">
            <el-option label="手动" value="manual" />
            <el-option label="自动" value="auto" />
            <el-option label="API" value="api" />
            <el-option label="Webhook" value="webhook" />
          </el-select>
        </el-form-item>
        <el-form-item label="来源" prop="source">
          <el-input v-model="traceForm.source" />
        </el-form-item>
        <el-form-item label="泄露 URL" prop="leak_url">
          <el-input v-model="traceForm.leak_url" />
        </el-form-item>
        <el-form-item label="可信度" prop="confidence">
          <el-select v-model="traceForm.confidence" style="width:100%">
            <el-option label="低" value="low" />
            <el-option label="中" value="medium" />
            <el-option label="高" value="high" />
            <el-option label="已确认" value="confirmed" />
          </el-select>
        </el-form-item>
        <el-form-item label="备注" prop="notes">
          <el-input v-model="traceForm.notes" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateTraceDialog = false">取消</el-button>
        <el-button type="primary" :loading="creatingTrace" @click="handleCreateTrace">确认</el-button>
      </template>
    </el-dialog>

    <!-- 解决事件对话框 -->
    <el-dialog v-model="showResolveDialog" title="解决事件" width="450px">
      <el-input v-model="resolveResolution" type="textarea" :rows="4" placeholder="请描述解决方案" />
      <template #footer>
        <el-button @click="showResolveDialog = false">取消</el-button>
        <el-button type="primary" :loading="resolving" @click="handleDoResolve">确认</el-button>
      </template>
    </el-dialog>

    <!-- 编辑策略对话框 -->
    <el-dialog v-model="showPolicyDialog" title="编辑策略" width="500px">
      <el-form ref="policyFormRef" :model="policyForm" label-width="130px">
        <el-form-item label="阈值">
          <el-input-number v-model="policyForm.threshold" :min="1" />
        </el-form-item>
        <el-form-item label="冷却时间">
          <el-input-number v-model="policyForm.cooldown_seconds" :min="0" /> 秒
        </el-form-item>
        <el-form-item label="严重级别">
          <el-select v-model="policyForm.severity" style="width:100%">
            <el-option label="低" value="low" />
            <el-option label="中" value="medium" />
            <el-option label="高" value="high" />
            <el-option label="严重" value="critical" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="policyForm.is_active" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPolicyDialog = false">取消</el-button>
        <el-button type="primary" :loading="savingPolicy" @click="handleSavePolicy">确认</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
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

// ─── 仪表盘 ───
const dashboard = ref({})

// ─── 选项卡 ───
const activeTab = ref('watermarks')

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
const embedRules = { license_id: [{ required: true, message: '请选择 License', trigger: 'change' }] }

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
    .then(res => {
      ElMessage.success('暗水印已嵌入')
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
  ElMessageBox.confirm('确定吊销这个水印？', '确认', { type: 'warning' }).then(() => {
    revokeWatermark(row.id).then(() => {
      ElMessage.success('水印已吊销')
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
const traceRules = {
  watermark_id: [{ required: true, message: '请选择水印', trigger: 'change' }],
  trace_type: [{ required: true, message: '请选择类型', trigger: 'change' }],
}
const creatingTrace = ref(false)

function handleCreateTrace() {
  creatingTrace.value = true
  createTrace(traceForm.value)
    .then(res => {
      ElMessage.success('溯源记录已创建')
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
  if (!resolveResolution.value) { ElMessage.warning('请填写解决描述'); return }
  resolving.value = true
  resolveTamperEvent(resolveEvent.value.id, resolveResolution.value)
    .then(() => {
      ElMessage.success('事件已解决')
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
    ElMessage.success(val ? '策略已启用' : '策略已禁用')
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
      ElMessage.success('策略已更新')
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
