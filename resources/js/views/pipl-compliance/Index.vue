<template>
  <div class="pipl-page">
    <div class="page-header">
      <h2>PIPL 个人信息保护法合规</h2>
      <div class="header-actions">
        <el-button type="primary" :loading="scanLoading" @click="runScan">
          <el-icon><Search /></el-icon> 扫描数据库字段
        </el-button>
        <el-button @click="loadAll" :loading="loading.stats">刷新</el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="stats-row">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.inventory?.total ?? 0 }}</div>
          <div class="stat-label">个人信息字段</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card warning">
          <div class="stat-value">{{ stats.inventory?.by_level?.L3 ?? 0 }} / {{ stats.inventory?.by_level?.L4 ?? 0 }}</div>
          <div class="stat-label">L3 敏感 / L4 核心</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card info">
          <div class="stat-value">{{ stats.cross_border?.active ?? 0 }}</div>
          <div class="stat-label">活跃跨境传输</div>
          <div v-if="stats.cross_border?.overdue" class="stat-sub warning-text">待复评 {{ stats.cross_border.overdue }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card success">
          <div class="stat-value">{{ stats.dpia?.completed ?? 0 }} / {{ stats.dpia?.total ?? 0 }}</div>
          <div class="stat-label">已完成 DPIA</div>
        </el-card>
      </el-col>
    </el-row>

    <el-tabs v-model="activeTab" class="main-tabs">
      <!-- 个人信息清单 -->
      <el-tab-pane label="个人信息清单" name="inventory">
        <el-card shadow="never">
          <div class="tab-toolbar">
            <el-input v-model="invFilter.table_name" placeholder="表名" clearable style="width:160px" @clear="loadInventory" @keyup.enter="loadInventory" />
            <el-select v-model="invFilter.category" placeholder="分类" clearable style="width:140px" @change="loadInventory">
              <el-option v-for="(l, k) in categoryLabels" :key="k" :label="l" :value="k" />
            </el-select>
            <el-select v-model="invFilter.classification" placeholder="分级" clearable style="width:120px" @change="loadInventory">
              <el-option v-for="(l, k) in classificationLabels" :key="k" :label="l" :value="k" />
            </el-select>
            <el-button type="primary" size="small" @click="loadInventory">查询</el-button>
          </div>
          <el-table :data="inventory" v-loading="loading.inventory" stripe @selection-change="onInvSelect">
            <el-table-column type="selection" width="45" />
            <el-table-column prop="table_name" label="表名" width="160" />
            <el-table-column prop="field_name" label="字段" width="140" />
            <el-table-column label="分类" width="130">
              <template #default="{ row }">
                <el-tag :type="categoryTag(row.category)" size="small">{{ categoryLabels[row.category] || row.category }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="分级" width="120">
              <template #default="{ row }">
                <el-tag :type="levelTag(row.classification)" size="small" effect="dark">{{ classificationLabels[row.classification] || row.classification }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="purpose" label="处理目的" min-width="160" show-overflow-tooltip />
            <el-table-column prop="retention_days" label="保留(天)" width="90" />
            <el-table-column label="可导出" width="70" align="center">
              <template #default="{ row }"><el-icon :color="row.is_exportable ? '#67c23a' : '#909399'"><CircleCheck v-if="row.is_exportable" /><CircleClose v-else /></el-icon></template>
            </el-table-column>
            <el-table-column label="可删除" width="70" align="center">
              <template #default="{ row }"><el-icon :color="row.is_deletable ? '#67c23a' : '#909399'"><CircleCheck v-if="row.is_deletable" /><CircleClose v-else /></el-icon></template>
            </el-table-column>
            <el-table-column label="操作" width="80" fixed="right">
              <template #default="{ row }">
                <el-button text size="small" type="primary" @click="openInvEdit(row)">编辑</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="invTotal > invPerPage">
            <el-pagination v-model:current-page="invPage" v-model:page-size="invPerPage" :total="invTotal"
              layout="total, prev, pager, next" @current-change="loadInventory" />
          </div>
        </el-card>
      </el-tab-pane>

      <!-- 跨境传输 -->
      <el-tab-pane label="跨境传输评估" name="cross-border">
        <el-card shadow="never">
          <div class="tab-toolbar">
            <el-button type="primary" size="small" @click="showTransferForm = true"><el-icon><Plus /></el-icon> 新增传输记录</el-button>
            <el-select v-model="cbFilter.status" placeholder="状态" clearable style="width:120px" @change="loadTransfers">
              <el-option label="活跃" value="active" /><el-option label="已过期" value="expired" /><el-option label="已撤销" value="revoked" />
            </el-select>
          </div>
          <el-table :data="transfers" v-loading="loading.transfers" stripe>
            <el-table-column prop="data_category" label="数据类别" min-width="130" />
            <el-table-column prop="recipient_country" label="目的地" width="100" />
            <el-table-column prop="recipient_name" label="接收方" min-width="140" />
            <el-table-column label="传输方式" width="90">
              <template #default="{ row }">{{ transferMethodLabels[row.transfer_method] || row.transfer_method }}</template>
            </el-table-column>
            <el-table-column label="法律依据" width="120">
              <template #default="{ row }">{{ legalBasisLabels[row.legal_basis] || row.legal_basis }}</template>
            </el-table-column>
            <el-table-column label="状态" width="90">
              <template #default="{ row }">
                <el-tag :type="transferStatusTag(row.status)" size="small">{{ transferStatusLabels[row.status] }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="下次复评" width="120">
              <template #default="{ row }">
                <span :class="{ 'warning-text': isOverdue(row.next_review_at) }">{{ formatDate(row.next_review_at) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="150" fixed="right">
              <template #default="{ row }">
                <el-button text size="small" @click="openTransferReview(row)">复评</el-button>
                <el-button text size="small" type="primary" @click="openTransferEdit(row)">编辑</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="cbTotal > cbPerPage">
            <el-pagination v-model:current-page="cbPage" :page-size="cbPerPage" :total="cbTotal"
              layout="total, prev, pager, next" @current-change="loadTransfers" />
          </div>
        </el-card>
      </el-tab-pane>

      <!-- DPIA -->
      <el-tab-pane label="DPIA 影响评估" name="dpia">
        <el-card shadow="never">
          <div class="tab-toolbar">
            <el-button type="primary" size="small" @click="openDpiaCreate"><el-icon><Plus /></el-icon> 新建 DPIA</el-button>
            <el-select v-model="dpiaFilter.status" placeholder="状态" clearable style="width:130px" @change="loadDpias">
              <el-option v-for="(l, k) in dpiaStatusLabels" :key="k" :label="l" :value="k" />
            </el-select>
          </div>
          <el-table :data="dpias" v-loading="loading.dpias" stripe>
            <el-table-column prop="title" label="标题" min-width="200" />
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="dpiaStatusTag(row.status)" size="small">{{ dpiaStatusLabels[row.status] }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="创建人" width="110">
              <template #default="{ row }">{{ row.creator?.name || '-' }}</template>
            </el-table-column>
            <el-table-column label="创建时间" width="160">
              <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
            </el-table-column>
            <el-table-column label="完成时间" width="160">
              <template #default="{ row }">{{ formatDate(row.completed_at) }}</template>
            </el-table-column>
            <el-table-column label="操作" width="180" fixed="right">
              <template #default="{ row }">
                <el-button text size="small" @click="openDpiaDetail(row)">详情</el-button>
                <el-button v-if="row.status !== 'completed'" text size="small" type="success" @click="openDpiaComplete(row)">完成评估</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="dpiaTotal > dpiaPerPage">
            <el-pagination v-model:current-page="dpiaPage" :page-size="dpiaPerPage" :total="dpiaTotal"
              layout="total, prev, pager, next" @current-change="loadDpias" />
          </div>
        </el-card>
      </el-tab-pane>

      <!-- 敏感字段参考 -->
      <el-tab-pane label="敏感字段定义" name="definitions">
        <el-card shadow="never">
          <p class="hint-text">系统预定义的敏感个人信息字段分类规则（GB/T 35273-2020 参照）</p>
          <el-table :data="sensitiveFieldsList" v-loading="loading.definitions" stripe>
            <el-table-column prop="field" label="字段名" width="180" />
            <el-table-column prop="label" label="说明" min-width="140" />
            <el-table-column label="分类" width="130">
              <template #default="{ row }">
                <el-tag :type="categoryTag(row.category)" size="small">{{ categoryLabels[row.category] }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="分级" width="120">
              <template #default="{ row }">
                <el-tag :type="levelTag(row.level)" size="small" effect="dark">{{ classificationLabels[row.level] }}</el-tag>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>
    </el-tabs>

    <!-- 编辑清单 -->
    <el-dialog v-model="invEditVisible" title="编辑个人信息字段" width="520px">
      <el-form :model="invForm" label-width="100px" size="small">
        <el-form-item label="表/字段"><span>{{ invForm.table_name }}.{{ invForm.field_name }}</span></el-form-item>
        <el-form-item label="分类">
          <el-select v-model="invForm.category" style="width:100%">
            <el-option v-for="(l, k) in categoryLabels" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item label="分级">
          <el-select v-model="invForm.classification" style="width:100%">
            <el-option v-for="(l, k) in classificationLabels" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item label="处理目的"><el-input v-model="invForm.purpose" type="textarea" :rows="2" /></el-form-item>
        <el-form-item label="保留天数"><el-input v-model="invForm.retention_days" /></el-form-item>
        <el-form-item label="可导出"><el-switch v-model="invForm.is_exportable" /></el-form-item>
        <el-form-item label="可删除"><el-switch v-model="invForm.is_deletable" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="invEditVisible = false">取消</el-button>
        <el-button type="primary" :loading="savingInv" @click="saveInvEdit">保存</el-button>
      </template>
    </el-dialog>

    <!-- 跨境传输表单 -->
    <el-dialog v-model="showTransferForm" :title="transferEditId ? '编辑跨境传输' : '新增跨境传输'" width="560px">
      <el-form :model="transferForm" label-width="100px" size="small">
        <el-form-item label="数据类别" required><el-input v-model="transferForm.data_category" placeholder="如：用户账户信息" /></el-form-item>
        <el-form-item label="目的地" required><el-input v-model="transferForm.recipient_country" placeholder="如：美国" /></el-form-item>
        <el-form-item label="接收方" required><el-input v-model="transferForm.recipient_name" placeholder="如：AWS Inc." /></el-form-item>
        <el-form-item label="传输目的" required><el-input v-model="transferForm.recipient_purpose" type="textarea" :rows="2" /></el-form-item>
        <el-form-item label="传输方式" required>
          <el-select v-model="transferForm.transfer_method" style="width:100%">
            <el-option v-for="(l, k) in transferMethodLabels" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item label="法律依据" required>
          <el-select v-model="transferForm.legal_basis" style="width:100%">
            <el-option v-for="(l, k) in legalBasisLabels" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item label="安全措施"><el-input v-model="transferForm.security_measures" type="textarea" :rows="2" placeholder="TLS 加密、访问控制等" /></el-form-item>
        <el-form-item v-if="transferEditId" label="状态">
          <el-select v-model="transferForm.status" style="width:100%">
            <el-option v-for="(l, k) in transferStatusLabels" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showTransferForm = false">取消</el-button>
        <el-button type="primary" :loading="savingTransfer" @click="saveTransfer">保存</el-button>
      </template>
    </el-dialog>

    <!-- 跨境复评 -->
    <el-dialog v-model="reviewVisible" title="跨境传输影响复评" width="520px">
      <p class="hint-text mb-2">接收方：{{ reviewTarget?.recipient_name }}（{{ reviewTarget?.recipient_country }}）</p>
      <el-input v-model="reviewForm.impact_assessment" type="textarea" :rows="5" placeholder="填写传输影响评估结论..." />
      <template #footer>
        <el-button @click="reviewVisible = false">取消</el-button>
        <el-button type="primary" :loading="savingReview" @click="submitReview">提交复评</el-button>
      </template>
    </el-dialog>

    <!-- DPIA 创建 -->
    <el-dialog v-model="dpiaCreateVisible" title="新建 DPIA" width="560px">
      <el-form :model="dpiaCreateForm" label-width="100px" size="small">
        <el-form-item label="标题" required><el-input v-model="dpiaCreateForm.title" /></el-form-item>
        <el-form-item label="描述"><el-input v-model="dpiaCreateForm.description" type="textarea" :rows="3" /></el-form-item>
        <el-form-item label="涉及数据">
          <el-select v-model="dpiaCreateForm.involved_data_categories" multiple filterable allow-create style="width:100%" placeholder="输入后回车添加">
            <el-option label="用户账户信息" value="用户账户信息" />
            <el-option label="设备指纹" value="设备指纹" />
            <el-option label="IP 地址" value="IP 地址" />
            <el-option label="支付信息" value="支付信息" />
          </el-select>
        </el-form-item>
        <el-form-item label="相关方">
          <el-select v-model="dpiaCreateForm.stakeholders" multiple filterable allow-create style="width:100%">
            <el-option label="数据保护官" value="数据保护官" />
            <el-option label="产品经理" value="产品经理" />
            <el-option label="法务" value="法务" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dpiaCreateVisible = false">取消</el-button>
        <el-button type="primary" :loading="savingDpia" @click="saveDpiaCreate">创建</el-button>
      </template>
    </el-dialog>

    <!-- DPIA 详情/编辑 -->
    <el-dialog v-model="dpiaDetailVisible" title="DPIA 详情" width="680px" top="5vh">
      <template v-if="dpiaDetail">
        <el-descriptions :column="2" border size="small" class="mb-3">
          <el-descriptions-item label="标题" :span="2">{{ dpiaDetail.title }}</el-descriptions-item>
          <el-descriptions-item label="状态"><el-tag :type="dpiaStatusTag(dpiaDetail.status)" size="small">{{ dpiaStatusLabels[dpiaDetail.status] }}</el-tag></el-descriptions-item>
          <el-descriptions-item label="创建人">{{ dpiaDetail.creator?.name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="描述" :span="2">{{ dpiaDetail.description || '-' }}</el-descriptions-item>
          <el-descriptions-item label="涉及数据" :span="2">{{ (dpiaDetail.involved_data_categories || []).join('、') || '-' }}</el-descriptions-item>
          <el-descriptions-item label="相关方" :span="2">{{ (dpiaDetail.stakeholders || []).join('、') || '-' }}</el-descriptions-item>
        </el-descriptions>
        <div v-if="dpiaDetail.status !== 'completed'" class="mt-3">
          <el-form :model="dpiaEditForm" label-width="100px" size="small">
            <el-form-item label="状态">
              <el-select v-model="dpiaEditForm.status" style="width:200px">
                <el-option label="草稿" value="draft" /><el-option label="进行中" value="in_progress" />
              </el-select>
            </el-form-item>
            <el-form-item label="必要性评估"><el-input v-model="dpiaEditForm.necessity_assessment" type="textarea" :rows="2" /></el-form-item>
            <el-form-item label="风险评估"><el-input v-model="dpiaEditForm.risk_assessment" type="textarea" :rows="2" /></el-form-item>
            <el-form-item label="缓解措施"><el-input v-model="dpiaEditForm.mitigation_measures" type="textarea" :rows="2" /></el-form-item>
            <el-form-item label="结论"><el-input v-model="dpiaEditForm.conclusion" type="textarea" :rows="2" /></el-form-item>
          </el-form>
          <div class="text-right">
            <el-button type="primary" :loading="savingDpia" @click="saveDpiaEdit">保存进度</el-button>
          </div>
        </div>
        <template v-else>
          <h5 class="section-title">评估结论</h5>
          <p><strong>必要性：</strong>{{ dpiaDetail.necessity_assessment }}</p>
          <p><strong>风险：</strong>{{ dpiaDetail.risk_assessment }}</p>
          <p><strong>缓解措施：</strong>{{ dpiaDetail.mitigation_measures }}</p>
          <p><strong>结论：</strong>{{ dpiaDetail.conclusion }}</p>
        </template>
      </template>
    </el-dialog>

    <!-- DPIA 完成 -->
    <el-dialog v-model="dpiaCompleteVisible" title="完成 DPIA 评估" width="560px">
      <el-form :model="dpiaCompleteForm" label-width="100px" size="small">
        <el-form-item label="必要性评估" required><el-input v-model="dpiaCompleteForm.necessity_assessment" type="textarea" :rows="3" /></el-form-item>
        <el-form-item label="风险评估" required><el-input v-model="dpiaCompleteForm.risk_assessment" type="textarea" :rows="3" /></el-form-item>
        <el-form-item label="缓解措施" required><el-input v-model="dpiaCompleteForm.mitigation_measures" type="textarea" :rows="3" /></el-form-item>
        <el-form-item label="结论" required><el-input v-model="dpiaCompleteForm.conclusion" type="textarea" :rows="2" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dpiaCompleteVisible = false">取消</el-button>
        <el-button type="success" :loading="savingDpia" @click="submitDpiaComplete">完成评估</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus, Search, CircleCheck, CircleClose } from '@element-plus/icons-vue';
import piplApi from '@/api/pipl';

const activeTab = ref('inventory');
const stats = ref({});
const scanLoading = ref(false);
const loading = reactive({ stats: false, inventory: false, transfers: false, dpias: false, definitions: false });

const categoryLabels = { person: '个人信息', general: '一般信息', sensitive: '敏感个人信息', private: '私密信息' };
const classificationLabels = { L1: 'L1 公开', L2: 'L2 内部', L3: 'L3 敏感', L4: 'L4 核心' };
const legalBasisLabels = { consent: '单独同意', standard_clauses: '标准合同', adequacy: '充分保护认定', safe_harbor: '安全港', other: '其他' };
const transferMethodLabels = { api: 'API', sdk: 'SDK', manual: '人工', cloud: '云服务' };
const transferStatusLabels = { active: '活跃', expired: '已过期', revoked: '已撤销' };
const dpiaStatusLabels = { draft: '草稿', in_progress: '进行中', completed: '已完成', archived: '已归档' };

// Inventory
const inventory = ref([]);
const invPage = ref(1);
const invPerPage = ref(50);
const invTotal = ref(0);
const invFilter = reactive({ table_name: '', category: '', classification: '' });
const invEditVisible = ref(false);
const savingInv = ref(false);
const invForm = reactive({ id: null, table_name: '', field_name: '', category: '', classification: '', purpose: '', retention_days: '', is_exportable: true, is_deletable: true });
const invSelected = ref([]);

// Cross-border
const transfers = ref([]);
const cbPage = ref(1);
const cbPerPage = ref(20);
const cbTotal = ref(0);
const cbFilter = reactive({ status: '' });
const showTransferForm = ref(false);
const transferEditId = ref(null);
const savingTransfer = ref(false);
const transferForm = reactive({
  data_category: '', recipient_country: '', recipient_name: '', recipient_purpose: '',
  transfer_method: 'cloud', legal_basis: 'standard_clauses', security_measures: '', status: 'active',
});
const reviewVisible = ref(false);
const reviewTarget = ref(null);
const savingReview = ref(false);
const reviewForm = reactive({ impact_assessment: '' });

// DPIA
const dpias = ref([]);
const dpiaPage = ref(1);
const dpiaPerPage = ref(20);
const dpiaTotal = ref(0);
const dpiaFilter = reactive({ status: '' });
const dpiaCreateVisible = ref(false);
const dpiaDetailVisible = ref(false);
const dpiaCompleteVisible = ref(false);
const savingDpia = ref(false);
const dpiaDetail = ref(null);
const dpiaCompleteTarget = ref(null);
const dpiaCreateForm = reactive({ title: '', description: '', involved_data_categories: [], stakeholders: [] });
const dpiaEditForm = reactive({ status: 'draft', necessity_assessment: '', risk_assessment: '', mitigation_measures: '', conclusion: '' });
const dpiaCompleteForm = reactive({ necessity_assessment: '', risk_assessment: '', mitigation_measures: '', conclusion: '' });

// Definitions
const sensitiveFieldsRaw = ref({});
const sensitiveFieldsList = computed(() =>
  Object.entries(sensitiveFieldsRaw.value).map(([field, meta]) => ({ field, ...meta }))
);

function categoryTag(c) { return { person: '', general: 'info', sensitive: 'warning', private: 'danger' }[c] || 'info'; }
function levelTag(l) { return { L1: 'info', L2: '', L3: 'warning', L4: 'danger' }[l] || ''; }
function transferStatusTag(s) { return { active: 'success', expired: 'warning', revoked: 'info' }[s] || ''; }
function dpiaStatusTag(s) { return { draft: 'info', in_progress: 'warning', completed: 'success', archived: '' }[s] || ''; }
function formatDate(t) { return t ? new Date(t).toLocaleDateString('zh-CN') : '-'; }
function isOverdue(t) { return t && new Date(t) <= new Date(); }

async function loadStats() {
  loading.stats = true;
  try {
    const { data: res } = await piplApi.getStats();
    if (res.success) stats.value = res.data || {};
  } catch { stats.value = {}; }
  finally { loading.stats = false; }
}

async function loadInventory() {
  loading.inventory = true;
  try {
    const { data: res } = await piplApi.getInventory({
      page: invPage.value, per_page: invPerPage.value,
      table_name: invFilter.table_name || undefined,
      category: invFilter.category || undefined,
      classification: invFilter.classification || undefined,
    });
    if (res.success) {
      inventory.value = res.data?.data || [];
      invTotal.value = res.data?.total || 0;
    }
  } catch { inventory.value = []; }
  finally { loading.inventory = false; }
}

async function loadTransfers() {
  loading.transfers = true;
  try {
    const { data: res } = await piplApi.getCrossBorderTransfers({
      page: cbPage.value, per_page: cbPerPage.value,
      status: cbFilter.status || undefined,
    });
    if (res.success) {
      transfers.value = res.data?.data || [];
      cbTotal.value = res.data?.total || 0;
    }
  } catch { transfers.value = []; }
  finally { loading.transfers = false; }
}

async function loadDpias() {
  loading.dpias = true;
  try {
    const { data: res } = await piplApi.getDpias({
      page: dpiaPage.value, per_page: dpiaPerPage.value,
      status: dpiaFilter.status || undefined,
    });
    if (res.success) {
      dpias.value = res.data?.data || [];
      dpiaTotal.value = res.data?.total || 0;
    }
  } catch { dpias.value = []; }
  finally { loading.dpias = false; }
}

async function loadDefinitions() {
  loading.definitions = true;
  try {
    const { data: res } = await piplApi.getSensitiveFields();
    if (res.success) sensitiveFieldsRaw.value = res.data || {};
  } catch { sensitiveFieldsRaw.value = {}; }
  finally { loading.definitions = false; }
}

async function runScan() {
  scanLoading.value = true;
  try {
    const { data: res } = await piplApi.scan();
    if (res.success) {
      ElMessage.success(res.message || `扫描完成，新增 ${res.data?.items_created ?? 0} 条`);
      loadStats();
      loadInventory();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || '扫描失败'); }
  finally { scanLoading.value = false; }
}

function onInvSelect(rows) { invSelected.value = rows; }

function openInvEdit(row) {
  Object.assign(invForm, {
    id: row.id, table_name: row.table_name, field_name: row.field_name,
    category: row.category, classification: row.classification,
    purpose: row.purpose || '', retention_days: row.retention_days || '',
    is_exportable: row.is_exportable, is_deletable: row.is_deletable,
  });
  invEditVisible.value = true;
}

async function saveInvEdit() {
  savingInv.value = true;
  try {
    const { data: res } = await piplApi.updateInventory(invForm.id, {
      category: invForm.category, classification: invForm.classification,
      purpose: invForm.purpose, retention_days: invForm.retention_days,
      is_exportable: invForm.is_exportable, is_deletable: invForm.is_deletable,
    });
    if (res.success) {
      ElMessage.success('已更新');
      invEditVisible.value = false;
      loadInventory();
      loadStats();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || '保存失败'); }
  finally { savingInv.value = false; }
}

function resetTransferForm() {
  transferEditId.value = null;
  Object.assign(transferForm, {
    data_category: '', recipient_country: '', recipient_name: '', recipient_purpose: '',
    transfer_method: 'cloud', legal_basis: 'standard_clauses', security_measures: '', status: 'active',
  });
}

function openTransferEdit(row) {
  transferEditId.value = row.id;
  Object.assign(transferForm, {
    data_category: row.data_category, recipient_country: row.recipient_country,
    recipient_name: row.recipient_name, recipient_purpose: row.recipient_purpose,
    transfer_method: row.transfer_method, legal_basis: row.legal_basis,
    security_measures: row.security_measures || '', status: row.status,
  });
  showTransferForm.value = true;
}

function openTransferReview(row) {
  reviewTarget.value = row;
  reviewForm.impact_assessment = row.impact_assessment || '';
  reviewVisible.value = true;
}

async function saveTransfer() {
  if (!transferForm.data_category || !transferForm.recipient_country || !transferForm.recipient_name) {
    ElMessage.warning('请填写必填项'); return;
  }
  savingTransfer.value = true;
  try {
    const payload = { ...transferForm };
    const res = transferEditId.value
      ? await piplApi.updateCrossBorderTransfer(transferEditId.value, payload)
      : await piplApi.createCrossBorderTransfer(payload);
    if (res.data.success) {
      ElMessage.success(transferEditId.value ? '已更新' : '已创建');
      showTransferForm.value = false;
      resetTransferForm();
      loadTransfers();
      loadStats();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败'); }
  finally { savingTransfer.value = false; }
}

async function submitReview() {
  if (!reviewForm.impact_assessment.trim()) { ElMessage.warning('请填写评估结论'); return; }
  savingReview.value = true;
  try {
    const { data: res } = await piplApi.reviewCrossBorderTransfer(reviewTarget.value.id, reviewForm);
    if (res.success) {
      ElMessage.success('复评已提交');
      reviewVisible.value = false;
      loadTransfers();
      loadStats();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || '提交失败'); }
  finally { savingReview.value = false; }
}

function openDpiaCreate() {
  dpiaCreateForm.title = '';
  dpiaCreateForm.description = '';
  dpiaCreateForm.involved_data_categories = [];
  dpiaCreateForm.stakeholders = [];
  dpiaCreateVisible.value = true;
}

async function saveDpiaCreate() {
  if (!dpiaCreateForm.title.trim()) { ElMessage.warning('请填写标题'); return; }
  savingDpia.value = true;
  try {
    const { data: res } = await piplApi.createDpia({ ...dpiaCreateForm });
    if (res.success) {
      ElMessage.success('DPIA 已创建');
      dpiaCreateVisible.value = false;
      loadDpias();
      loadStats();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || '创建失败'); }
  finally { savingDpia.value = false; }
}

async function openDpiaDetail(row) {
  try {
    const { data: res } = await piplApi.getDpia(row.id);
    if (res.success) {
      dpiaDetail.value = res.data;
      Object.assign(dpiaEditForm, {
        status: res.data.status || 'draft',
        necessity_assessment: res.data.necessity_assessment || '',
        risk_assessment: res.data.risk_assessment || '',
        mitigation_measures: res.data.mitigation_measures || '',
        conclusion: res.data.conclusion || '',
      });
      dpiaDetailVisible.value = true;
    }
  } catch { ElMessage.error('加载失败'); }
}

async function saveDpiaEdit() {
  savingDpia.value = true;
  try {
    const { data: res } = await piplApi.updateDpia(dpiaDetail.value.id, { ...dpiaEditForm });
    if (res.success) {
      ElMessage.success('已保存');
      dpiaDetail.value = res.data;
      loadDpias();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || '保存失败'); }
  finally { savingDpia.value = false; }
}

function openDpiaComplete(row) {
  dpiaCompleteTarget.value = row;
  Object.assign(dpiaCompleteForm, {
    necessity_assessment: row.necessity_assessment || '',
    risk_assessment: row.risk_assessment || '',
    mitigation_measures: row.mitigation_measures || '',
    conclusion: row.conclusion || '',
  });
  dpiaCompleteVisible.value = true;
}

async function submitDpiaComplete() {
  const f = dpiaCompleteForm;
  if (!f.necessity_assessment || !f.risk_assessment || !f.mitigation_measures || !f.conclusion) {
    ElMessage.warning('请填写全部评估项'); return;
  }
  savingDpia.value = true;
  try {
    const { data: res } = await piplApi.completeDpia(dpiaCompleteTarget.value.id, { ...f });
    if (res.success) {
      ElMessage.success('DPIA 评估已完成');
      dpiaCompleteVisible.value = false;
      loadDpias();
      loadStats();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || '提交失败'); }
  finally { savingDpia.value = false; }
}

async function loadAll() {
  await Promise.all([loadStats(), loadInventory(), loadTransfers(), loadDpias(), loadDefinitions()]);
}

watch(activeTab, (tab) => {
  if (tab === 'inventory') loadInventory();
  if (tab === 'cross-border') loadTransfers();
  if (tab === 'dpia') loadDpias();
  if (tab === 'definitions') loadDefinitions();
});

watch(showTransferForm, (v) => { if (v && !transferEditId.value) resetTransferForm(); });

onMounted(() => {
  loadStats();
  loadInventory();
  loadDefinitions();
});
</script>

<style scoped>
.pipl-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; padding: 8px 0; }
.stat-card.warning { border-left: 3px solid #e6a23c; }
.stat-card.info { border-left: 3px solid #409eff; }
.stat-card.success { border-left: 3px solid #67c23a; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-sub { font-size: 12px; margin-top: 4px; }
.warning-text { color: #e6a23c; font-weight: 600; }
.tab-toolbar { display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; align-items: center; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: flex-end; }
.hint-text { color: #909399; font-size: 13px; margin: 0 0 12px; }
.section-title { font-size: 14px; font-weight: 600; margin: 12px 0 8px; }
.mb-2 { margin-bottom: 8px; }
.mb-3 { margin-bottom: 16px; }
.mt-3 { margin-top: 16px; }
.text-right { text-align: right; }
</style>
