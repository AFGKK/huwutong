<template>
  <div class="cart-page">
    <div class="page-header">
      <h1>{{ t('shop_cart.title') }}</h1>
      <el-button text @click="$router.push('/portal/shop')">← {{ t('shop_cart.continue') }}</el-button>
    </div>

    <el-skeleton v-if="loading" :rows="4" animated />

    <el-alert
      v-if="summary.price_changed"
      :title="t('shop_cart.price_changed')"
      type="warning"
      show-icon
      :closable="false"
      class="mb-4"
    />

    <template v-if="items.length > 0">
      <el-card v-if="!isMobile" shadow="hover">
        <div class="table-scroll-wrap">
        <el-table :data="items" stripe>
          <el-table-column :label="t('shop_cart.col_product')" min-width="200">
            <template #default="{ row }">
              <div class="product-info">
                <el-image v-if="row.image_url" :src="row.image_url" class="product-img" fit="cover" />
                <div>
                  <div class="product-name">{{ row.product_name || (row.sku?.product_name || t('shop_cart.product')) }}</div>
                  <div class="product-spec">{{ cycleLabel(row.billing_cycle || row.sku?.billing_cycle) }}</div>
                  <div v-if="row.price_changed" class="price-warning">
                    <el-tag size="small" type="warning">¥{{ row.current_price }}</el-tag>
                    <span class="old-price">¥{{ row.unit_price }}</span>
                  </div>
                </div>
              </div>
            </template>
          </el-table-column>
          <el-table-column :label="t('shop_cart.col_price')" width="120" align="center">
            <template #default="{ row }">¥{{ (row.unit_price || row.sku?.price || 0).toFixed(2) }}</template>
          </el-table-column>
          <el-table-column :label="t('shop_cart.col_qty')" width="140" align="center">
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
          <el-table-column :label="t('shop_cart.col_subtotal')" width="120" align="center">
            <template #default="{ row }">¥{{ (row.subtotal || (row.unit_price || row.sku?.price || 0) * row.quantity).toFixed(2) }}</template>
          </el-table-column>
          <el-table-column :label="t('shop_cart.col_actions')" width="80" align="center">
            <template #default="{ row }">
              <el-button type="danger" size="small" text @click="removeItem(row)">{{ t('shop_cart.remove') }}</el-button>
            </template>
          </el-table-column>
        </el-table>
        </div>
      </el-card>

      <div v-else class="cart-mobile-list">
        <el-card v-for="row in items" :key="row.sku_id || row.id" shadow="hover" class="cart-mobile-item">
          <div class="product-info">
            <el-image v-if="row.image_url" :src="row.image_url" class="product-img" fit="cover" />
            <div class="product-info-text">
              <div class="product-name">{{ row.product_name || (row.sku?.product_name || t('shop_cart.product')) }}</div>
              <div class="product-spec">{{ cycleLabel(row.billing_cycle || row.sku?.billing_cycle) }}</div>
              <div v-if="row.price_changed" class="price-warning">
                <el-tag size="small" type="warning">¥{{ row.current_price }}</el-tag>
                <span class="old-price">¥{{ row.unit_price }}</span>
              </div>
            </div>
          </div>
          <div class="cart-mobile-row">
            <span class="cart-mobile-label">{{ t('shop_cart.col_price') }}</span>
            <span>¥{{ (row.unit_price || row.sku?.price || 0).toFixed(2) }}</span>
          </div>
          <div class="cart-mobile-row">
            <span class="cart-mobile-label">{{ t('shop_cart.col_qty') }}</span>
            <el-input-number
              :model-value="row.quantity"
              :min="1"
              :max="99"
              size="small"
              :disabled="updating === row.sku_id"
              @change="(val) => updateQuantity(row, val)"
            />
          </div>
          <div class="cart-mobile-row">
            <span class="cart-mobile-label">{{ t('shop_cart.col_subtotal') }}</span>
            <strong>¥{{ (row.subtotal || (row.unit_price || row.sku?.price || 0) * row.quantity).toFixed(2) }}</strong>
          </div>
          <el-button type="danger" size="small" text class="cart-mobile-remove" @click="removeItem(row)">{{ t('shop_cart.remove') }}</el-button>
        </el-card>
      </div>

      <el-card shadow="hover" class="mt-4">
        <div class="coupon-section">
          <span class="coupon-label">{{ t('shop_cart.coupon') }}</span>
          <div v-if="summary.coupon_code" class="coupon-applied">
            <el-tag closable type="success" @close="handleRemoveCoupon">
              {{ summary.coupon_code }}
            </el-tag>
            <span class="coupon-discount">-¥{{ (summary.coupon_discount || 0).toFixed(2) }}</span>
          </div>
          <div v-else class="coupon-input">
            <el-input v-model="couponCode" :placeholder="t('shop_cart.coupon_ph')" size="small" class="coupon-input-field" />
            <el-button size="small" :loading="couponLoading" @click="handleApplyCoupon">{{ t('shop_cart.apply') }}</el-button>
          </div>
        </div>
      </el-card>

      <el-card shadow="hover" class="mt-4">
        <div class="cart-summary">
          <div class="summary-row">
            <span>{{ t('shop_cart.items_subtotal') }}</span>
            <span>¥{{ (summary.subtotal || totalAmount).toFixed(2) }}</span>
          </div>
          <div v-if="summary.coupon_discount > 0" class="summary-row discount">
            <span>{{ t('shop_cart.discount') }}</span>
            <span>-¥{{ (summary.coupon_discount || 0).toFixed(2) }}</span>
          </div>
          <div class="summary-row total">
            <span>{{ t('shop_cart.payable') }}</span>
            <span class="total-price">¥{{ (summary.final_amount || totalAmount).toFixed(2) }}</span>
          </div>
        </div>
        <div class="cart-actions">
          <el-button @click="handleClear">{{ t('shop_cart.clear') }}</el-button>
          <el-button type="primary" size="large" :loading="checkoutLoading" @click="goCheckout">
            {{ t('shop_cart.checkout') }}
          </el-button>
        </div>
      </el-card>
    </template>

    <el-empty v-else :description="t('shop_cart.empty')" :image-size="80">
      <el-button type="primary" @click="$router.push('/portal/shop')">{{ t('shop_cart.go_shop') }}</el-button>
    </el-empty>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useRouter } from 'vue-router';
import { useResponsive } from '@/composables/useResponsive';
import shopApi from '@/api/shop';

const { t } = useI18n();
const router = useRouter();
const { isMobile } = useResponsive();

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
    ElMessage.error(e.response?.data?.message || t('shop_cart.update_fail'));
    await loadCart();
  } finally {
    updating.value = null;
  }
}

async function removeItem(row) {
  try {
    await shopApi.removeFromCart(row.sku_id);
    ElMessage.success(t('shop_cart.removed'));
    await loadCart();
  } catch {
    ElMessage.error(t('shop_cart.remove_fail'));
  }
}

async function handleClear() {
  try {
    await ElMessageBox.confirm(t('shop_cart.clear_confirm'));
    await shopApi.clearCart();
    ElMessage.success(t('shop_cart.cleared'));
    await loadCart();
  } catch {}
}

async function handleApplyCoupon() {
  if (!couponCode.value.trim()) {
    ElMessage.warning(t('shop_cart.coupon_required'));
    return;
  }
  couponLoading.value = true;
  try {
    const res = await shopApi.applyCoupon(couponCode.value.trim());
    ElMessage.success(res.data?.message || t('shop_cart.coupon_applied'));
    couponCode.value = '';
    await loadCart();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('shop_cart.coupon_invalid'));
  } finally {
    couponLoading.value = false;
  }
}

async function handleRemoveCoupon() {
  try {
    await shopApi.removeCoupon();
    ElMessage.success(t('shop_cart.coupon_removed'));
    await loadCart();
  } catch {
    ElMessage.error(t('shop_cart.remove_fail'));
  }
}

async function goCheckout() {
  if (!items.value.length) {
    ElMessage.warning(t('shop_cart.cart_empty_warn'));
    return;
  }

  checkoutLoading.value = true;
  try {
    const res = await shopApi.checkout({});
    const order = res.data?.data || res.data;
    const orderId = order?.id;
    if (orderId) {
      ElMessage.success(t('shop_cart.order_ok'));
      window.location.href = `/portal/payment-result/${orderId}`;
    }
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || t('shop_cart.order_fail'));
  } finally {
    checkoutLoading.value = false;
  }
}

function cycleLabel(c) {
  return {
    monthly: t('shop_cart.cycle_monthly'),
    yearly: t('shop_cart.cycle_yearly'),
    lifetime: t('shop_cart.cycle_lifetime'),
    'one-time': t('shop_cart.cycle_onetime'),
  }[c] || c || '';
}
</script>

<style scoped>
.cart-page { padding: 24px; max-width: 960px; margin: 0 auto; min-width: 0; overflow-x: clip; }
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

.cart-mobile-list { display: flex; flex-direction: column; gap: 12px; }
.cart-mobile-item { width: 100%; }
.cart-mobile-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 10px; font-size: 14px; }
.cart-mobile-label { color: #909399; font-size: 13px; flex-shrink: 0; }
.cart-mobile-remove { margin-top: 8px; padding-left: 0; }
.product-info-text { min-width: 0; flex: 1; }

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
