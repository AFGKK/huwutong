<template>
  <div class="checkout-page">
    <div v-if="inMiniProgram" class="mp-pay-banner">
      <span>{{ t('shop_checkout.mp_banner') }}</span>
      <el-button size="small" type="warning" @click="copyPayLink">{{ t('shop_checkout.copy_link') }}</el-button>
    </div>
    <div class="page-header">
      <h1>{{ t('shop_checkout.title') }}</h1>
    </div>

    <el-row :gutter="24">
      <el-col :xs="24" :sm="24" :md="16">
        <el-card shadow="hover" class="mb-4">
          <template #header><span>{{ t('shop_checkout.items') }}</span></template>

          <div v-if="!isMobile" class="table-scroll-wrap">
            <el-table :data="items" stripe size="small">
              <el-table-column :label="t('shop_checkout.col_product')" min-width="180">
                <template #default="{ row }">{{ row.product_name || row.sku?.product_name || t('shop_checkout.product') }}</template>
              </el-table-column>
              <el-table-column :label="t('shop_checkout.col_version')" width="100">
                <template #default="{ row }">{{ row.sku?.version || '—' }}</template>
              </el-table-column>
              <el-table-column :label="t('shop_checkout.col_cycle')" width="80">
                <template #default="{ row }">{{ cycleLabel(row.sku?.billing_cycle) }}</template>
              </el-table-column>
              <el-table-column :label="t('shop_checkout.col_qty')" width="80" align="center">
                <template #default="{ row }">{{ row.quantity || 1 }}</template>
              </el-table-column>
              <el-table-column :label="t('shop_checkout.col_subtotal')" width="100" align="center">
                <template #default="{ row }">¥{{ lineTotal(row) }}</template>
              </el-table-column>
            </el-table>
          </div>

          <div v-else class="checkout-mobile-list">
            <div v-for="(row, idx) in items" :key="idx" class="checkout-mobile-item">
              <div class="checkout-mobile-name">{{ row.product_name || row.sku?.product_name || t('shop_checkout.product') }}</div>
              <div class="checkout-mobile-meta">
                <span>{{ cycleLabel(row.sku?.billing_cycle) }}</span>
                <span>×{{ row.quantity || 1 }}</span>
              </div>
              <div class="checkout-mobile-price">¥{{ lineTotal(row) }}</div>
            </div>
          </div>
        </el-card>

        <el-card shadow="hover" class="mb-4">
          <template #header><span>{{ t('shop_checkout.coupon') }}</span></template>
          <div class="coupon-apply-row">
            <el-input v-model="couponCode" :placeholder="t('shop_checkout.coupon_ph')" class="coupon-input" />
            <el-button @click="applyCoupon">{{ t('shop_checkout.apply') }}</el-button>
          </div>
        </el-card>
      </el-col>

      <el-col :xs="24" :sm="24" :md="8">
        <el-card shadow="hover" class="checkout-summary-card">
          <template #header><span>{{ t('shop_checkout.summary') }}</span></template>
          <div class="summary-row"><span>{{ t('shop_checkout.items_total') }}</span><span>¥{{ subtotal }}</span></div>
          <div class="summary-row" v-if="discount > 0"><span>{{ t('shop_checkout.discount') }}</span><span class="text-success">-¥{{ discount.toFixed(2) }}</span></div>
          <el-divider style="margin:8px 0" />
          <div class="summary-row total"><span>{{ t('shop_checkout.payable') }}</span><span class="total-price">¥{{ finalTotal }}</span></div>
          <el-divider style="margin:8px 0" />
          <el-button type="primary" size="large" class="submit-btn" @click="handleSubmit" :loading="submitting">
            {{ t('shop_checkout.submit') }}
          </el-button>
          <p class="text-muted submit-hint">
            {{ t('shop_checkout.submit_hint') }}
          </p>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { useResponsive } from '@/composables/useResponsive';
import shopApi from '@/api/shop';
import { isMiniProgram, copyCurrentUrl } from '@/utils/miniprogramEnv';

const { t } = useI18n();
const { isMobile } = useResponsive();

const items = ref([]);
const couponCode = ref('');
const submitting = ref(false);
const discount = ref(0);
const inMiniProgram = ref(false);

const subtotal = computed(() => {
  return items.value.reduce((s, i) => s + (i.sku?.price || i.price || 0) * (i.quantity || 1), 0).toFixed(2);
});
const finalTotal = computed(() => {
  return Math.max(0, parseFloat(subtotal.value) - discount.value).toFixed(2);
});

function lineTotal(row) {
  return ((row.sku?.price || row.price || 0) * (row.quantity || 1)).toFixed(2);
}

onMounted(() => {
  inMiniProgram.value = isMiniProgram();
  try {
    const saved = sessionStorage.getItem('checkout_items');
    if (saved) items.value = JSON.parse(saved);
  } catch {}
  if (!items.value.length) {
    ElMessage.warning(t('shop_checkout.cart_empty'));
  }
});

async function copyPayLink() {
  try {
    await copyCurrentUrl();
    ElMessage.success(t('shop_checkout.link_copied'));
  } catch {
    ElMessage.error(t('shop_checkout.copy_fail'));
  }
}

function applyCoupon() {
  if (!couponCode.value) return;
  discount.value = parseFloat(subtotal.value) * 0.1;
  ElMessage.success(t('shop_checkout.coupon_applied'));
}

async function handleSubmit() {
  if (!items.value.length) { ElMessage.warning(t('shop_checkout.cart_empty')); return; }
  submitting.value = true;
  try {
    const res = await shopApi.checkout({
      coupon_code: couponCode.value || null,
    });
    sessionStorage.removeItem('checkout_items');
    const orderId = res.data?.order?.id || res.data?.id;
    if (orderId) {
      ElMessage.success(t('shop_checkout.order_ok'));
      window.location.href = `/portal/payment-result/${orderId}`;
    }
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || t('shop_checkout.order_fail'));
  } finally { submitting.value = false; }
}

function cycleLabel(c) {
  return {
    monthly: t('shop_checkout.cycle_monthly'),
    yearly: t('shop_checkout.cycle_yearly'),
    lifetime: t('shop_checkout.cycle_lifetime'),
  }[c] || c;
}
</script>

<style scoped>
.mp-pay-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 14px;
  margin: -24px -24px 16px;
  background: #fff7e6;
  border-bottom: 1px solid #ffd591;
  color: #ad6800;
  font-size: 13px;
  line-height: 1.4;
}
.checkout-page { padding: 24px; max-width: 1000px; margin: 0 auto; min-width: 0; overflow-x: clip; }
.page-header { margin-bottom: 16px; }
.page-header h1 { margin: 0; font-size: 22px; }
.mb-4 { margin-bottom: 16px; }
.summary-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; gap: 12px; }
.summary-row.total { font-size: 16px; font-weight: 600; }
.total-price { font-size: 20px; font-weight: 700; color: #F56C6C; }
.text-success { color: #67C23A; }
.text-muted { color: #909399; }
.coupon-apply-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.coupon-input { flex: 1; min-width: 0; max-width: 280px; }
.submit-btn { width: 100%; }
.submit-hint { margin-top: 8px; font-size: 12px; }
.checkout-mobile-list { display: flex; flex-direction: column; gap: 12px; }
.checkout-mobile-item { padding: 12px; border: 1px solid #ebeef5; border-radius: 8px; background: #fafafa; }
.checkout-mobile-name { font-weight: 600; font-size: 14px; margin-bottom: 6px; word-break: break-word; }
.checkout-mobile-meta { display: flex; justify-content: space-between; font-size: 12px; color: #909399; }
.checkout-mobile-price { margin-top: 8px; font-size: 16px; font-weight: 700; color: #0f172a; text-align: right; }

@media (max-width: 768px) {
    .checkout-page { padding: 12px; }
    .coupon-input { max-width: none; width: 100%; }
    .coupon-apply-row { flex-direction: column; align-items: stretch; }
    .coupon-apply-row .el-button { width: 100%; }
    .checkout-summary-card { margin-top: 4px; }
}
</style>
