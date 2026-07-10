<template>
  <div class="checkout-page">
    <div class="page-header">
      <h1>订单结算</h1>
    </div>

    <el-row :gutter="24">
      <el-col :xs="24" :sm="24" :md="16">
        <el-card shadow="hover" class="mb-4">
          <template #header><span>商品明细</span></template>
          <el-table :data="items" stripe size="small">
            <el-table-column label="商品" min-width="180">
              <template #default="{ row }">{{ row.product_name || row.sku?.product_name || '商品' }}</template>
            </el-table-column>
            <el-table-column label="版本" width="100">{{ row.sku?.version || '' }}</el-table-column>
            <el-table-column label="周期" width="80">{{ cycleLabel(row.sku?.billing_cycle) }}</el-table-column>
            <el-table-column label="数量" width="80" align="center">{{ row.quantity || 1 }}</el-table-column>
            <el-table-column label="小计" width="100" align="center">¥{{ ((row.sku?.price || row.price) * (row.quantity || 1)).toFixed(2) }}</el-table-column>
          </el-table>
        </el-card>

        <el-card shadow="hover" class="mb-4">
          <template #header><span>优惠码</span></template>
          <el-input v-model="couponCode" placeholder="输入优惠码" style="width:240px;margin-right:8px" />
          <el-button @click="applyCoupon">应用</el-button>
        </el-card>
      </el-col>

      <el-col :xs="24" :sm="24" :md="8">
        <el-card shadow="hover">
          <template #header><span>订单摘要</span></template>
          <div class="summary-row"><span>商品总额</span><span>¥{{ subtotal }}</span></div>
          <div class="summary-row" v-if="discount > 0"><span>优惠</span><span class="text-success">-¥{{ discount.toFixed(2) }}</span></div>
          <el-divider style="margin:8px 0" />
          <div class="summary-row total"><span>应付总额</span><span class="total-price">¥{{ finalTotal }}</span></div>
          <el-divider style="margin:8px 0" />
          <el-button type="primary" size="large" style="width:100%" @click="handleSubmit" :loading="submitting">
            提交订单
          </el-button>
          <p class="text-muted" style="margin-top:8px;font-size:12px">
            提交后将跳转到支付页面
          </p>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import shopApi from '@/api/shop';

const items = ref([]);
const couponCode = ref('');
const submitting = ref(false);
const discount = ref(0);

const subtotal = computed(() => {
  return items.value.reduce((s, i) => s + (i.sku?.price || i.price || 0) * (i.quantity || 1), 0).toFixed(2);
});
const finalTotal = computed(() => {
  return Math.max(0, parseFloat(subtotal.value) - discount.value).toFixed(2);
});

onMounted(() => {
  try {
    const saved = sessionStorage.getItem('checkout_items');
    if (saved) items.value = JSON.parse(saved);
  } catch {}
  if (!items.value.length) {
    ElMessage.warning('购物车为空');
  }
});

function applyCoupon() {
  if (!couponCode.value) return;
  discount.value = parseFloat(subtotal.value) * 0.1;
  ElMessage.success('优惠码已应用，优惠 10%');
}

async function handleSubmit() {
  if (!items.value.length) { ElMessage.warning('购物车为空'); return; }
  submitting.value = true;
  try {
    const res = await shopApi.checkout({
      coupon_code: couponCode.value || null,
    });
    sessionStorage.removeItem('checkout_items');
    const orderId = res.data?.order?.id || res.data?.id;
    if (orderId) {
      ElMessage.success('订单已创建');
      window.location.href = `/portal/payment-result/${orderId}`;
    }
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || '下单失败');
  } finally { submitting.value = false; }
}

function cycleLabel(c) {
  return { monthly: '月付', yearly: '年付', lifetime: '终身' }[c] || c;
}
</script>

<style scoped>
.checkout-page { padding: 24px; max-width: 1000px; margin: 0 auto; }
.page-header { margin-bottom: 16px; }
.page-header h1 { margin: 0; font-size: 22px; }
.mb-4 { margin-bottom: 16px; }
.summary-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
.summary-row.total { font-size: 16px; font-weight: 600; }
.total-price { font-size: 20px; font-weight: 700; color: #F56C6C; }
.text-success { color: #67C23A; }
.text-muted { color: #909399; }

@media (max-width: 768px) {
    .checkout-page { padding: 12px; }
    .checkout-page :deep(.el-table) {
        display: block;
        overflow-x: auto;
    }
    .checkout-page :deep(.el-input) {
        width: 100% !important;
        margin-right: 0 !important;
        margin-bottom: 8px;
    }
    .checkout-page .mb-4 :deep(.el-button) {
        width: 100%;
    }
}
</style>
