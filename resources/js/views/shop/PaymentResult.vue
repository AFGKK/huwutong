<template>
  <div class="payment-result-page">
    <el-card shadow="hover" class="payment-result-card">
      <div v-if="loading">{{ t('payment_result.loading') }}</div>
      <template v-else-if="order">
        <template v-if="order.status === 'paid' || order.status === 'completed'">
          <el-result icon="success" :title="t('payment_result.success_title')" :sub-title="t('payment_result.order_no', { id: order.id })">
            <template #extra>
              <div class="order-info">
                <p>{{ t('payment_result.amount') }} <strong>¥{{ order.total_amount || order.total }}</strong></p>
                <p>{{ t('payment_result.status') }} <el-tag type="success">{{ order.status === 'paid' ? t('payment_result.paid') : t('payment_result.completed') }}</el-tag></p>
              </div>
              <p style="color:#67C23A;margin:16px 0">{{ t('payment_result.license_activated') }}</p>
              <el-button type="primary" @click="$router.push('/portal/licenses')">{{ t('payment_result.view_licenses') }}</el-button>
              <el-button class="secondary-action-btn" @click="$router.push('/portal/dashboard')">{{ t('payment_result.back_portal') }}</el-button>
            </template>
          </el-result>
        </template>

        <template v-else>
          <div class="pay-header">
            <h2>{{ t('payment_result.pending_title') }}</h2>
            <p class="order-no">{{ t('payment_result.order_no', { id: order.id }) }}</p>
          </div>

          <el-divider />

          <div class="order-summary">
            <div class="summary-row">
              <span>{{ t('payment_result.items_total') }}</span>
              <span>¥{{ order.total_amount || '0.00' }}</span>
            </div>
            <div v-if="order.discount_amount > 0" class="summary-row discount">
              <span>{{ t('payment_result.discount') }}</span>
              <span>-¥{{ order.discount_amount }}</span>
            </div>
            <div class="summary-row total">
              <span>{{ t('payment_result.payable') }}</span>
              <span class="price">¥{{ order.final_amount || order.total_amount || '0.00' }}</span>
            </div>
          </div>

          <el-divider />

          <div class="gateway-section">
            <p class="gateway-title">{{ t('payment_result.choose_gateway') }}</p>
            <div class="gateway-list">
              <div
                v-for="g in paymentGateways"
                :key="g.key"
                class="gateway-item"
                :class="{ active: selectedGateway === g.key }"
                @click="selectedGateway = g.key"
              >
                <span class="gateway-radio">
                  <span v-if="selectedGateway === g.key" class="radio-dot" />
                </span>
                <span class="gateway-label">{{ g.label }}</span>
              </div>
            </div>
            <el-button
              type="primary"
              size="large"
              style="width:100%;margin-top:16px"
              :loading="paying"
              :disabled="!selectedGateway || paying"
              @click="handlePay"
            >
              {{ t('payment_result.pay_now') }}
            </el-button>
            <el-button style="width:100%;margin-top:8px" @click="$router.push('/portal/orders')">
              {{ t('payment_result.view_orders') }}
            </el-button>
          </div>
        </template>
      </template>
      <el-empty v-else :description="t('payment_result.not_found')" :image-size="60" />
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { useRoute } from 'vue-router';
import shopApi from '@/api/shop';

const { t } = useI18n();
const route = useRoute();
const order = ref(null);
const loading = ref(true);
const paying = ref(false);
const selectedGateway = ref('alipay');

const paymentGateways = computed(() => [
  { key: 'alipay', label: t('payment_result.gw_alipay') },
  { key: 'wechat', label: t('payment_result.gw_wechat') },
  { key: 'stripe', label: 'Stripe' },
  { key: 'paypal', label: 'PayPal' },
  { key: 'yipay', label: t('payment_result.gw_yipay') },
]);

onMounted(async () => {
  const orderId = route.params.id;
  if (!orderId) { loading.value = false; return; }
  try {
    const res = await shopApi.getOrder(orderId);
    order.value = res.data?.data || res.data;
  } catch {
    ElMessage.error(t('payment_result.load_fail'));
  } finally { loading.value = false; }
});

async function handlePay() {
  if (!order.value?.id || !selectedGateway.value) return;
  paying.value = true;
  try {
    const res = await shopApi.initiatePayment(order.value.id, selectedGateway.value);
    const data = res.data?.data || res.data;

    if (data?.payment_form) {
      const container = document.createElement('div');
      container.style.display = 'none';
      container.innerHTML = data.payment_form;
      document.body.appendChild(container);
      return;
    }

    if (selectedGateway.value === 'wechat' && data?.qr_code) {
      // optional QR fallback
    }

    if (data?.payment_url) {
      window.location.href = data.payment_url;
    } else if (data?.redirect_url) {
      window.location.href = data.redirect_url;
    } else {
      ElMessage.success(t('payment_result.pay_submitted'));
    }
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || t('payment_result.pay_fail'));
  } finally {
    paying.value = false;
  }
}
</script>

<style scoped>
.payment-result-page {
  padding: 16px 12px;
  min-width: 0;
  overflow-x: clip;
}
.payment-result-card {
  max-width: 520px;
  margin: 24px auto;
  text-align: center;
}
.secondary-action-btn { margin-left: 8px; }
.order-info p { margin: 4px 0; }
.pay-header { padding: 20px 0 0; }
.pay-header h2 { margin: 0 0 8px; font-size: 20px; }
.order-no { color: #909399; font-size: 13px; margin: 0; }
.order-summary { padding: 0 16px; }
.summary-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
.summary-row.discount { color: #67C23A; }
.summary-row.total { font-size: 16px; font-weight: 600; padding-top: 10px; border-top: 1px dashed #e4e7ed; }
.summary-row .price { font-size: 22px; font-weight: 700; color: #F56C6C; }
.gateway-title { font-size: 14px; color: #606266; margin: 0 0 10px; }
.gateway-list { display: flex; flex-direction: column; gap: 8px; }
.gateway-item {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 14px; border: 1px solid #e4e7ed; border-radius: 8px;
  cursor: pointer; transition: all 0.2s;
}
.gateway-item:hover { border-color: #0f172a; background: #f0f7ff; }
.gateway-item.active { border-color: #0f172a; background: #f1f5f9; }
.gateway-radio {
  width: 16px; height: 16px; border-radius: 50%;
  border: 2px solid #c0c4cc; display: flex;
  align-items: center; justify-content: center; flex-shrink: 0;
}
.gateway-item.active .gateway-radio { border-color: #0f172a; }
.radio-dot {
  width: 8px; height: 8px; border-radius: 50%; background: #0f172a;
}
.gateway-label { font-size: 14px; }

@media (max-width: 768px) {
  .payment-result-page { padding: 12px 8px; }
  .payment-result-card { margin: 12px auto; }
  .secondary-action-btn { margin-left: 0 !important; margin-top: 8px; width: 100%; }
  .payment-result-card :deep(.el-result__extra .el-button) {
    width: 100%;
    margin-left: 0 !important;
  }
}
</style>
