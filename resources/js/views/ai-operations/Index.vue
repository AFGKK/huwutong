<template>
  <div class="ai-ops-page">
    <el-tabs tab-position="left" v-model="activeTab">
      <!-- ════════════════════════════════════════ -->
      <!-- Tab 1: 知识库自增长 -->
      <!-- ════════════════════════════════════════ -->
      <el-tab-pane label="📚 知识库自增长" name="kb">
        <div class="tab-header"><h3>📚 AI 知识库自增长</h3></div>
        <el-row :gutter="16" class="stats-row">
          <el-col :span="6" v-for="s in kbStats" :key="s.label">
            <el-card shadow="hover"><div class="stat-card">
              <div class="stat-num">{{ s.value }}</div>
              <div class="stat-label">{{ s.label }}</div>
            </div></el-card>
          </el-col>
        </el-row>
        <div class="action-bar">
          <el-button type="primary" @click="runKbAutoGrow" :loading="kbRunning">▶ 手动扫描</el-button>
        </div>
        <el-table :data="kbDrafts" border stripe v-loading="kbLoading" style="width:100%">
          <el-table-column prop="id" label="ID" width="60"/>
          <el-table-column prop="title" label="标题" min-width="180"/>
          <el-table-column prop="source_type" label="来源" width="120">
            <template #default="{row}">{{ sourceLabel(row.source_type) }}</template>
          </el-table-column>
          <el-table-column prop="confidence" label="置信度" width="100">
            <template #default="{row}"><el-tag :type="confidenceType(row.confidence)">{{ row.confidence }}</el-tag></template>
          </el-table-column>
          <el-table-column label="操作" width="160" fixed="right">
            <template #default="{row}">
              <el-button size="small" type="success" @click="approveDraft(row.id)">通过</el-button>
              <el-button size="small" type="danger" @click="rejectDraft(row.id)">拒绝</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ════════════════════════════════════════ -->
      <!-- Tab 2: 深度研究 -->
      <!-- ════════════════════════════════════════ -->
      <el-tab-pane label="🔬 深度研究" name="research">
        <div class="tab-header"><h3>🔬 AI 深度研究</h3></div>
        <el-card shadow="never" class="input-card">
          <el-input v-model="researchQuery" type="textarea" :rows="3" placeholder="输入研究问题，如「分析2026年SaaS License管理趋势」"/>
          <el-button type="primary" @click="startResearch" :loading="researchLoading" style="margin-top:12px">🚀 开始研究</el-button>
        </el-card>
        <el-table :data="researchHistory" border v-loading="researchListLoading" style="width:100%;margin-top:16px">
          <el-table-column prop="id" label="ID" width="60"/>
          <el-table-column prop="query" label="研究问题" min-width="250"/>
          <el-table-column prop="status" label="状态" width="100">
            <template #default="{row}">
              <el-tag :type="row.status==='completed'?'success':row.status==='failed'?'danger':'warning'">
                {{ {completed:'完成',failed:'失败',in_progress:'进行中',pending:'等待中'}[row.status] || row.status }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="source_count" label="来源数" width="80"/>
          <el-table-column prop="created_at" label="时间" width="170"/>
          <el-table-column label="操作" width="120">
            <template #default="{row}">
              <el-button size="small" @click="viewResearch(row)">查看</el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-dialog v-model="researchDialog" :title="'📄 '+ (currentResearch?.query || '')" width="70%" top="5vh">
          <div class="report-content" v-html="renderMarkdown(currentResearch?.report || '')"></div>
        </el-dialog>
      </el-tab-pane>

      <!-- ════════════════════════════════════════ -->
      <!-- Tab 3: 搜索增强 -->
      <!-- ════════════════════════════════════════ -->
      <el-tab-pane label="🔍 搜索增强" name="search">
        <div class="tab-header"><h3>🔍 AI 搜索增强</h3></div>
        <el-row :gutter="16" class="stats-row">
          <el-col :span="8"><el-card shadow="hover"><div class="stat-card"><div class="stat-num">{{ searchStats?.total_documents || 0 }}</div><div class="stat-label">文档总数</div></div></el-card></el-col>
          <el-col :span="8"><el-card shadow="hover"><div class="stat-card"><div class="stat-num">{{ searchStats?.indexed_documents || 0 }}</div><div class="stat-label">已索引</div></div></el-card></el-col>
          <el-col :span="8"><el-card shadow="hover"><div class="stat-card"><div class="stat-num">{{ searchStats?.index_coverage || 0 }}%</div><div class="stat-label">索引覆盖率</div></div></el-card></el-col>
        </el-row>
        <div class="action-bar">
          <el-button type="warning" @click="rebuildIndex" :loading="rebuilding">🔄 重建索引</el-button>
        </div>
        <el-card shadow="never" class="input-card" style="margin-top:16px">
          <el-input v-model="searchQuery" placeholder="输入搜索关键词测试搜索效果"/>
          <el-button type="primary" @click="testSearch" :loading="searchLoading" style="margin-top:12px">搜索</el-button>
          <div v-if="searchResults?.results" style="margin-top:12px">
            <el-tag>找到 {{ searchResults.total }} 条结果</el-tag>
            <div v-for="r in searchResults.results" :key="r.id" class="search-item">
              <strong>{{ r.title }}</strong><br>
              <span class="text-muted">{{ r.content?.substring(0,150) }}...</span>
              <el-tag size="small" :type="r.score > 0.7 ? 'success' : 'warning'" style="margin-left:8px">{{ r.score }}</el-tag>
            </div>
          </div>
        </el-card>
      </el-tab-pane>

      <!-- ════════════════════════════════════════ -->
      <!-- Tab 4: 幻觉检测 -->
      <!-- ════════════════════════════════════════ -->
      <el-tab-pane label="✅ 幻觉检测" name="hallucination">
        <div class="tab-header"><h3>✅ AI 幻觉检测</h3></div>
        <el-row :gutter="16" class="stats-row">
          <el-col :span="6" v-for="s in hcStatsArr" :key="s.label">
            <el-card shadow="hover"><div class="stat-card"><div class="stat-num">{{ s.value }}</div><div class="stat-label">{{ s.label }}</div></div></el-card>
          </el-col>
        </el-row>
        <el-card shadow="never" class="input-card">
          <el-input v-model="hcText" type="textarea" :rows="4" placeholder="输入 AI 生成文本进行幻觉检测"/>
          <el-button type="primary" @click="testHallucination" :loading="hcLoading" style="margin-top:12px">检测</el-button>
          <div v-if="hcResult" style="margin-top:12px">
            <el-tag :type="hcResult.verdict==='trustworthy'?'success':'danger'" size="large">
              裁决: {{ {trustworthy:'可信',pending:'待确认',unverified:'未验证',contradicted:'矛盾'}[hcResult.verdict] || hcResult.verdict }}
            </el-tag>
            <el-tag style="margin-left:8px">可信度: {{ hcResult.overall_score }}</el-tag>
            <div v-for="r in hcResult.results" :key="r.claim" class="hc-item">
              <el-tag size="small" :type="r.status==='verified'?'success':'warning'">{{ r.status }}</el-tag>
              <span>{{ r.claim?.substring(0,80) }}...</span>
            </div>
          </div>
        </el-card>
      </el-tab-pane>

      <!-- ════════════════════════════════════════ -->
      <!-- Tab 5: 内容溯源 -->
      <!-- ════════════════════════════════════════ -->
      <el-tab-pane label="🔐 内容溯源" name="signature">
        <div class="tab-header"><h3>🔐 AI 内容溯源/数字签名</h3></div>
        <el-row :gutter="16" class="stats-row">
          <el-col :span="8"><el-card shadow="hover"><div class="stat-card"><div class="stat-num">{{ sigStats?.total_signed || 0 }}</div><div class="stat-label">已签名</div></div></el-card></el-col>
          <el-col :span="8"><el-card shadow="hover"><div class="stat-card"><div class="stat-num">{{ Object.keys(sigStats?.by_source || {}).length }}</div><div class="stat-label">来源类型</div></div></el-card></el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-card shadow="never" class="input-card">
              <h4>✍️ 签名</h4>
              <el-input v-model="signContent" type="textarea" :rows="3" placeholder="输入要签名内容"/>
              <el-button type="primary" @click="doSign" :loading="signLoading" style="margin-top:12px">签名</el-button>
              <div v-if="signResult" style="margin-top:8px">
                <div>哈希: <code>{{ signResult.hash?.substring(0,20) }}...</code></div>
                <el-tag>已存证</el-tag>
              </div>
            </el-card>
          </el-col>
          <el-col :span="12">
            <el-card shadow="never" class="input-card">
              <h4>🔍 验证</h4>
              <el-input v-model="verifyContent" type="textarea" :rows="3" placeholder="输入要验证内容"/>
              <el-button type="success" @click="doVerify" :loading="verifyLoading" style="margin-top:12px">验证</el-button>
              <div v-if="verifyResult" style="margin-top:8px">
                <el-tag :type="verifyResult.verified ? 'success' : 'danger'">
                  {{ verifyResult.verified ? '✅ 内容可信' : '❌ ' + verifyResult.message }}
                </el-tag>
              </div>
            </el-card>
          </el-col>
        </el-row>
      </el-tab-pane>

      <!-- ════════════════════════════════════════ -->
      <!-- Tab 6: 自动化运营 -->
      <!-- ════════════════════════════════════════ -->
      <el-tab-pane label="🤖 自动化运营" name="quality">
        <div class="tab-header"><h3>🤖 AI 自动化运营</h3></div>
        <el-row :gutter="16" class="stats-row">
          <el-col :span="6" v-for="s in qualityStatsArr" :key="s.label">
            <el-card shadow="hover"><div class="stat-card"><div class="stat-num">{{ s.value }}</div><div class="stat-label">{{ s.label }}</div></div></el-card>
          </el-col>
        </el-row>
        <div class="action-bar">
          <el-button type="primary" @click="runQualityOps" :loading="qualityRunning">▶ 执行运营任务</el-button>
        </div>
        <el-card shadow="never" class="input-card">
          <el-input v-model="qualityText" placeholder="输入文本测试质量评分"/>
          <el-button @click="testQuality" :loading="qualityTestLoading" style="margin-top:12px">评分测试</el-button>
          <div v-if="qualityScore" style="margin-top:8px">
            <el-tag :type="qualityScore.score>0.7?'success':'danger'">得分: {{ qualityScore.score }}</el-tag>
            <el-tag v-for="i in qualityScore.issues" :key="i" type="warning" style="margin-left:4px">{{ i }}</el-tag>
          </div>
        </el-card>
      </el-tab-pane>

      <!-- ════════════════════════════════════════ -->
      <!-- Tab 7: 电子签名 -->
      <!-- ════════════════════════════════════════ -->
      <el-tab-pane label="📝 电子签名" name="esign">
        <div class="tab-header"><h3>📝 电子签名消息</h3></div>
        <el-row :gutter="16" class="stats-row">
          <el-col :span="6" v-for="s in esignStatsArr" :key="s.label">
            <el-card shadow="hover"><div class="stat-card"><div class="stat-num">{{ s.value }}</div><div class="stat-label">{{ s.label }}</div></div></el-card>
          </el-col>
        </el-row>
        <el-table :data="esignPending" border v-loading="esignLoading" style="width:100%">
          <el-table-column label="签署类型" width="100">
            <template #default="{row}">{{ {single:'单方',multi:'多方',approval:'审批'}[row.type] || row.type }}</template>
          </el-table-column>
          <el-table-column label="签署人" width="120">
            <template #default="{row}">{{ row.user?.name || '未知' }}</template>
          </el-table-column>
          <el-table-column prop="sequence" label="顺序" width="60"/>
          <el-table-column prop="status" label="状态" width="100">
            <template #default="{row}">
              <el-tag :type="row.status==='signed'?'success':row.status==='rejected'?'danger':'warning'">
                {{ {pending:'待签',signed:'已签',rejected:'拒绝',expired:'过期'}[row.status] || row.status }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="发起时间" width="170"/>
        </el-table>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '@/api/aiOperations'

const activeTab = ref('kb')

// ── KB Auto Grow ──
const kbStats = ref([])
const kbDrafts = ref([])
const kbLoading = ref(false)
const kbRunning = ref(false)

function sourceLabel(s) {
  return { rag_chat:'AI客服', handoff:'人工客服', forum_post:'广场', im_chat:'群聊' }[s] || s
}
function confidenceType(v) { return v > 0.7 ? 'success' : v > 0.4 ? 'warning' : 'danger' }

async function loadKb() {
  try {
    const [sr, dr] = await Promise.all([api.kbAutoGrowStats(), api.kbAutoGrowPending()])
    const s = sr.data?.data || {}
    kbStats.value = [
      { label:'待审核', value: s.pending || 0 },
      { label:'已通过', value: s.approved || 0 },
      { label:'已拒绝', value: s.rejected || 0 },
    ]
    kbDrafts.value = dr.data?.data?.data || []
  } catch { /* ignore */ }
}
async function runKbAutoGrow() {
  kbRunning.value = true
  await api.kbAutoGrowRun()
  ElMessage.success('扫描完成')
  kbRunning.value = false
  loadKb()
}
async function approveDraft(id) { await api.kbAutoGrowApprove(id); ElMessage.success('已通过'); loadKb() }
async function rejectDraft(id) { await api.kbAutoGrowReject(id); ElMessage.success('已拒绝'); loadKb() }

// ── Deep Research ──
const researchQuery = ref('')
const researchLoading = ref(false)
const researchHistory = ref([])
const researchListLoading = ref(false)
const researchDialog = ref(false)
const currentResearch = ref(null)

async function loadResearch() {
  researchListLoading.value = true
  try {
    const r = await api.deepResearchHistory()
    researchHistory.value = r.data?.data?.data || []
  } catch {}
  researchListLoading.value = false
}
async function startResearch() {
  if (!researchQuery.value) return
  researchLoading.value = true
  try {
    await api.deepResearchStart(researchQuery.value)
    ElMessage.success('研究已启动')
    researchQuery.value = ''
    loadResearch()
  } catch (e) { ElMessage.error(e.response?.data?.message || '启动失败') }
  researchLoading.value = false
}
async function viewResearch(row) {
  try {
    const r = await api.deepResearchDetail(row.id)
    currentResearch.value = r.data?.data
    researchDialog.value = true
  } catch {}
}
function renderMarkdown(text) {
  if (!text) return ''
  return text.replace(/\n/g, '<br>').replace(/## (.+)/g, '<h3>$1</h3>').replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
}

// ── Vector Search ──
const searchStats = ref({})
const searchQuery = ref('')
const searchLoading = ref(false)
const searchResults = ref(null)
const rebuilding = ref(false)

async function loadSearchStats() {
  try { const r = await api.vectorSearchStats(); searchStats.value = r.data?.data || {} } catch {}
}
async function testSearch() {
  if (!searchQuery.value) return
  searchLoading.value = true
  try { const r = await api.vectorSearch(searchQuery.value); searchResults.value = r.data?.data } catch {}
  searchLoading.value = false
}
async function rebuildIndex() {
  rebuilding.value = true
  await api.vectorSearchRebuild(true)
  ElMessage.success('索引重建完成')
  rebuilding.value = false
  loadSearchStats()
}

// ── Hallucination ──
const hcText = ref('')
const hcLoading = ref(false)
const hcResult = ref(null)
const hcStats = ref({})

const hcStatsArr = computed(() => [
  { label:'总检测', value: hcStats.value.total_checks || 0 },
  { label:'平均可信度', value: hcStats.value.avg_score || 0 },
  { label:'总主张数', value: hcStats.value.total_claims || 0 },
])

async function loadHcStats() {
  try { const r = await api.hallucinationStats(); hcStats.value = r.data?.data || {} } catch {}
}
async function testHallucination() {
  if (!hcText.value) return
  hcLoading.value = true
  try { const r = await api.hallucinationInspect(hcText.value); hcResult.value = r.data?.data } catch {}
  hcLoading.value = false
}

// ── Content Signature ──
const sigStats = ref({})
const signContent = ref('')
const signLoading = ref(false)
const signResult = ref(null)
const verifyContent = ref('')
const verifyLoading = ref(false)
const verifyResult = ref(null)

async function loadSigStats() {
  try { const r = await api.contentStats(); sigStats.value = r.data?.data || {} } catch {}
}
async function doSign() {
  if (!signContent.value) return
  signLoading.value = true
  try { const r = await api.contentSign(signContent.value); signResult.value = r.data?.data } catch {}
  signLoading.value = false
}
async function doVerify() {
  if (!verifyContent.value) return
  verifyLoading.value = true
  try { const r = await api.contentVerify(verifyContent.value); verifyResult.value = r.data?.data } catch {}
  verifyLoading.value = false
}

// ── Content Quality ──
const qualityStats = ref({})
const qualityRunning = ref(false)
const qualityText = ref('')
const qualityTestLoading = ref(false)
const qualityScore = ref(null)

const qualityStatsArr = computed(() => [
  { label:'总记录', value: qualityStats.value.total_records || 0 },
  { label:'平均质量', value: qualityStats.value.avg_quality || 0 },
])

async function loadQualityStats() {
  try { const r = await api.qualityStats(); qualityStats.value = r.data?.data || {} } catch {}
}
async function runQualityOps() {
  qualityRunning.value = true
  await api.qualityRun()
  ElMessage.success('运营任务执行完成')
  qualityRunning.value = false
  loadQualityStats()
}
async function testQuality() {
  if (!qualityText.value) return
  qualityTestLoading.value = true
  try { const r = await api.qualityRate(qualityText.value); qualityScore.value = r.data?.data } catch {}
  qualityTestLoading.value = false
}

// ── Electronic Signature ──
const esignStats = ref({})
const esignPending = ref([])
const esignLoading = ref(false)

const esignStatsArr = computed(() => [
  { label:'总签署', value: esignStats.value.total || 0 },
])

async function loadEsign() {
  esignLoading.value = true
  try {
    const [sr, pr] = await Promise.all([api.esignStats(), api.esignMyPending()])
    esignStats.value = sr.data?.data || {}
    esignPending.value = pr.data?.data?.data || []
  } catch {}
  esignLoading.value = false
}

onMounted(() => {
  loadKb(); loadResearch(); loadSearchStats(); loadHcStats(); loadSigStats(); loadQualityStats(); loadEsign()
})
</script>

<style scoped>
.ai-ops-page { padding: 8px; }
.tab-header { margin-bottom: 16px; }
.tab-header h3 { margin: 0; font-size: 18px; }
.stats-row { margin-bottom: 16px; }
.stat-card { text-align: center; padding: 8px 0; }
.stat-num { font-size: 28px; font-weight: 700; color: var(--el-color-primary); }
.stat-label { font-size: 13px; color: #888; margin-top: 4px; }
.action-bar { margin-bottom: 16px; }
.input-card { margin-bottom: 16px; }
.search-item { padding: 8px 0; border-bottom: 1px solid #eee; }
.search-item .text-muted { color: #999; font-size: 12px; }
.hc-item { padding: 4px 0; }
.report-content { line-height: 1.8; font-size: 14px; }
.report-content h3 { margin: 16px 0 8px; color: var(--el-color-primary); }
</style>
