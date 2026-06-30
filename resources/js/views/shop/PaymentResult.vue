<template>
  <div class="payment-result-page">
    <el-card shadow="hover" style="max-width:520px;margin:60px auto;text-align:center">
      <div v-if="loading">加载中...</div>
      <template v-else-if="order">
        <!-- 已支付 -->
        <template v-if="order.status === 'paid' || order.status === 'completed'">
          <el-result icon="success" title="支付成功" :sub-title="`订单号: ${order.id}`">
            <template #extra>
              <div class="order-info">
                <p>金额: <strong>¥{{ order.total_amount || order.total }}</strong></p>
                <p>状态: <el-tag type="success">{{ order.status === 'paid' ? '已支付' : '已完成' }}</el-tag></p>
              </div>
              <p style="color:#67C23A;margin:16px 0">✅ License 已自动激活，请查收邮件</p>
              <el-button type="primary" @click="$router.push('/portal/licenses')">查看我的 License</el-button>
              <el-button @click="$router.push('/portal/dashboard')" style="margin-left:8px">返回门户</el-button>
            </template>
          </el-result>
        </template>

        <!-- 待支付 -->
        <template v-else>
          <div class="pay-header">
            <div class="pay-icon">⏳</div>
            <h2>待支付</h2>
            <p class="order-no">订单号: {{ order.id }}</p>
          </div>

          <el-divider />

          <div class="order-summary">
            <div class="summary-row">
              <span>商品总额</span>
              <span>¥{{ order.total_amount || '0.00' }}</span>
            </div>
            <div v-if="order.discount_amount > 0" class="summary-row discount">
              <span>优惠</span>
              <span>-¥{{ order.discount_amount }}</span>
            </div>
            <div class="summary-row total">
              <span>应付</span>
              <span class="price">¥{{ order.final_amount || order.total_amount || '0.00' }}</span>
            </div>
          </div>

          <el-divider />

          <div class="gateway-section">
            <p class="gateway-title">选择支付方式</p>
            <div class="gateway-list">
              <div
                v-for="g in PAYMENT_GATEWAYS"
                :key="g.key"
                class="gateway-item"
                :class="{ active: selectedGateway === g.key }"
                @click="selectedGateway = g.key"
              >
                <span class="gateway-radio">
                  <span v-if="selectedGateway === g.key" class="radio-dot" />
                </span>
                <span class="gateway-icon">{{ g.icon }}</span>
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
              去支付
            </el-button>
            <el-button style="width:100%;margin-top:8px" @click="$router.push('/portal/orders')">
              查看订单
            </el-button>
          </div>
        </template>
      </template>
      <el-empty v-else description="订单不存在" :image-size="60" />
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { useRoute } from 'vue-router';
import shopApi from '@/api/shop';

const route = useRoute();
const order = ref(null);
const loading = ref(true);
const paying = ref(false);
const selectedGateway = ref('alipay');

const PAYMENT_GATEWAYS = [
  { key: 'alipay', label: '支付宝', icon: '💳' },
  { key: 'wechat', label: '微信支付', icon: '💚' },
  { key: 'stripe', label: 'Stripe', icon: '🔵' },
  { key: 'paypal', label: 'PayPal', icon: '🅿️' },
];

onMounted(async () => {
  const orderId = route.params.id;
  if (!orderId) { loading.value = false; return; }
  try {
    const res = await shopApi.getOrder(orderId);
    order.value = res.data?.data || res.data;
  } catch {
    ElMessage.error('加载订单失败');
  } finally { loading.value = false; }
});

async function handlePay() {
  if (!order.value?.id || !selectedGateway.value) return;
  paying.value = true;
  try {
    const res = await shopApi.initiatePayment(order.value.id, selectedGateway.value);
    const data = res.data?.data || res.data;

    // 1. 支付宝：渲染自动提交表单
    if (data?.payment_form) {
      const container = document.createElement('div');
      container.style.display = 'none';
      container.innerHTML = data.payment_form;
      document.body.appendChild(container);
      // 表单已包含自动提交的 script
      return;
    }

    // 2. 微信 Native 支付：显示二维码
    if (selectedGateway.value === 'wechat' && data?.qr_code) {
      // 降级：跳转二维码图片（或显示二维码弹窗）
    }

    // 3. 标准跳转
    if (data?.payment_url) {
      window.location.href = data.payment_url;
    } else if (data?.redirect_url) {
      window.location.href = data.redirect_url;
    } else {
      ElMessage.success('支付请求已提交，请查看订单状态');
    }
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || '支付发起失败');
  } finally {
    paying.value = false;
  }
}
</script>

<style scoped>
.order-info p { margin: 4px 0; }
.pay-header { padding: 20px 0 0; }
.pay-icon { font-size: 48px; margin-bottom: 8px; }
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
.gateway-item:hover { border-color: #409eff; background: #f0f7ff; }
.gateway-item.active { border-color: #409eff; background: #ecf5ff; }
.gateway-radio {
  width: 16px; height: 16px; border-radius: 50%;
  border: 2px solid #c0c4cc; display: flex;
  align-items: center; justify-content: center; flex-shrink: 0;
}
.gateway-item.active .gateway-radio { border-color: #409eff; }
.radio-dot {
  width: 8px; height: 8px; border-radius: 50%; background: #409eff;
}
.gateway-icon { font-size: 22px; }
.gateway-label { font-size: 14px; }
</style>
