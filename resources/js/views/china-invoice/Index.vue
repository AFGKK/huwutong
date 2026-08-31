<template>
  <div class="china-invoice-page">
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.issued_invoices }}</div><div class="stat-label">{{ t('china_invoice_page.stats.issued') }}</div></div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.pending_invoices }}</div><div class="stat-label">{{ t('china_invoice_page.stats.pending') }}</div></div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">¥{{ formatMoney(stats.monthly_amount) }}</div><div class="stat-label">{{ t('china_invoice_page.stats.monthly_amount') }}</div></div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_reports }}</div><div class="stat-label">{{ t('china_invoice_page.stats.tax_reports') }}</div></div></el-card></el-col>
    </el-row>

    <el-tabs v-model="activeTab">
      <el-tab-pane :label="t('china_invoice_page.tabs.invoices')" name="invoice">
        <el-card><template #header><div class="flex items-center justify-between">
          <span>{{ t('china_invoice_page.vat_invoices_title') }}</span>
          <el-button type="primary" size="small" @click="showIssueDialog=true">{{ t('china_invoice_page.issue_btn') }}</el-button>
        </div></template>
        <el-table :data="invoices" stripe size="small" v-loading="loading">
          <el-table-column prop="invoice_code" :label="t('china_invoice_page.cols.invoice_code')" width="130" />
          <el-table-column prop="invoice_no" :label="t('china_invoice_page.cols.invoice_no')" width="80" />
          <el-table-column prop="invoice_type" :label="t('billing_page.col_type')" width="100">
            <template #default="{row}">{{ typeMap[row.invoice_type]||row.invoice_type }}</template>
          </el-table-column>
          <el-table-column prop="buyer_name" :label="t('china_invoice_page.cols.buyer')" width="120" />
          <el-table-column prop="total_amount" :label="t('billing_page.col_amount')" width="90">
            <template #default="{row}">¥{{ row.total_amount }}</template>
          </el-table-column>
          <el-table-column prop="status" :label="t('billing_page.col_status')" width="80">
            <template #default="{row}"><el-tag :type="statusType(row.status)" size="small">{{ statusMap[row.status] }}</el-tag></template>
          </el-table-column>
          <el-table-column prop="issued_at" :label="t('china_invoice_page.cols.issued_at')" width="100">
            <template #default="{row}">{{ row.issued_at?row.issued_at.slice(0,10):'-' }}</template>
          </el-table-column>
          <el-table-column :label="t('billing_page.col_actions')" width="160" fixed="right">
            <template #default="{row}">
              <el-button size="small" @click="viewInvoice(row)">{{ t('actions.view_details') }}</el-button>
              <el-button v-if="row.status==='issued'" size="small" type="warning" plain @click="handleRedLetter(row)">{{ t('china_invoice_page.red_letter_btn') }}</el-button>
              <el-button v-if="row.status==='pending'" size="small" type="danger" plain @click="handleVoid(row)">{{ t('china_invoice_page.void_btn') }}</el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-pagination v-if="total>0" v-model:current-page="page" :total="total" :page-size="20" layout="prev,pager,next" small class="mt-2" @current-change="loadInvoices" /></el-card>
      </el-tab-pane>

      <el-tab-pane :label="t('china_invoice_page.tabs.devices')" name="device">
        <el-card><template #header><div class="flex items-center justify-between"><span>{{ t('china_invoice_page.devices_title') }}</span><el-button size="small" type="primary" @click="showDeviceDialog=true">{{ t('china_invoice_page.add_device_btn') }}</el-button></div></template>
        <el-empty v-if="devices.length===0" :description="t('china_invoice_page.empty_devices')" />
        <el-table v-else :data="devices" stripe size="small">
          <el-table-column prop="name" :label="t('billing_page.col_name')" width="120" />
          <el-table-column prop="device_type" :label="t('billing_page.col_type')" width="80">
            <template #default="{row}">{{ deviceTypeMap[row.device_type]||row.device_type }}</template>
          </el-table-column>
          <el-table-column prop="company_name" :label="t('china_invoice_page.cols.company_name')" width="160" />
          <el-table-column prop="taxpayer_id" :label="t('china_invoice_page.cols.taxpayer_id')" width="120" />
          <el-table-column prop="is_active" :label="t('billing_page.col_status')" width="70">
            <template #default="{row}"><el-tag :type="row.is_active?'success':'danger'" size="small">{{ row.is_active ? t('invoice_enhance_page.status_active') : t('invoice_enhance_page.status_inactive') }}</el-tag></template>
          </el-table-column>
          <el-table-column :label="t('billing_page.col_actions')" width="80"><template #default="{row}"><el-button size="small" type="danger" plain @click="deleteDeviceRow(row)">{{ t('actions.delete') }}</el-button></template></el-table-column>
        </el-table></el-card>
      </el-tab-pane>

      <el-tab-pane :label="t('china_invoice_page.tabs.reports')" name="report">
        <el-card><template #header><div class="flex items-center justify-between"><span>{{ t('china_invoice_page.monthly_reports_title') }}</span><el-button size="small" @click="handleGenerateReport">{{ t('china_invoice_page.generate_report_btn') }}</el-button></div></template>
        <el-empty v-if="reports.length===0" :description="t('china_invoice_page.empty_reports')" />
        <el-table v-else :data="reports" stripe size="small">
          <el-table-column prop="period" :label="t('china_invoice_page.cols.period')" width="80" />
          <el-table-column prop="total_sales" :label="t('china_invoice_page.cols.total_sales')" width="100"><template #default="{r}">¥{{ r.total_sales }}</template></el-table-column>
          <el-table-column prop="total_tax" :label="t('china_invoice_page.cols.total_tax')" width="100" />
          <el-table-column prop="deductible_tax" :label="t('china_invoice_page.cols.deductible_tax')" width="100" />
          <el-table-column prop="payable_tax" :label="t('china_invoice_page.cols.payable_tax')" width="100" />
          <el-table-column prop="status" :label="t('billing_page.col_status')" width="80">
            <template #default="{r}"><el-tag size="small">{{ r.status }}</el-tag></template>
          </el-table-column>
        </el-table></el-card>
      </el-tab-pane>
    </el-tabs>

    <!-- 发票详情 -->
    <el-dialog v-model="showDetailDialog" :title="t('china_invoice_page.invoice_detail_title')" width="600px" destroy-on-close>
      <el-descriptions v-if="currentInvoice" :column="2" border size="small">
        <el-descriptions-item :label="t('china_invoice_page.cols.invoice_code')" :span="1">{{ currentInvoice.invoice_code }}</el-descriptions-item>
        <el-descriptions-item :label="t('china_invoice_page.cols.invoice_no_full')">{{ currentInvoice.invoice_no }}</el-descriptions-item>
        <el-descriptions-item :label="t('china_invoice_page.cols.buyer')" :span="2">{{ currentInvoice.buyer_name }}</el-descriptions-item>
        <el-descriptions-item :label="t('china_invoice_page.cols.buyer_tax_id')" :span="2">{{ currentInvoice.buyer_tax_id||'-' }}</el-descriptions-item>
        <el-descriptions-item :label="t('china_invoice_page.cols.amount_excl_tax')">¥{{ currentInvoice.amount }}</el-descriptions-item>
        <el-descriptions-item :label="t('tax_page.cols.rate')">{{ currentInvoice.tax_rate }}%</el-descriptions-item>
        <el-descriptions-item :label="t('tax_page.calc.tax_amount')">¥{{ currentInvoice.tax_amount }}</el-descriptions-item>
        <el-descriptions-item :label="t('china_invoice_page.cols.total_incl_tax')">¥{{ currentInvoice.total_amount }}</el-descriptions-item>
        <el-descriptions-item :label="t('china_invoice_page.cols.tax_control_code')" :span="2"><code class="text-xs">{{ currentInvoice.tax_control_code||'-' }}</code></el-descriptions-item>
        <el-descriptions-item :label="t('billing_page.col_status')" :span="2">{{ statusMap[currentInvoice.status] }}</el-descriptions-item>
      </el-descriptions>
      <el-table v-if="currentInvoice?.items?.length" :data="currentInvoice.items" stripe size="small" class="mt-2">
        <el-table-column prop="item_name" :label="t('billing_page.col_name')" />
        <el-table-column prop="quantity" :label="t('china_invoice_page.cols.quantity')" width="60" />
        <el-table-column prop="unit_price" :label="t('china_invoice_page.cols.unit_price')" width="80" />
        <el-table-column prop="amount" :label="t('billing_page.col_amount')" width="80" />
        <el-table-column prop="tax_rate" :label="t('tax_page.cols.rate')" width="60"><template #default="{r}">{{ r.tax_rate }}%</template></el-table-column>
      </el-table>
    </el-dialog>

    <!-- 开票对话框 -->
    <el-dialog v-model="showIssueDialog" :title="t('china_invoice_page.issue_btn')" width="550px" destroy-on-close>
      <el-form :model="issueForm" label-width="100px">
        <el-form-item :label="t('china_invoice_page.form.invoice_type')"><el-select v-model="issueForm.invoice_type" style="width:100%">
          <el-option :label="t('china_invoice_page.types.vat_special_full')" value="vat_special" /><el-option :label="t('china_invoice_page.types.vat_normal_full')" value="vat_normal" />
        </el-select></el-form-item>
        <el-form-item :label="t('china_invoice_page.form.buyer_name')"><el-input v-model="issueForm.buyer_name" /></el-form-item>
        <el-form-item :label="t('china_invoice_page.form.buyer_tax_id')"><el-input v-model="issueForm.buyer_tax_id" :placeholder="t('china_invoice_page.form.taxpayer_id_ph')" /></el-form-item>
        <el-form-item :label="t('china_invoice_page.cols.amount_excl_tax')"><el-input-number v-model="issueForm.amount" :min="0.01" :step="100" style="width:200px" /></el-form-item>
        <el-form-item :label="t('tax_page.cols.rate')"><el-select v-model="issueForm.tax_rate" style="width:200px">
          <el-option label="13%" :value="13" /><el-option label="9%" :value="9" /><el-option label="6%" :value="6" /><el-option label="3%" :value="3" /><el-option label="0%" :value="0" />
        </el-select></el-form-item>
        <el-form-item :label="t('china_invoice_page.form.line_items')"><div v-for="(it,i) in issueForm.items" :key="i" class="item-row flex gap-1 mb-1">
          <el-input v-model="it.item_name" :placeholder="t('billing_page.col_name')" size="small" style="width:150px" />
          <el-input-number v-model="it.quantity" :min="1" size="small" style="width:80px" />
          <el-input-number v-model="it.unit_price" :min="0" :step="10" size="small" style="width:110px" />
          <el-button size="small" type="danger" plain @click="issueForm.items.splice(i,1)">×</el-button>
        </div>
        <el-button size="small" @click="issueForm.items.push({item_name:'',quantity:1,unit_price:0,tax_rate:issueForm.tax_rate})">{{ t('china_invoice_page.form.add_line') }}</el-button></el-form-item>
      </el-form>
      <template #footer><el-button @click="showIssueDialog=false">{{ t('actions.cancel') }}</el-button><el-button type="primary" :loading="submitting" @click="handleIssue">{{ t('china_invoice_page.issue_btn') }}</el-button></template>
    </el-dialog>

    <!-- 添加设备 -->
    <el-dialog v-model="showDeviceDialog" :title="t('china_invoice_page.add_device_dialog_title')" width="500px" destroy-on-close>
      <el-form :model="deviceForm" label-width="100px">
        <el-form-item :label="t('billing_page.col_name')"><el-input v-model="deviceForm.name" /></el-form-item>
        <el-form-item :label="t('billing_page.col_type')"><el-select v-model="deviceForm.device_type" style="width:100%">
          <el-option :label="t('china_invoice_page.device_types.ukey')" value="ukey" /><el-option :label="t('china_invoice_page.device_types.tax_disk')" value="tax_disk" /><el-option :label="t('china_invoice_page.device_types.cloud')" value="cloud" />
        </el-select></el-form-item>
        <el-form-item :label="t('china_invoice_page.cols.company_name')"><el-input v-model="deviceForm.company_name" /></el-form-item>
        <el-form-item :label="t('china_invoice_page.form.taxpayer_id')"><el-input v-model="deviceForm.taxpayer_id" /></el-form-item>
      </el-form>
      <template #footer><el-button @click="showDeviceDialog=false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="handleAddDevice">{{ t('china_invoice_page.add_btn') }}</el-button></template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  getChinaInvoiceStats, getDevices, createDevice, deleteDevice,
  getInvoices, issueInvoice, getInvoice, redLetter, voidInvoice,
  getTaxReports, generateTaxReport,
} from '../../api/chinaInvoice';

const { t } = useI18n();

const activeTab = ref('invoice'); const loading = ref(false); const submitting = ref(false);
const stats = reactive({issued_invoices:0,pending_invoices:0,monthly_amount:0,total_reports:0});
const invoices = ref([]); const devices = ref([]); const reports = ref([]);
const currentInvoice = ref(null); const page = ref(1); const total = ref(0);
const showIssueDialog = ref(false); const showDetailDialog = ref(false); const showDeviceDialog = ref(false);

function createDefaultIssueForm() {
  return {
    invoice_type: 'vat_normal',
    buyer_name: '',
    buyer_tax_id: '',
    amount: 1000,
    tax_rate: 13,
    items: [{ item_name: t('china_invoice_page.default_item_name'), quantity: 1, unit_price: 1000, tax_rate: 13 }],
  };
}

const issueForm = reactive(createDefaultIssueForm());
const deviceForm = reactive({name:'',device_type:'ukey',company_name:'',taxpayer_id:''});

const typeMap = computed(() => ({
  vat_special: t('china_invoice_page.types.vat_special'),
  vat_normal: t('china_invoice_page.types.vat_normal'),
  fiscal_bill: t('china_invoice_page.types.fiscal_bill'),
}));

const statusMap = computed(() => ({
  pending: t('china_invoice_page.status.pending'),
  issued: t('china_invoice_page.status.issued'),
  voided: t('china_invoice_page.status.voided'),
  red_letter: t('china_invoice_page.status.red_letter'),
}));

const deviceTypeMap = computed(() => ({
  ukey: t('china_invoice_page.device_types.ukey'),
  tax_disk: t('china_invoice_page.device_types.tax_disk'),
  cloud: t('china_invoice_page.device_types.cloud'),
}));

const statusType = (s) => ({pending:'info',issued:'success',voided:'danger',red_letter:'warning'}[s]||'info');

function formatMoney(v) {
  const n = Number(v);
  if (n >= 10000) return (n / 10000).toFixed(1) + t('china_invoice_page.wan_suffix');
  return n.toFixed(2);
}

async function loadStats() { try {const {data}=await getChinaInvoiceStats();Object.assign(stats,data.data);}catch{} }
async function loadInvoices() { loading.value=true; try{const{data}=await getInvoices({page:page.value});invoices.value=data.data.data;total.value=data.data.total;}catch{}finally{loading.value=false;} }
async function loadDevices() { try{const{data}=await getDevices();devices.value=data.data;}catch{} }
async function loadReports() { try{const{data}=await getTaxReports();reports.value=data.data;}catch{} }

async function handleIssue() {
  submitting.value=true;
  try{
    await issueInvoice({...issueForm});
    ElMessage.success(t('china_invoice_page.messages.issue_ok'));
    showIssueDialog.value=false;
    Object.assign(issueForm, createDefaultIssueForm());
    await loadInvoices();
    await loadStats();
  }
  catch(e){ElMessage.error(e.response?.data?.message||t('china_invoice_page.messages.issue_fail'))}finally{submitting.value=false}
}
async function handleRedLetter(row) {
  ElMessageBox.prompt(t('china_invoice_page.messages.red_letter_prompt'), t('china_invoice_page.messages.red_letter_confirm_title')).then(async({value})=>{
    await redLetter(row.id,value);
    ElMessage.success(t('china_invoice_page.messages.red_letter_ok'));
    await loadInvoices();
  }).catch(()=>{});
}
async function handleVoid(row) {
  ElMessageBox.confirm(
    t('china_invoice_page.messages.void_confirm', { code: row.invoice_code, no: row.invoice_no }),
    t('actions.confirm'),
    { type: 'warning' },
  ).then(async()=>{
    await voidInvoice(row.id);
    ElMessage.success(t('china_invoice_page.messages.void_ok'));
    await loadInvoices();
  }).catch(()=>{});
}
async function viewInvoice(row) {
  showDetailDialog.value=true;
  try{const{data}=await getInvoice(row.id);currentInvoice.value=data.data;}catch{currentInvoice.value=row;}
}
async function handleAddDevice() {
  try{
    await createDevice({...deviceForm});
    ElMessage.success(t('messages.success'));
    showDeviceDialog.value=false;
    await loadDevices();
  }catch{}
}
function deleteDeviceRow(row) {
  ElMessageBox.confirm(
    t('china_invoice_page.messages.delete_device_confirm', { name: row.name }),
    t('actions.confirm'),
    { type: 'warning' },
  ).then(async()=>{
    await deleteDevice(row.id);
    ElMessage.success(t('china_invoice_page.messages.deleted_ok'));
    await loadDevices();
  }).catch(()=>{});
}
async function handleGenerateReport() {
  const period = new Date().toISOString().slice(0,7);
  try{
    await generateTaxReport(period);
    ElMessage.success(t('china_invoice_page.messages.report_generated_ok'));
    await loadReports();
  }catch{}
}

onMounted(()=>{loadStats();loadInvoices();loadDevices();loadReports();});
</script>
<style scoped>
.china-invoice-page{padding:20px}
.stat-item{text-align:center;padding:8px 0}
.stat-value{font-size:28px;font-weight:700;color:var(--el-color-primary)}
.stat-label{font-size:13px;color:#909399;margin-top:4px}
.mb-4{margin-bottom:16px}.mb-1{margin-bottom:4px}.mt-2{margin-top:8px}
.flex{display:flex}.items-center{align-items:center}.justify-between{justify-content:space-between}.gap-1{gap:4px}
.text-xs{font-size:12px}
.item-row{display:flex;align-items:center}
</style>
