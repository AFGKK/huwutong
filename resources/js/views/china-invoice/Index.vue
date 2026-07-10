<template>
  <div class="china-invoice-page">
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.issued_invoices }}</div><div class="stat-label">已开票</div></div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.pending_invoices }}</div><div class="stat-label">待开票</div></div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">¥{{ formatMoney(stats.monthly_amount) }}</div><div class="stat-label">本月开票金额</div></div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_reports }}</div><div class="stat-label">税务报告</div></div></el-card></el-col>
    </el-row>

    <el-tabs v-model="activeTab">
      <el-tab-pane label="🧾 发票管理" name="invoice">
        <el-card><template #header><div class="flex items-center justify-between">
          <span>增值税发票</span>
          <el-button type="primary" size="small" @click="showIssueDialog=true">开票</el-button>
        </div></template>
        <el-table :data="invoices" stripe size="small" v-loading="loading">
          <el-table-column prop="invoice_code" label="发票代码" width="130" />
          <el-table-column prop="invoice_no" label="号码" width="80" />
          <el-table-column prop="invoice_type" label="类型" width="100">
            <template #default="{row}">{{ typeMap[row.invoice_type]||row.invoice_type }}</template>
          </el-table-column>
          <el-table-column prop="buyer_name" label="购买方" width="120" />
          <el-table-column prop="total_amount" label="金额" width="90">
            <template #default="{row}">¥{{ row.total_amount }}</template>
          </el-table-column>
          <el-table-column prop="status" label="状态" width="80">
            <template #default="{row}"><el-tag :type="statusType(row.status)" size="small">{{ statusMap[row.status] }}</el-tag></template>
          </el-table-column>
          <el-table-column prop="issued_at" label="开票日期" width="100">
            <template #default="{row}">{{ row.issued_at?row.issued_at.slice(0,10):'-' }}</template>
          </el-table-column>
          <el-table-column label="操作" width="160" fixed="right">
            <template #default="{row}">
              <el-button size="small" @click="viewInvoice(row)">详情</el-button>
              <el-button v-if="row.status==='issued'" size="small" type="warning" plain @click="handleRedLetter(row)">红冲</el-button>
              <el-button v-if="row.status==='pending'" size="small" type="danger" plain @click="handleVoid(row)">作废</el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-pagination v-if="total>0" v-model:current-page="page" :total="total" :page-size="20" layout="prev,pager,next" small class="mt-2" @current-change="loadInvoices" /></el-card>
      </el-tab-pane>

      <el-tab-pane label="💻 税控设备" name="device">
        <el-card><template #header><div class="flex items-center justify-between"><span>税控设备</span><el-button size="small" type="primary" @click="showDeviceDialog=true">添加设备</el-button></div></template>
        <el-empty v-if="devices.length===0" description="暂无税控设备" />
        <el-table v-else :data="devices" stripe size="small">
          <el-table-column prop="name" label="名称" width="120" />
          <el-table-column prop="device_type" label="类型" width="80">
            <template #default="{row}">{{ {ukey:'税务UKey',tax_disk:'税控盘',cloud:'云开票'}[row.device_type] }}</template>
          </el-table-column>
          <el-table-column prop="company_name" label="企业名称" width="160" />
          <el-table-column prop="taxpayer_id" label="税号" width="120" />
          <el-table-column prop="is_active" label="状态" width="70">
            <template #default="{row}"><el-tag :type="row.is_active?'success':'danger'" size="small">{{ row.is_active?'启用':'停用' }}</el-tag></template>
          </el-table-column>
          <el-table-column label="操作" width="80"><template #default="{row}"><el-button size="small" type="danger" plain @click="deleteDeviceRow(row)">删除</el-button></template></el-table-column>
        </el-table></el-card>
      </el-tab-pane>

      <el-tab-pane label="📋 税务报告" name="report">
        <el-card><template #header><div class="flex items-center justify-between"><span>月度税务报告</span><el-button size="small" @click="handleGenerateReport">生成本月报告</el-button></div></template>
        <el-empty v-if="reports.length===0" description="暂无税务报告" />
        <el-table v-else :data="reports" stripe size="small">
          <el-table-column prop="period" label="所属期" width="80" />
          <el-table-column prop="total_sales" label="销售额" width="100"><template #default="{r}">¥{{ r.total_sales }}</template></el-table-column>
          <el-table-column prop="total_tax" label="应纳税额" width="100" />
          <el-table-column prop="deductible_tax" label="可抵扣" width="100" />
          <el-table-column prop="payable_tax" label="应缴税额" width="100" />
          <el-table-column prop="status" label="状态" width="80">
            <template #default="{r}"><el-tag size="small">{{ r.status }}</el-tag></template>
          </el-table-column>
        </el-table></el-card>
      </el-tab-pane>
    </el-tabs>

    <!-- 发票详情 -->
    <el-dialog v-model="showDetailDialog" :title="'发票详情'" width="600px" destroy-on-close>
      <el-descriptions v-if="currentInvoice" :column="2" border size="small">
        <el-descriptions-item label="发票代码" :span="1">{{ currentInvoice.invoice_code }}</el-descriptions-item>
        <el-descriptions-item label="发票号码">{{ currentInvoice.invoice_no }}</el-descriptions-item>
        <el-descriptions-item label="购买方" :span="2">{{ currentInvoice.buyer_name }}</el-descriptions-item>
        <el-descriptions-item label="购买方税号" :span="2">{{ currentInvoice.buyer_tax_id||'-' }}</el-descriptions-item>
        <el-descriptions-item label="金额(不含税)">¥{{ currentInvoice.amount }}</el-descriptions-item>
        <el-descriptions-item label="税率">{{ currentInvoice.tax_rate }}%</el-descriptions-item>
        <el-descriptions-item label="税额">¥{{ currentInvoice.tax_amount }}</el-descriptions-item>
        <el-descriptions-item label="价税合计">¥{{ currentInvoice.total_amount }}</el-descriptions-item>
        <el-descriptions-item label="税控码" :span="2"><code class="text-xs">{{ currentInvoice.tax_control_code||'-' }}</code></el-descriptions-item>
        <el-descriptions-item label="状态" :span="2">{{ statusMap[currentInvoice.status] }}</el-descriptions-item>
      </el-descriptions>
      <el-table v-if="currentInvoice?.items?.length" :data="currentInvoice.items" stripe size="small" class="mt-2">
        <el-table-column prop="item_name" label="名称" />
        <el-table-column prop="quantity" label="数量" width="60" />
        <el-table-column prop="unit_price" label="单价" width="80" />
        <el-table-column prop="amount" label="金额" width="80" />
        <el-table-column prop="tax_rate" label="税率" width="60"><template #default="{r}">{{ r.tax_rate }}%</template></el-table-column>
      </el-table>
    </el-dialog>

    <!-- 开票对话框 -->
    <el-dialog v-model="showIssueDialog" title="开票" width="550px" destroy-on-close>
      <el-form :model="issueForm" label-width="100px">
        <el-form-item label="发票类型"><el-select v-model="issueForm.invoice_type" style="width:100%">
          <el-option label="增值税专用发票" value="vat_special" /><el-option label="增值税普通发票" value="vat_normal" />
        </el-select></el-form-item>
        <el-form-item label="购买方名称"><el-input v-model="issueForm.buyer_name" /></el-form-item>
        <el-form-item label="购买方税号"><el-input v-model="issueForm.buyer_tax_id" placeholder="纳税人识别号" /></el-form-item>
        <el-form-item label="金额(不含税)"><el-input-number v-model="issueForm.amount" :min="0.01" :step="100" style="width:200px" /></el-form-item>
        <el-form-item label="税率"><el-select v-model="issueForm.tax_rate" style="width:200px">
          <el-option label="13%" :value="13" /><el-option label="9%" :value="9" /><el-option label="6%" :value="6" /><el-option label="3%" :value="3" /><el-option label="0%" :value="0" />
        </el-select></el-form-item>
        <el-form-item label="行项目"><div v-for="(it,i) in issueForm.items" :key="i" class="item-row flex gap-1 mb-1">
          <el-input v-model="it.item_name" placeholder="名称" size="small" style="width:150px" />
          <el-input-number v-model="it.quantity" :min="1" size="small" style="width:80px" />
          <el-input-number v-model="it.unit_price" :min="0" :step="10" size="small" style="width:110px" />
          <el-button size="small" type="danger" plain @click="issueForm.items.splice(i,1)">×</el-button>
        </div>
        <el-button size="small" @click="issueForm.items.push({item_name:'',quantity:1,unit_price:0,tax_rate:issueForm.tax_rate})">+ 添加行</el-button></el-form-item>
      </el-form>
      <template #footer><el-button @click="showIssueDialog=false">取消</el-button><el-button type="primary" :loading="submitting" @click="handleIssue">开票</el-button></template>
    </el-dialog>

    <!-- 添加设备 -->
    <el-dialog v-model="showDeviceDialog" title="添加税控设备" width="500px" destroy-on-close>
      <el-form :model="deviceForm" label-width="100px">
        <el-form-item label="名称"><el-input v-model="deviceForm.name" /></el-form-item>
        <el-form-item label="类型"><el-select v-model="deviceForm.device_type" style="width:100%">
          <el-option label="税务UKey" value="ukey" /><el-option label="税控盘" value="tax_disk" /><el-option label="云开票" value="cloud" />
        </el-select></el-form-item>
        <el-form-item label="企业名称"><el-input v-model="deviceForm.company_name" /></el-form-item>
        <el-form-item label="纳税人识别号"><el-input v-model="deviceForm.taxpayer_id" /></el-form-item>
      </el-form>
      <template #footer><el-button @click="showDeviceDialog=false">取消</el-button><el-button type="primary" @click="handleAddDevice">添加</el-button></template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  getChinaInvoiceStats, getDevices, createDevice, deleteDevice,
  getInvoices, issueInvoice, getInvoice, redLetter, voidInvoice,
  getTaxReports, generateTaxReport,
} from '../../api/chinaInvoice';

const activeTab = ref('invoice'); const loading = ref(false); const submitting = ref(false);
const stats = reactive({issued_invoices:0,pending_invoices:0,monthly_amount:0,total_reports:0});
const invoices = ref([]); const devices = ref([]); const reports = ref([]);
const currentInvoice = ref(null); const page = ref(1); const total = ref(0);
const showIssueDialog = ref(false); const showDetailDialog = ref(false); const showDeviceDialog = ref(false);
const issueForm = reactive({invoice_type:'vat_normal',buyer_name:'',buyer_tax_id:'',amount:1000,tax_rate:13,items:[{item_name:'License授权费',quantity:1,unit_price:1000,tax_rate:13}]});
const deviceForm = reactive({name:'',device_type:'ukey',company_name:'',taxpayer_id:''});
const typeMap = {vat_special:'专票',vat_normal:'普票',fiscal_bill:'财政票据'};
const statusMap = {pending:'待开票',issued:'已开票',voided:'已作废',red_letter:'已红冲'};
const statusType = (s) => ({pending:'info',issued:'success',voided:'danger',red_letter:'warning'}[s]||'info');
function formatMoney(v) { const n=Number(v); return n>=10000?(n/10000).toFixed(1)+'万':n.toFixed(2); }

async function loadStats() { try {const {data}=await getChinaInvoiceStats();Object.assign(stats,data.data);}catch{} }
async function loadInvoices() { loading.value=true; try{const{data}=await getInvoices({page:page.value});invoices.value=data.data.data;total.value=data.data.total;}catch{}finally{loading.value=false;} }
async function loadDevices() { try{const{data}=await getDevices();devices.value=data.data;}catch{} }
async function loadReports() { try{const{data}=await getTaxReports();reports.value=data.data;}catch{} }

async function handleIssue() {
  submitting.value=true;
  try{await issueInvoice({...issueForm});ElMessage.success('开票成功');showIssueDialog.value=false;await loadInvoices();await loadStats();}
  catch(e){ElMessage.error(e.response?.data?.message||'开票失败')}finally{submitting.value=false}
}
async function handleRedLetter(row) {
  ElMessageBox.prompt('红冲原因','确认红冲').then(async({value})=>{await redLetter(row.id,value);ElMessage.success('红冲成功');await loadInvoices();}).catch(()=>{});
}
async function handleVoid(row) {
  ElMessageBox.confirm(`作废发票 ${row.invoice_code}${row.invoice_no}?`,'确认',{type:'warning'}).then(async()=>{await voidInvoice(row.id);ElMessage.success('已作废');await loadInvoices();}).catch(()=>{});
}
async function viewInvoice(row) {
  showDetailDialog.value=true;
  try{const{data}=await getInvoice(row.id);currentInvoice.value=data.data;}catch{currentInvoice.value=row;}
}
async function handleAddDevice() {
  try{await createDevice({...deviceForm});ElMessage.success('添加成功');showDeviceDialog.value=false;await loadDevices();}catch{}
}
function deleteDeviceRow(row) {
  ElMessageBox.confirm(`删除设备「${row.name}」?`,'确认',{type:'warning'}).then(async()=>{await deleteDevice(row.id);ElMessage.success('已删除');await loadDevices();}).catch(()=>{});
}
async function handleGenerateReport() {
  const period = new Date().toISOString().slice(0,7);
  try{await generateTaxReport(period);ElMessage.success('报告已生成');await loadReports();}catch{}
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
