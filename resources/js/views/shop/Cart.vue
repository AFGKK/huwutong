<template>
  <div class="cart-page">
    <div class="page-header">
      <h1>购物车</h1>
      <el-button text @click="$router.push('/portal/shop')">← 继续购物</el-button>
    </div>

    <!-- 加载中 -->
    <el-skeleton v-if="loading" :rows="4" animated />

    <!-- 价格变动提示 -->
    <el-alert
      v-if="summary.price_changed"
      title="部分商品价格已变动，请确认"
      type="warning"
      show-icon
      :closable="false"
      class="mb-4"
    />

    <!-- 商品列表 -->
    <template v-if="items.length > 0">
      <el-card shadow="hover">
        <el-table :data="items" stripe>
          <el-table-column label="商品" min-width="200">
            <template #default="{ row }">
              <div class="product-info">
                <el-image v-if="row.image_url" :src="row.image_url" class="product-img" fit="cover" />
                <div>
                  <div class="product-name">{{ row.product_name || (row.sku?.product_name || '商品') }}</div>
                  <div class="product-spec">{{ cycleLabel(row.billing_cycle || row.sku?.billing_cycle) }}</div>
                  <div v-if="row.price_changed" class="price-warning">
                    <el-tag size="small" type="warning">¥{{ row.current_price }}</el-tag>
                    <span class="old-price">¥{{ row.unit_price }}</span>
                  </div>
                </div>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="单价" width="120" align="center">
            <template #default="{ row }">¥{{ (row.unit_price || row.sku?.price || 0).toFixed(2) }}</template>
          </el-table-column>
          <el-table-column label="数量" width="140" align="center">
            <template #default="{ row }">
              <el-input-number
                :model-value="row.quantity"
                :min="1"
                :max="99"
                size="small"
                :disabled="updating === row.sku_id"
                @change="(val) => updateQuantity(row, val)"
              />
            </template>
          </el-table-column>
          <el-table-column label="小计" width="120" align="center">
            <template #default="{ row }">¥{{ (row.subtotal || (row.unit_price || row.sku?.price || 0) * row.quantity).toFixed(2) }}</template>
          </el-table-column>
          <el-table-column label="操作" width="80" align="center">
            <template #default="{ row }">
              <el-button type="danger" size="small" text @click="removeItem(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-card>

      <!-- 优惠券 -->
      <el-card shadow="hover" class="mt-4">
        <div class="coupon-section">
          <span class="coupon-label">优惠券:</span>
          <div v-if="summary.coupon_code" class="coupon-applied">
            <el-tag closable type="success" @close="handleRemoveCoupon">
              {{ summary.coupon_code }}
            </el-tag>
            <span class="coupon-discount">-¥{{ (summary.coupon_discount || 0).toFixed(2) }}</span>
          </div>
          <div v-else class="coupon-input">
            <el-input v-model="couponCode" placeholder="请输入优惠码" size="small" class="coupon-input-field" />
            <el-button size="small" :loading="couponLoading" @click="handleApplyCoupon">应用</el-button>
          </div>
        </div>
      </el-card>

      <!-- 汇总 -->
      <el-card shadow="hover" class="mt-4">
        <div class="cart-summary">
          <div class="summary-row">
            <span>商品小计:</span>
            <span>¥{{ (summary.subtotal || totalAmount).toFixed(2) }}</span>
          </div>
          <div v-if="summary.coupon_discount > 0" class="summary-row discount">
            <span>优惠折扣:</span>
            <span>-¥{{ (summary.coupon_discount || 0).toFixed(2) }}</span>
          </div>
          <div class="summary-row total">
            <span>应付金额:</span>
            <span class="total-price">¥{{ (summary.final_amount || totalAmount).toFixed(2) }}</span>
          </div>
        </div>
        <div class="cart-actions">
          <el-button @click="handleClear">清空购物车</el-button>
          <el-button type="primary" size="large" :loading="checkoutLoading" @click="goCheckout">
            去结算
          </el-button>
        </div>
      </el-card>
    </template>

    <!-- 空购物车 -->
    <el-empty v-else description="购物车是空的" :image-size="80">
      <el-button type="primary" @click="$router.push('/portal/shop')">去逛逛</el-button>
    </el-empty>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useRouter } from 'vue-router';
import shopApi from '@/api/shop';

const router = useRouter();

const loading = ref(false);
const items = ref([]);
const summary = ref({});
const updating = ref(null);
const couponCode = ref('');
const couponLoading = ref(false);
const checkoutLoading = ref(false);

const totalAmount = computed(() => {
  return items.value.reduce((sum, item) => {
    return sum + ((item.unit_price || item.sku?.price || 0)) * (item.quantity || 1);
  }, 0).toFixed(2);
});

onMounted(loadCart);

async function loadCart() {
  loading.value = true;
  try {
    const [itemsRes, summaryRes] = await Promise.all([
      shopApi.getCart(),
      shopApi.getCartSummary(),
    ]);
    const data = itemsRes.data;
    items.value = data?.items || data?.data?.items || data?.data || [];
    summary.value = summaryRes.data?.data ?? summaryRes.data ?? {};
  } catch {
    items.value = [];
    summary.value = {};
  } finally {
    loading.value = false;
  }
}

async function updateQuantity(row, val) {
  updating.value = row.sku_id;
  try {
    await shopApi.updateCartItem({ sku_id: row.sku_id, quantity: val });
    await loadCart();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '更新失败');
    await loadCart();
  } finally {
    updating.value = null;
  }
}

async function removeItem(row) {
  try {
    await shopApi.removeFromCart(row.sku_id);
    ElMessage.success('已移除');
    await loadCart();
  } catch {
    ElMessage.error('移除失败');
  }
}

async function handleClear() {
  try {
    await ElMessageBox.confirm('确定清空购物车？');
    await shopApi.clearCart();
    ElMessage.success('已清空');
    await loadCart();
  } catch {}
}

async function handleApplyCoupon() {
  if (!couponCode.value.trim()) {
    ElMessage.warning('请输入优惠码');
    return;
  }
  couponLoading.value = true;
  try {
    const res = await shopApi.applyCoupon(couponCode.value.trim());
    ElMessage.success(res.data?.message || '优惠券已应用');
    couponCode.value = '';
    await loadCart();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '优惠券无效');
  } finally {
    couponLoading.value = false;
  }
}

async function handleRemoveCoupon() {
  try {
    await shopApi.removeCoupon();
    ElMessage.success('优惠券已移除');
    await loadCart();
  } catch {
    ElMessage.error('移除失败');
  }
}

async function goCheckout() {
  if (!items.value.length) {
    ElMessage.warning('购物车为空');
    return;
  }

  checkoutLoading.value = true;
  try {
    // 直接下单
    const res = await shopApi.checkout({});
    const order = res.data?.data || res.data;
    const orderId = order?.id;
    if (orderId) {
      ElMessage.success('订单已创建');
      window.location.href = `/portal/payment-result/${orderId}`;
    }
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || '下单失败');
  } finally {
    checkoutLoading.value = false;
  }
}

function cycleLabel(c) {
  return { monthly: '月付', yearly: '年付', lifetime: '终身', 'one-time': '一次性' }[c] || c || '';
}
</script>

<style scoped>
.cart-page { padding: 24px; max-width: 960px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h1 { margin: 0; font-size: 22px; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }

.product-info { display: flex; align-items: center; gap: 12px; }
.product-img { width: 48px; height: 48px; border-radius: 4px; flex-shrink: 0; }
.product-name { font-weight: 600; }
.product-spec { font-size: 12px; color: #909399; margin-top: 2px; }
.price-warning { margin-top: 4px; }
.old-price { text-decoration: line-through; color: #909399; font-size: 12px; margin-left: 4px; }

.coupon-section { display: flex; align-items: center; gap: 12px; }
.coupon-label { font-weight: 600; white-space: nowrap; }
.coupon-applied { display: flex; align-items: center; gap: 8px; }
.coupon-discount { color: #f56c6c; font-weight: 600; }
.coupon-input { display: flex; align-items: center; gap: 8px; flex: 1; }
.coupon-input-field { max-width: 240px; }

.cart-summary { margin-bottom: 16px; }
.summary-row { display: flex; justify-content: flex-end; gap: 16px; padding: 4px 0; font-size: 14px; }
.summary-row.discount { color: #f56c6c; }
.summary-row.total { font-size: 16px; font-weight: 600; border-top: 1px solid #ebeef5; padding-top: 8px; margin-top: 4px; }
.total-price { font-size: 24px; font-weight: 700; color: #F56C6C; }

.cart-actions { display: flex; justify-content: flex-end; gap: 12px; }

@media (max-width: 768px) {
    .cart-page { padding: 12px; }
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    .cart-page :deep(.el-table) {
        display: block;
        overflow-x: auto;
    }
    .coupon-section {
        flex-direction: column;
        align-items: stretch;
    }
    .coupon-input-field { max-width: none; width: 100%; }
    .summary-row { justify-content: space-between; }
    .cart-actions {
        flex-direction: column;
    }
    .cart-actions .el-button { width: 100%; margin-left: 0 !important; }
}
</style>
