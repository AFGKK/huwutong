<template>
  <div class="pipl-page">
    <div class="page-header">
      <h2>{{ t('pipl_compliance_page.title') }}</h2>
      <div class="header-actions">
        <el-button type="primary" :loading="scanLoading" @click="runScan">
          <el-icon><Search /></el-icon> {{ t('pipl_compliance_page.scan_db_fields') }}
        </el-button>
        <el-button @click="loadAll" :loading="loading.stats">{{ t('pipl_compliance_page.refresh') }}</el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="stats-row">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.inventory?.total ?? 0 }}</div>
          <div class="stat-label">{{ t('pipl_compliance_page.stats.personal_info_fields') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card warning">
          <div class="stat-value">{{ stats.inventory?.by_level?.L3 ?? 0 }} / {{ stats.inventory?.by_level?.L4 ?? 0 }}</div>
          <div class="stat-label">{{ t('pipl_compliance_page.stats.l3_l4_sensitive') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card info">
          <div class="stat-value">{{ stats.cross_border?.active ?? 0 }}</div>
          <div class="stat-label">{{ t('pipl_compliance_page.stats.active_cross_border') }}</div>
          <div v-if="stats.cross_border?.overdue" class="stat-sub warning-text">{{ t('pipl_compliance_page.stats.overdue_review', { n: stats.cross_border.overdue }) }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card success">
          <div class="stat-value">{{ stats.dpia?.completed ?? 0 }} / {{ stats.dpia?.total ?? 0 }}</div>
          <div class="stat-label">{{ t('pipl_compliance_page.stats.dpia_completed') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-tabs v-model="activeTab" class="main-tabs">
      <!-- 个人信息清单 -->
      <el-tab-pane :label="t('pipl_compliance_page.tabs.inventory')" name="inventory">
        <el-card shadow="never">
          <div class="tab-toolbar">
            <el-input v-model="invFilter.table_name" :placeholder="t('pipl_compliance_page.filters.table_name_ph')" clearable style="width:160px" @clear="loadInventory" @keyup.enter="loadInventory" />
            <el-select v-model="invFilter.category" :placeholder="t('pipl_compliance_page.filters.category_ph')" clearable style="width:140px" @change="loadInventory">
              <el-option v-for="(l, k) in categoryLabels" :key="k" :label="l" :value="k" />
            </el-select>
            <el-select v-model="invFilter.classification" :placeholder="t('pipl_compliance_page.filters.classification_ph')" clearable style="width:120px" @change="loadInventory">
              <el-option v-for="(l, k) in classificationLabels" :key="k" :label="l" :value="k" />
            </el-select>
            <el-button type="primary" size="small" @click="loadInventory">{{ t('actions.search') }}</el-button>
          </div>
          <el-table :data="inventory" v-loading="loading.inventory" stripe @selection-change="onInvSelect">
            <el-table-column type="selection" width="45" />
            <el-table-column prop="table_name" :label="t('pipl_compliance_page.columns.table_name')" width="160" />
            <el-table-column prop="field_name" :label="t('pipl_compliance_page.columns.field_name')" width="140" />
            <el-table-column :label="t('pipl_compliance_page.columns.category')" width="130">
              <template #default="{ row }">
                <el-tag :type="categoryTag(row.category)" size="small">{{ categoryLabels[row.category] || row.category }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('pipl_compliance_page.columns.classification')" width="120">
              <template #default="{ row }">
                <el-tag :type="levelTag(row.classification)" size="small" effect="dark">{{ classificationLabels[row.classification] || row.classification }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="purpose" :label="t('pipl_compliance_page.columns.purpose')" min-width="160" show-overflow-tooltip />
            <el-table-column prop="retention_days" :label="t('pipl_compliance_page.columns.retention_days')" width="90" />
            <el-table-column :label="t('pipl_compliance_page.columns.exportable')" width="70" align="center">
              <template #default="{ row }"><el-icon :color="row.is_exportable ? '#67c23a' : '#909399'"><CircleCheck v-if="row.is_exportable" /><CircleClose v-else /></el-icon></template>
            </el-table-column>
            <el-table-column :label="t('pipl_compliance_page.columns.deletable')" width="70" align="center">
              <template #default="{ row }"><el-icon :color="row.is_deletable ? '#67c23a' : '#909399'"><CircleCheck v-if="row.is_deletable" /><CircleClose v-else /></el-icon></template>
            </el-table-column>
            <el-table-column :label="t('pipl_compliance_page.columns.actions')" width="80" fixed="right">
              <template #default="{ row }">
                <el-button text size="small" type="primary" @click="openInvEdit(row)">{{ t('actions.edit') }}</el-button>
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
      <el-tab-pane :label="t('pipl_compliance_page.tabs.cross_border')" name="cross-border">
        <el-card shadow="never">
          <div class="tab-toolbar">
            <el-button type="primary" size="small" @click="showTransferForm = true"><el-icon><Plus /></el-icon> {{ t('pipl_compliance_page.buttons.add_transfer') }}</el-button>
            <el-select v-model="cbFilter.status" :placeholder="t('pipl_compliance_page.filters.status_ph')" clearable style="width:120px" @change="loadTransfers">
              <el-option v-for="(l, k) in transferStatusLabels" :key="k" :label="l" :value="k" />
            </el-select>
          </div>
          <el-table :data="transfers" v-loading="loading.transfers" stripe>
            <el-table-column prop="data_category" :label="t('pipl_compliance_page.columns.data_category')" min-width="130" />
            <el-table-column prop="recipient_country" :label="t('pipl_compliance_page.columns.destination')" width="100" />
            <el-table-column prop="recipient_name" :label="t('pipl_compliance_page.columns.recipient')" min-width="140" />
            <el-table-column :label="t('pipl_compliance_page.columns.transfer_method')" width="90">
              <template #default="{ row }">{{ transferMethodLabels[row.transfer_method] || row.transfer_method }}</template>
            </el-table-column>
            <el-table-column :label="t('pipl_compliance_page.columns.legal_basis')" width="120">
              <template #default="{ row }">{{ legalBasisLabels[row.legal_basis] || row.legal_basis }}</template>
            </el-table-column>
            <el-table-column :label="t('pipl_compliance_page.columns.status')" width="90">
              <template #default="{ row }">
                <el-tag :type="transferStatusTag(row.status)" size="small">{{ transferStatusLabels[row.status] }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('pipl_compliance_page.columns.next_review')" width="120">
              <template #default="{ row }">
                <span :class="{ 'warning-text': isOverdue(row.next_review_at) }">{{ formatDate(row.next_review_at) }}</span>
              </template>
            </el-table-column>
            <el-table-column :label="t('pipl_compliance_page.columns.actions')" width="150" fixed="right">
              <template #default="{ row }">
                <el-button text size="small" @click="openTransferReview(row)">{{ t('pipl_compliance_page.buttons.review') }}</el-button>
                <el-button text size="small" type="primary" @click="openTransferEdit(row)">{{ t('actions.edit') }}</el-button>
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
      <el-tab-pane :label="t('pipl_compliance_page.tabs.dpia')" name="dpia">
        <el-card shadow="never">
          <div class="tab-toolbar">
            <el-button type="primary" size="small" @click="openDpiaCreate"><el-icon><Plus /></el-icon> {{ t('pipl_compliance_page.buttons.new_dpia') }}</el-button>
            <el-select v-model="dpiaFilter.status" :placeholder="t('pipl_compliance_page.filters.status_ph')" clearable style="width:130px" @change="loadDpias">
              <el-option v-for="(l, k) in dpiaStatusLabels" :key="k" :label="l" :value="k" />
            </el-select>
          </div>
          <el-table :data="dpias" v-loading="loading.dpias" stripe>
            <el-table-column prop="title" :label="t('pipl_compliance_page.columns.title')" min-width="200" />
            <el-table-column :label="t('pipl_compliance_page.columns.status')" width="100">
              <template #default="{ row }">
                <el-tag :type="dpiaStatusTag(row.status)" size="small">{{ dpiaStatusLabels[row.status] }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('pipl_compliance_page.columns.creator')" width="110">
              <template #default="{ row }">{{ row.creator?.name || '-' }}</template>
            </el-table-column>
            <el-table-column :label="t('pipl_compliance_page.columns.created_at')" width="160">
              <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
            </el-table-column>
            <el-table-column :label="t('pipl_compliance_page.columns.completed_at')" width="160">
              <template #default="{ row }">{{ formatDate(row.completed_at) }}</template>
            </el-table-column>
            <el-table-column :label="t('pipl_compliance_page.columns.actions')" width="180" fixed="right">
              <template #default="{ row }">
                <el-button text size="small" @click="openDpiaDetail(row)">{{ t('actions.view_details') }}</el-button>
                <el-button v-if="row.status !== 'completed'" text size="small" type="success" @click="openDpiaComplete(row)">{{ t('pipl_compliance_page.buttons.complete_assessment') }}</el-button>
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
      <el-tab-pane :label="t('pipl_compliance_page.tabs.definitions')" name="definitions">
        <el-card shadow="never">
          <p class="hint-text">{{ t('pipl_compliance_page.hints.sensitive_fields') }}</p>
          <el-table :data="sensitiveFieldsList" v-loading="loading.definitions" stripe>
            <el-table-column prop="field" :label="t('pipl_compliance_page.columns.field_label')" width="180" />
            <el-table-column prop="label" :label="t('pipl_compliance_page.columns.description')" min-width="140" />
            <el-table-column :label="t('pipl_compliance_page.columns.category')" width="130">
              <template #default="{ row }">
                <el-tag :type="categoryTag(row.category)" size="small">{{ categoryLabels[row.category] }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('pipl_compliance_page.columns.classification')" width="120">
              <template #default="{ row }">
                <el-tag :type="levelTag(row.level)" size="small" effect="dark">{{ classificationLabels[row.level] }}</el-tag>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>
    </el-tabs>

    <!-- 编辑清单 -->
    <el-dialog v-model="invEditVisible" :title="t('pipl_compliance_page.dialogs.edit_inventory')" width="520px">
      <el-form :model="invForm" label-width="100px" size="small">
        <el-form-item :label="t('pipl_compliance_page.columns.table_field')"><span>{{ invForm.table_name }}.{{ invForm.field_name }}</span></el-form-item>
        <el-form-item :label="t('pipl_compliance_page.columns.category')">
          <el-select v-model="invForm.category" style="width:100%">
            <el-option v-for="(l, k) in categoryLabels" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('pipl_compliance_page.columns.classification')">
          <el-select v-model="invForm.classification" style="width:100%">
            <el-option v-for="(l, k) in classificationLabels" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('pipl_compliance_page.columns.purpose')"><el-input v-model="invForm.purpose" type="textarea" :rows="2" /></el-form-item>
        <el-form-item :label="t('pipl_compliance_page.forms.retention_days')"><el-input v-model="invForm.retention_days" /></el-form-item>
        <el-form-item :label="t('pipl_compliance_page.forms.exportable')"><el-switch v-model="invForm.is_exportable" /></el-form-item>
        <el-form-item :label="t('pipl_compliance_page.forms.deletable')"><el-switch v-model="invForm.is_deletable" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="invEditVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="savingInv" @click="saveInvEdit">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- 跨境传输表单 -->
    <el-dialog v-model="showTransferForm" :title="transferEditId ? t('pipl_compliance_page.dialogs.edit_transfer') : t('pipl_compliance_page.dialogs.add_transfer')" width="560px">
      <el-form :model="transferForm" label-width="100px" size="small">
        <el-form-item :label="t('pipl_compliance_page.columns.data_category')" required><el-input v-model="transferForm.data_category" :placeholder="t('pipl_compliance_page.placeholders.data_category')" /></el-form-item>
        <el-form-item :label="t('pipl_compliance_page.columns.destination')" required><el-input v-model="transferForm.recipient_country" :placeholder="t('pipl_compliance_page.placeholders.destination')" /></el-form-item>
        <el-form-item :label="t('pipl_compliance_page.columns.recipient')" required><el-input v-model="transferForm.recipient_name" :placeholder="t('pipl_compliance_page.placeholders.recipient')" /></el-form-item>
        <el-form-item :label="t('pipl_compliance_page.forms.transfer_purpose')" required><el-input v-model="transferForm.recipient_purpose" type="textarea" :rows="2" /></el-form-item>
        <el-form-item :label="t('pipl_compliance_page.columns.transfer_method')" required>
          <el-select v-model="transferForm.transfer_method" style="width:100%">
            <el-option v-for="(l, k) in transferMethodLabels" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('pipl_compliance_page.columns.legal_basis')" required>
          <el-select v-model="transferForm.legal_basis" style="width:100%">
            <el-option v-for="(l, k) in legalBasisLabels" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('pipl_compliance_page.forms.security_measures')"><el-input v-model="transferForm.security_measures" type="textarea" :rows="2" :placeholder="t('pipl_compliance_page.placeholders.security_measures')" /></el-form-item>
        <el-form-item v-if="transferEditId" :label="t('pipl_compliance_page.columns.status')">
          <el-select v-model="transferForm.status" style="width:100%">
            <el-option v-for="(l, k) in transferStatusLabels" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showTransferForm = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="savingTransfer" @click="saveTransfer">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- 跨境复评 -->
    <el-dialog v-model="reviewVisible" :title="t('pipl_compliance_page.dialogs.transfer_review')" width="520px">
      <p class="hint-text mb-2">{{ t('pipl_compliance_page.review_recipient', { name: reviewTarget?.recipient_name, country: reviewTarget?.recipient_country }) }}</p>
      <el-input v-model="reviewForm.impact_assessment" type="textarea" :rows="5" :placeholder="t('pipl_compliance_page.placeholders.impact_assessment')" />
      <template #footer>
        <el-button @click="reviewVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="savingReview" @click="submitReview">{{ t('pipl_compliance_page.buttons.submit_review') }}</el-button>
      </template>
    </el-dialog>

    <!-- DPIA 创建 -->
    <el-dialog v-model="dpiaCreateVisible" :title="t('pipl_compliance_page.dialogs.new_dpia')" width="560px">
      <el-form :model="dpiaCreateForm" label-width="100px" size="small">
        <el-form-item :label="t('pipl_compliance_page.columns.title')" required><el-input v-model="dpiaCreateForm.title" /></el-form-item>
        <el-form-item :label="t('pipl_compliance_page.columns.description')"><el-input v-model="dpiaCreateForm.description" type="textarea" :rows="3" /></el-form-item>
        <el-form-item :label="t('pipl_compliance_page.forms.involved_data')">
          <el-select v-model="dpiaCreateForm.involved_data_categories" multiple filterable allow-create style="width:100%" :placeholder="t('pipl_compliance_page.placeholders.involved_data')">
            <el-option :label="t('pipl_compliance_page.data_categories.user_account')" :value="apiZh('pipl_compliance_page.data_categories.user_account')" />
            <el-option :label="t('pipl_compliance_page.data_categories.device_fingerprint')" :value="apiZh('pipl_compliance_page.data_categories.device_fingerprint')" />
            <el-option :label="t('pipl_compliance_page.data_categories.ip_address')" :value="apiZh('pipl_compliance_page.data_categories.ip_address')" />
            <el-option :label="t('pipl_compliance_page.data_categories.payment_info')" :value="apiZh('pipl_compliance_page.data_categories.payment_info')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('pipl_compliance_page.forms.stakeholders')">
          <el-select v-model="dpiaCreateForm.stakeholders" multiple filterable allow-create style="width:100%">
            <el-option :label="t('pipl_compliance_page.stakeholders.dpo')" :value="apiZh('pipl_compliance_page.stakeholders.dpo')" />
            <el-option :label="t('pipl_compliance_page.stakeholders.product_manager')" :value="apiZh('pipl_compliance_page.stakeholders.product_manager')" />
            <el-option :label="t('pipl_compliance_page.stakeholders.legal')" :value="apiZh('pipl_compliance_page.stakeholders.legal')" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dpiaCreateVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="savingDpia" @click="saveDpiaCreate">{{ t('actions.create') }}</el-button>
      </template>
    </el-dialog>

    <!-- DPIA 详情/编辑 -->
    <el-dialog v-model="dpiaDetailVisible" :title="t('pipl_compliance_page.dialogs.dpia_detail')" width="680px" top="5vh">
      <template v-if="dpiaDetail">
        <el-descriptions :column="2" border size="small" class="mb-3">
          <el-descriptions-item :label="t('pipl_compliance_page.columns.title')" :span="2">{{ dpiaDetail.title }}</el-descriptions-item>
          <el-descriptions-item :label="t('pipl_compliance_page.columns.status')"><el-tag :type="dpiaStatusTag(dpiaDetail.status)" size="small">{{ dpiaStatusLabels[dpiaDetail.status] }}</el-tag></el-descriptions-item>
          <el-descriptions-item :label="t('pipl_compliance_page.columns.creator')">{{ dpiaDetail.creator?.name || '-' }}</el-descriptions-item>
          <el-descriptions-item :label="t('pipl_compliance_page.columns.description')" :span="2">{{ dpiaDetail.description || '-' }}</el-descriptions-item>
          <el-descriptions-item :label="t('pipl_compliance_page.forms.involved_data')" :span="2">{{ (dpiaDetail.involved_data_categories || []).join(localeListSep) || '-' }}</el-descriptions-item>
          <el-descriptions-item :label="t('pipl_compliance_page.forms.stakeholders')" :span="2">{{ (dpiaDetail.stakeholders || []).join(localeListSep) || '-' }}</el-descriptions-item>
        </el-descriptions>
        <div v-if="dpiaDetail.status !== 'completed'" class="mt-3">
          <el-form :model="dpiaEditForm" label-width="100px" size="small">
            <el-form-item :label="t('pipl_compliance_page.columns.status')">
              <el-select v-model="dpiaEditForm.status" style="width:200px">
                <el-option v-for="(l, k) in dpiaEditStatusLabels" :key="k" :label="l" :value="k" />
              </el-select>
            </el-form-item>
            <el-form-item :label="t('pipl_compliance_page.forms.necessity_assessment')"><el-input v-model="dpiaEditForm.necessity_assessment" type="textarea" :rows="2" /></el-form-item>
            <el-form-item :label="t('pipl_compliance_page.forms.risk_assessment')"><el-input v-model="dpiaEditForm.risk_assessment" type="textarea" :rows="2" /></el-form-item>
            <el-form-item :label="t('pipl_compliance_page.forms.mitigation_measures')"><el-input v-model="dpiaEditForm.mitigation_measures" type="textarea" :rows="2" /></el-form-item>
            <el-form-item :label="t('pipl_compliance_page.forms.conclusion')"><el-input v-model="dpiaEditForm.conclusion" type="textarea" :rows="2" /></el-form-item>
          </el-form>
          <div class="text-right">
            <el-button type="primary" :loading="savingDpia" @click="saveDpiaEdit">{{ t('pipl_compliance_page.buttons.save_progress') }}</el-button>
          </div>
        </div>
        <template v-else>
          <h5 class="section-title">{{ t('pipl_compliance_page.assessment_conclusions') }}</h5>
          <p><strong>{{ t('pipl_compliance_page.conclusion_labels.necessity') }}</strong>{{ dpiaDetail.necessity_assessment }}</p>
          <p><strong>{{ t('pipl_compliance_page.conclusion_labels.risk') }}</strong>{{ dpiaDetail.risk_assessment }}</p>
          <p><strong>{{ t('pipl_compliance_page.conclusion_labels.mitigation') }}</strong>{{ dpiaDetail.mitigation_measures }}</p>
          <p><strong>{{ t('pipl_compliance_page.conclusion_labels.conclusion') }}</strong>{{ dpiaDetail.conclusion }}</p>
        </template>
      </template>
    </el-dialog>

    <!-- DPIA 完成 -->
    <el-dialog v-model="dpiaCompleteVisible" :title="t('pipl_compliance_page.dialogs.complete_dpia')" width="560px">
      <el-form :model="dpiaCompleteForm" label-width="100px" size="small">
        <el-form-item :label="t('pipl_compliance_page.forms.necessity_assessment')" required><el-input v-model="dpiaCompleteForm.necessity_assessment" type="textarea" :rows="3" /></el-form-item>
        <el-form-item :label="t('pipl_compliance_page.forms.risk_assessment')" required><el-input v-model="dpiaCompleteForm.risk_assessment" type="textarea" :rows="3" /></el-form-item>
        <el-form-item :label="t('pipl_compliance_page.forms.mitigation_measures')" required><el-input v-model="dpiaCompleteForm.mitigation_measures" type="textarea" :rows="3" /></el-form-item>
        <el-form-item :label="t('pipl_compliance_page.forms.conclusion')" required><el-input v-model="dpiaCompleteForm.conclusion" type="textarea" :rows="2" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dpiaCompleteVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="success" :loading="savingDpia" @click="submitDpiaComplete">{{ t('pipl_compliance_page.buttons.complete_assessment') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import i18n from '@/i18n';
import { ElMessage } from 'element-plus';
import { Plus, Search, CircleCheck, CircleClose } from '@element-plus/icons-vue';
import piplApi from '@/api/pipl';

const { t, locale } = useI18n();
const apiZh = (key) => i18n.global.t(key, {}, { locale: 'zh_CN' });

const activeTab = ref('inventory');
const stats = ref({});
const scanLoading = ref(false);
const loading = reactive({ stats: false, inventory: false, transfers: false, dpias: false, definitions: false });

const categoryLabels = computed(() => ({
  person: t('pipl_compliance_page.category.person'),
  general: t('pipl_compliance_page.category.general'),
  sensitive: t('pipl_compliance_page.category.sensitive'),
  private: t('pipl_compliance_page.category.private'),
}));
const classificationLabels = computed(() => ({
  L1: t('pipl_compliance_page.classification.L1'),
  L2: t('pipl_compliance_page.classification.L2'),
  L3: t('pipl_compliance_page.classification.L3'),
  L4: t('pipl_compliance_page.classification.L4'),
}));
const legalBasisLabels = computed(() => ({
  consent: t('pipl_compliance_page.legal_basis.consent'),
  standard_clauses: t('pipl_compliance_page.legal_basis.standard_clauses'),
  adequacy: t('pipl_compliance_page.legal_basis.adequacy'),
  safe_harbor: t('pipl_compliance_page.legal_basis.safe_harbor'),
  other: t('pipl_compliance_page.legal_basis.other'),
}));
const transferMethodLabels = computed(() => ({
  api: t('pipl_compliance_page.transfer_method.api'),
  sdk: t('pipl_compliance_page.transfer_method.sdk'),
  manual: t('pipl_compliance_page.transfer_method.manual'),
  cloud: t('pipl_compliance_page.transfer_method.cloud'),
}));
const transferStatusLabels = computed(() => ({
  active: t('pipl_compliance_page.transfer_status.active'),
  expired: t('pipl_compliance_page.transfer_status.expired'),
  revoked: t('pipl_compliance_page.transfer_status.revoked'),
}));
const dpiaStatusLabels = computed(() => ({
  draft: t('pipl_compliance_page.dpia_status.draft'),
  in_progress: t('pipl_compliance_page.dpia_status.in_progress'),
  completed: t('pipl_compliance_page.dpia_status.completed'),
  archived: t('pipl_compliance_page.dpia_status.archived'),
}));
const dpiaEditStatusLabels = computed(() => ({
  draft: t('pipl_compliance_page.dpia_status.draft'),
  in_progress: t('pipl_compliance_page.dpia_status.in_progress'),
}));
const localeListSep = computed(() => (locale.value === 'en' ? ', ' : '、'));

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
function formatDate(val) {
  if (!val) return '-';
  const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
  return new Date(val).toLocaleDateString(loc);
}
function isOverdue(tVal) { return tVal && new Date(tVal) <= new Date(); }

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
      ElMessage.success(res.message || t('pipl_compliance_page.messages.scan_done', { n: res.data?.items_created ?? 0 }));
      loadStats();
      loadInventory();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || t('pipl_compliance_page.messages.scan_failed')); }
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
      ElMessage.success(t('pipl_compliance_page.messages.updated'));
      invEditVisible.value = false;
      loadInventory();
      loadStats();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || t('pipl_compliance_page.messages.save_failed')); }
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
    ElMessage.warning(t('pipl_compliance_page.messages.required_fields')); return;
  }
  savingTransfer.value = true;
  try {
    const payload = { ...transferForm };
    const res = transferEditId.value
      ? await piplApi.updateCrossBorderTransfer(transferEditId.value, payload)
      : await piplApi.createCrossBorderTransfer(payload);
    if (res.data.success) {
      ElMessage.success(transferEditId.value ? t('pipl_compliance_page.messages.updated') : t('pipl_compliance_page.messages.created'));
      showTransferForm.value = false;
      resetTransferForm();
      loadTransfers();
      loadStats();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || t('messages.failed')); }
  finally { savingTransfer.value = false; }
}

async function submitReview() {
  if (!reviewForm.impact_assessment.trim()) { ElMessage.warning(t('pipl_compliance_page.messages.assessment_required')); return; }
  savingReview.value = true;
  try {
    const { data: res } = await piplApi.reviewCrossBorderTransfer(reviewTarget.value.id, reviewForm);
    if (res.success) {
      ElMessage.success(t('pipl_compliance_page.messages.review_submitted'));
      reviewVisible.value = false;
      loadTransfers();
      loadStats();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || t('pipl_compliance_page.messages.submit_failed')); }
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
  if (!dpiaCreateForm.title.trim()) { ElMessage.warning(t('pipl_compliance_page.messages.title_required')); return; }
  savingDpia.value = true;
  try {
    const { data: res } = await piplApi.createDpia({ ...dpiaCreateForm });
    if (res.success) {
      ElMessage.success(t('pipl_compliance_page.messages.dpia_created'));
      dpiaCreateVisible.value = false;
      loadDpias();
      loadStats();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || t('pipl_compliance_page.messages.create_failed')); }
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
  } catch { ElMessage.error(t('messages.load_failed')); }
}

async function saveDpiaEdit() {
  savingDpia.value = true;
  try {
    const { data: res } = await piplApi.updateDpia(dpiaDetail.value.id, { ...dpiaEditForm });
    if (res.success) {
      ElMessage.success(t('pipl_compliance_page.messages.saved'));
      dpiaDetail.value = res.data;
      loadDpias();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || t('pipl_compliance_page.messages.save_failed')); }
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
    ElMessage.warning(t('pipl_compliance_page.messages.all_assessment_required')); return;
  }
  savingDpia.value = true;
  try {
    const { data: res } = await piplApi.completeDpia(dpiaCompleteTarget.value.id, { ...f });
    if (res.success) {
      ElMessage.success(t('pipl_compliance_page.messages.dpia_completed'));
      dpiaCompleteVisible.value = false;
      loadDpias();
      loadStats();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || t('pipl_compliance_page.messages.submit_failed')); }
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
.stat-card.info { border-left: 3px solid #0f172a; }
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
