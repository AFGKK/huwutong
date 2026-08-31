<template>
  <div class="checkout-page">
    <div v-if="loading" class="flex items-center justify-center min-h-[70vh]">
      <div class="text-center">
        <el-skeleton :rows="4" animated class="max-w-md w-full" />
        <p class="text-gray-400 text-sm mt-4">{{ t('actions.loading') }}</p>
      </div>
    </div>
    <div v-else-if="error" class="flex items-center justify-center min-h-[70vh]">
      <el-result icon="error" :title="t('messages.load_failed')" :sub-title="error">
        <template #extra>
          <el-button type="primary" round @click="$router.push('/products')">{{ t('checkout_page.back_to_shop') }}</el-button>
        </template>
      </el-result>
    </div>
    <div v-else>
      <div v-if="inMiniProgram" class="mp-pay-banner">
        <span>{{ t('checkout_page.mp_banner') }}</span>
        <button type="button" class="mp-pay-copy" @click="copyPayLink">{{ t('shop_checkout.copy_link') }}</button>
      </div>
      <div class="jd-topbar">
        <div class="jd-topbar-inner">
          <router-link to="/" class="jd-logo">
            <span class="jd-logo-icon">{{ t('auth.brand_mark') }}</span>
            <span class="jd-logo-text">{{ t('app_name') }}</span>
          </router-link>
          <div class="jd-topbar-nav">
            <span class="jd-nav-item">{{ t('nav.home') }}</span>
            <span class="jd-nav-sep">&gt;</span>
            <span class="jd-nav-item">{{ t('checkout_page.product_shop') }}</span>
            <span class="jd-nav-sep">&gt;</span>
            <span class="jd-nav-item jd-nav-cur">{{ t('checkout_page.confirm_order') }}</span>
          </div>
        </div>
      </div>
      <div class="jd-stepbar">
        <div class="jd-stepbar-inner">
          <div class="jd-step active"><span class="jd-step-num">1</span> {{ t('checkout_page.step_select') }}</div>
          <div class="jd-step active"><span class="jd-step-num">2</span> {{ t('checkout_page.step_confirm') }}</div>
          <div class="jd-step"><span class="jd-step-num">3</span> {{ t('checkout_page.step_pay') }}</div>
        </div>
      </div>
      <div class="main-wrap">
        <div class="main-layout">
          <div class="main-left">
            <div class="section-h2">{{ t('checkout_page.goods_info') }}</div>
            <div class="goods-table">
              <div class="goods-thead">
                <span class="goods-col goods-col-info">{{ t('checkout_page.col_product') }}</span>
                <span class="goods-col goods-col-price">{{ t('checkout_page.col_price') }}</span>
                <span class="goods-col goods-col-qty">{{ t('checkout_page.col_qty') }}</span>
                <span class="goods-col goods-col-total">{{ t('checkout_page.col_subtotal') }}</span>
              </div>
              <div class="goods-trow">
                <div class="goods-col goods-col-info">
                  <div class="goods-img">
                    <img v-if="product?.image_url && !imgLoadFailed" :src="product.image_url" alt="" class="goods-thumb" @error="onImgError" />
                    <el-icon v-else :size="22" color="#999"><Box /></el-icon>
                  </div>
                  <div class="goods-meta">
                    <div class="goods-name">{{ product?.name }}</div>
                    <div class="goods-sku">{{ selectedPlan?.name || t('checkout_page.default_plan') }}</div>
                  </div>
                </div>
                <div class="goods-col goods-col-price">¥{{ selectedPlan?.price || 0 }}{{ periodLabel }}</div>
                <div class="goods-col goods-col-qty">{{ quantity }}</div>
                <div class="goods-col goods-col-total">¥{{ (selectedPlan?.price || 0) * quantity }}</div>
              </div>
            </div>
            <div class="section-h2">{{ t('checkout_page.license_plan') }}</div>
            <div class="plan-tmall">
              <label v-for="p in plans" :key="p.id" class="plan-trow" :class="{ sel: selectedPlanId === p.id }">
                <input type="radio" name="plan" :value="p.id" v-model="selectedPlanId" @change="onPlanChange" class="plan-tradio">
                <span class="plan-tdot" :class="{ on: selectedPlanId === p.id }"></span>
                <span class="plan-tname">{{ p.name }}</span>
                <span class="plan-tsub">{{ p.description || t('checkout_page.default_license') }}</span>
                <span class="plan-tprice">¥{{ p.price }}{{ periodLabelFor(p) }}</span>
              </label>
            </div>
            <div class="section-h2">{{ t('checkout_page.qty_coupon') }}</div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">{{ t('checkout_page.purchase_qty') }}</label>
                <div class="qty-tmall">
                  <button class="qty-tbtn" @click="quantity > 1 && quantity--" :disabled="quantity <= 1">−</button>
                  <span class="qty-tnum">{{ quantity }}</span>
                  <button class="qty-tbtn" @click="quantity < 99 && quantity++" :disabled="quantity >= 99">+</button>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">{{ t('checkout_page.coupon') }}</label>
                <div class="coupon-tmall">
                  <input v-model="couponCode" :placeholder="t('checkout_page.coupon_ph')" class="coupon-tinp" @keyup.enter="applyCoupon">
                  <button class="coupon-tbtn" :class="{ on: couponCode }" :disabled="!couponCode" @click="applyCoupon">{{ t('checkout_page.verify') }}</button>
                </div>
              </div>
            </div>
            <div class="section-h2">{{ t('checkout_page.payment_method') }}</div>
            <div class="pay-tmall">
              <label class="pay-topt" :class="{ sel: paymentMethod === 'alipay' }">
                <input type="radio" name="pay" value="alipay" v-model="paymentMethod" class="pay-tradio">
                <span class="pay-tdot" :class="{ on: paymentMethod === 'alipay' }"></span>
                <svg class="pay-icon" viewBox="0 0 24 24" fill="none"><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2z" fill="#1677FF" opacity="0.12"/><path d="M7 11.5c0-.3.2-.5.5-.5h9c.3 0 .5.2.5.5v1c0 .3-.2.5-.5.5h-9c-.3 0-.5-.2-.5-.5v-1z" fill="#1677FF"/><path d="M17.5 9c.3 0 .5.2.5.5v5c0 .3-.2.5-.5.5h-11c-.3 0-.5-.2-.5-.5v-5c0-.3.2-.5.5-.5h11zm-10 1v4h9v-4h-9z" fill="#1677FF"/><path d="M8.5 8.5h7v1h-7z" fill="#1677FF"/></svg>
                <span class="pay-tname">{{ t('checkout_page.pay_alipay') }}</span>
              </label>
              <label class="pay-topt" :class="{ sel: paymentMethod === 'wxpay' }">
                <input type="radio" name="pay" value="wxpay" v-model="paymentMethod" class="pay-tradio">
                <span class="pay-tdot" :class="{ on: paymentMethod === 'wxpay' }"></span>
                <svg class="pay-icon" viewBox="0 0 24 24" fill="none"><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2z" fill="#07C160" opacity="0.12"/><path d="M7.5 10.5a1 1 0 100-2 1 1 0 000 2zm9 0a1 1 0 100-2 1 1 0 000 2z" fill="#07C160"/><path d="M12 16c-2.8 0-5-1.8-5-4s2.2-4 5-4 5 1.8 5 4c0 .8-.3 1.5-.7 2.1l.4 1.2-1.2-.4c-.5.2-1.1.4-1.7.5" stroke="#07C160" stroke-width="0.8" fill="none"/><circle cx="9" cy="12" r="0.5" fill="#07C160"/><circle cx="15" cy="12" r="0.5" fill="#07C160"/></svg>
                <span class="pay-tname">{{ t('checkout_page.pay_wechat') }}</span>
              </label>
              <label class="pay-topt" :class="{ sel: paymentMethod === 'yipay' }">
                <input type="radio" name="pay" value="yipay" v-model="paymentMethod" class="pay-tradio">
                <span class="pay-tdot" :class="{ on: paymentMethod === 'yipay' }"></span>
                <svg class="pay-icon" viewBox="0 0 24 24" fill="none"><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2z" fill="#E74C3C" opacity="0.12"/><path d="M12 6v12M7 10l5-4 5 4M7 14l5 4 5-4" stroke="#E74C3C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="pay-tname">{{ t('checkout_page.pay_yipay') }}</span>
              </label>
              <label class="pay-topt" :class="{ sel: paymentMethod === 'balance' }">
                <input type="radio" name="pay" value="balance" v-model="paymentMethod" class="pay-tradio">
                <span class="pay-tdot" :class="{ on: paymentMethod === 'balance' }"></span>
                <svg class="pay-icon" viewBox="0 0 24 24" fill="none"><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2z" fill="#FA8C16" opacity="0.12"/><path d="M7 8.5h10M7 12h7M7 15.5h4" stroke="#FA8C16" stroke-width="1.2" stroke-linecap="round"/><circle cx="17" cy="15.5" r="2.5" fill="#FA8C16" opacity="0.15"/><circle cx="17" cy="15.5" r="1" fill="#FA8C16"/></svg>
                <span class="pay-tname">{{ t('checkout_page.pay_balance') }}</span>
              </label>
            </div>
            <!-- ═══════ 自动续费 ═══════ -->
            <div v-if="isSubscription" class="section-h2">{{ t('checkout_page.renew_settings') }}</div>
            <div v-if="isSubscription" class="renew-box">
              <label class="renew-toggle">
                <input type="checkbox" v-model="autoRenew" class="renew-checkbox">
                <span class="renew-slider"></span>
                <span class="renew-label">
                  <strong>{{ t('checkout_page.auto_renew') }}</strong>
                  <span class="renew-desc">{{ t('checkout_page.auto_renew_desc') }}</span>
                </span>
              </label>
            </div>
            <!-- ═══════ 联系信息 ═══════ -->
            <div class="section-h2">{{ t('checkout_page.contact_info') }}</div>
            <div class="contact-box">
              <div class="contact-row">
                <div class="contact-field flex-1">
                  <label class="contact-label">{{ t('checkout_page.email_label') }} <span class="text-red-400">*</span></label>
                  <input v-model="contact.email" :placeholder="t('checkout_page.email_ph')" class="contact-input" type="email" />
                  <p class="contact-hint">{{ t('checkout_page.email_hint') }}</p>
                </div>
                <div class="contact-field flex-1">
                  <label class="contact-label">{{ t('checkout_page.phone_label') }}</label>
                  <input v-model="contact.phone" :placeholder="t('checkout_page.phone_ph')" class="contact-input" type="tel" maxlength="11" />
                  <p class="contact-hint">{{ t('checkout_page.phone_hint') }}</p>
                </div>
              </div>
              <div class="contact-field">
                <label class="contact-label">{{ t('checkout_page.recipient_name') }}</label>
                <input v-model="contact.name" :placeholder="t('checkout_page.optional')" class="contact-input" />
              </div>
            </div>
            <!-- ═══════ 订单备注 ═══════ -->
            <div class="section-h2">{{ t('checkout_page.order_notes') }} <span class="section-sub">{{ t('checkout_page.optional_paren') }}</span></div>
            <div class="notes-box">
              <textarea v-model="orderNotes" :placeholder="t('checkout_page.notes_ph')" class="notes-textarea" rows="3" maxlength="500"></textarea>
              <div class="notes-counter">{{ orderNotes.length }}/500</div>
            </div>
            <!-- ═══════ 发票信息 ═══════ -->
            <div class="section-h2">{{ t('checkout_page.invoice_info') }} <span class="section-sub">{{ t('checkout_page.optional_paren') }}</span></div>
            <div class="invoice-box">
              <div class="invoice-type-row">
                <label class="invoice-radio" :class="{ on: invoiceType === 'personal' }">
                  <input type="radio" value="personal" v-model="invoiceType" class="invoice-radio-input">
                  <span class="invoice-radio-dot" :class="{ on: invoiceType === 'personal' }"></span>
                  <span>{{ t('checkout_page.invoice_personal') }}</span>
                </label>
                <label class="invoice-radio" :class="{ on: invoiceType === 'company' }">
                  <input type="radio" value="company" v-model="invoiceType" class="invoice-radio-input">
                  <span class="invoice-radio-dot" :class="{ on: invoiceType === 'company' }"></span>
                  <span>{{ t('checkout_page.invoice_company') }}</span>
                </label>
              </div>
              <div class="invoice-fields">
                <div class="invoice-field">
                  <label class="invoice-label">{{ t('checkout_page.invoice_title') }}</label>
                  <input v-model="invoice.title" :placeholder="t('checkout_page.invoice_title_ph')" class="invoice-input" />
                </div>
                <template v-if="invoiceType === 'company'">
                  <div class="invoice-field">
                    <label class="invoice-label">{{ t('checkout_page.tax_no') }}</label>
                    <input v-model="invoice.tax_no" :placeholder="t('checkout_page.tax_no_ph')" class="invoice-input" maxlength="18" />
                  </div>
                  <div class="invoice-field">
                    <label class="invoice-label">{{ t('checkout_page.company_address') }}</label>
                    <input v-model="invoice.address" :placeholder="t('checkout_page.reg_address_ph')" class="invoice-input" />
                  </div>
                  <div class="invoice-field">
                    <label class="invoice-label">{{ t('checkout_page.company_phone') }}</label>
                    <input v-model="invoice.phone" :placeholder="t('checkout_page.reg_phone_ph')" class="invoice-input" />
                  </div>
                  <div class="invoice-row">
                    <div class="invoice-field flex-1">
                      <label class="invoice-label">{{ t('checkout_page.bank') }}</label>
                      <input v-model="invoice.bank" :placeholder="t('checkout_page.bank_ph')" class="invoice-input" />
                    </div>
                    <div class="invoice-field flex-1">
                      <label class="invoice-label">{{ t('checkout_page.bank_account') }}</label>
                      <input v-model="invoice.bank_account" :placeholder="t('checkout_page.bank_account')" class="invoice-input" />
                    </div>
                  </div>
                </template>
              </div>
            </div>
          </div>
          <div class="main-right">
            <div class="side-card">
              <div class="side-title">
                <span>{{ t('checkout_page.order_summary') }}</span>
                <!-- ═══ 支付倒计时 ═══ -->
                <span v-if="expireSeconds > 0" class="countdown-badge" :class="{ urgent: expireSeconds <= 300 }">
                  <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1"/><path d="M6 3v3l2 1" stroke="currentColor" stroke-width="1" stroke-linecap="round"/></svg>
                  {{ Math.floor(expireSeconds / 60) }}:{{ String(expireSeconds % 60).padStart(2, '0') }}
                </span>
              </div>
              <div class="side-body">
                <div class="side-row">
                  <span>{{ product?.name }} × {{ quantity }}</span>
                </div>
                <!-- ═══════ 价格明细（可展开）═══════ -->
                <div class="price-detail" :class="{ open: showPriceDetail }">
                  <div class="price-detail-header" @click="showPriceDetail = !showPriceDetail">
                    <span>{{ t('checkout_page.price_detail') }}</span>
                    <svg class="price-detail-arrow" :class="{ up: showPriceDetail }" width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M3 5l3 3 3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                  </div>
                  <div class="price-detail-body">
                    <div class="price-row">
                      <span>{{ t('checkout_page.subtotal') }}</span>
                      <span>¥{{ subtotal }}</span>
                    </div>
                    <div v-if="discount > 0" class="price-row price-row-green">
                      <span>{{ t('checkout_page.discount_amount') }}</span>
                      <span>−¥{{ discount.toFixed(2) }}</span>
                    </div>
                    <div v-if="isSubscription && autoRenew" class="price-row price-row-blue">
                      <span>{{ t('checkout_page.auto_renew_discount') }}</span>
                      <span>—</span>
                    </div>
                    <div class="price-row price-row-total">
                      <span>{{ t('checkout_page.total_due') }}</span>
                      <span>¥{{ total }}</span>
                    </div>
                  </div>
                </div>
                <div v-if="discount > 0" class="side-row side-green">
                  <span>{{ t('checkout_page.discount') }}</span>
                  <span>−¥{{ discount.toFixed(2) }}</span>
                </div>
              </div>
              <div class="side-total">
                <span class="side-total-label">{{ t('checkout_page.total_due_label') }}</span>
                <span class="side-total-price">¥{{ total }}</span>
              </div>
              <div class="side-btn-wrap">
                <button class="order-btn" :disabled="submitting" @click="submitOrder">
                  <svg v-if="submitting" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                  <span>{{ submitting ? t('checkout_page.submitting') : t('checkout_page.submit_order') }}</span>
                </button>
              </div>
              <div class="side-agree">
                {{ t('checkout_page.agree_prefix') }} <a href="/terms" target="_blank">{{ t('footer.terms_of_service') }}</a>
              </div>
            </div>
            <!-- ═══════ 推荐搭配 ═══════ -->
            <div v-if="recommendProducts.length > 0" class="side-card" style="margin-top:12px">
              <div class="side-title">{{ t('checkout_page.recommend') }}</div>
              <div class="recommend-body">
                <div v-for="(item, i) in recommendProducts" :key="i" class="recommend-item">
                  <div class="recommend-info">
                    <span class="recommend-name">{{ item.name }}</span>
                    <span class="recommend-price">¥{{ item.price }}</span>
                  </div>
                  <button class="recommend-add" :class="{ added: item.added }" @click="toggleRecommend(item)">
                    {{ item.added ? t('checkout_page.added') : t('checkout_page.add') }}
                  </button>
                </div>
              </div>
            </div>
            <!-- ═══════ 信任标识 ═══════ -->
            <div class="trust-badges">
              <div class="trust-badge-item">
                <svg class="trust-badge-icon" viewBox="0 0 24 24" fill="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="#52c41a"/></svg>
                <span>{{ t('checkout_page.trust_ssl') }}</span>
              </div>
              <div class="trust-badge-item">
                <svg class="trust-badge-icon" viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" fill="#52c41a"/></svg>
                <span>{{ t('checkout_page.trust_refund') }}</span>
              </div>
              <div class="trust-badge-item">
                <svg class="trust-badge-icon" viewBox="0 0 24 24" fill="none"><rect x="3" y="11" width="18" height="11" rx="2" ry="2" fill="#52c41a"/><path d="M7 11V7a5 5 0 0110 0v4" stroke="#52c41a" stroke-width="2" fill="none"/></svg>
                <span>{{ t('checkout_page.trust_secure') }}</span>
              </div>
              <div class="trust-badge-item">
                <svg class="trust-badge-icon" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" fill="#52c41a"/><path d="M12 6v6l4 2" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/></svg>
                <span>{{ t('checkout_page.trust_timeout') }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- ═══════ 页脚（与官网 Blade 结构对齐）═══════ -->
      <div class="checkout-footer">
        <div class="footer-bg"></div>
        <div class="footer-inner">
          <div class="footer-grid">
            <div class="footer-brand">
              <a href="/" class="footer-logo">
                <div class="footer-logo-icon">{{ $t('auth.brand_mark') }}</div>
                <span class="footer-logo-text">{{ siteSettings.site_name || $t('app_name') }}</span>
              </a>
              <p class="footer-desc">{{ siteSettings.site_description || $t('app_description') }}</p>
              <a href="/license/query" class="footer-cta">{{ $t('footer.query_cta') }}</a>
              <div class="footer-social">
                <a v-if="isHttpUrl(siteSettings.social_wechat)" :href="siteSettings.social_wechat" target="_blank" rel="noopener noreferrer" class="social-icon hover-green" :title="t('checkout_page.wechat')">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098 10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178A1.17 1.17 0 0 1 4.623 7.17c0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178 1.17 1.17 0 0 1-1.162-1.178c0-.651.52-1.18 1.162-1.18zm5.34 2.867c-1.797-.052-3.746.512-5.28 1.786-1.72 1.428-2.687 3.72-1.78 6.22.942 2.453 3.666 4.229 6.884 4.229.826 0 1.622-.12 2.361-.336a.722.722 0 0 1 .598.082l1.584.926a.272.272 0 0 0 .14.045c.134 0 .24-.11.24-.245 0-.06-.024-.12-.04-.178l-.325-1.233a.492.492 0 0 1 .178-.553C23.028 18.333 24 16.592 24 14.628c0-3.299-3.063-5.77-7.062-5.77zm-2.18 2.364c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982zm4.36 0c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982z"/></svg>
                </a>
                <a v-if="siteSettings.social_github" :href="siteSettings.social_github" target="_blank" rel="noopener noreferrer" class="social-icon hover-gray" title="GitHub">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0 1 12 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg>
                </a>
                <a v-if="siteSettings.contact_email" :href="'mailto:' + siteSettings.contact_email" class="social-icon hover-blue" :title="t('checkout_page.email_title')">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </a>
                <a v-else href="mailto:support@huwutong.com" class="social-icon hover-blue" :title="t('checkout_page.email_title')">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </a>
              </div>
            </div>
            <div class="footer-col">
              <h4 class="footer-col-title">{{ $t('nav.products') }}</h4>
              <a href="/#features" class="footer-link">{{ $t('nav.products') }}</a>
              <a href="/products" class="footer-link">{{ $t('nav.products') }}</a>
              <a href="/pricing" class="footer-link">{{ $t('nav.pricing') }}</a>
              <a href="/compare" class="footer-link">{{ $t('nav.compare') }}</a>
            </div>
            <div class="footer-col">
              <h4 class="footer-col-title">{{ $t('footer.resources') }}</h4>
              <a href="/help" class="footer-link">{{ $t('nav.help') }}</a>
              <a href="/sdk" class="footer-link">{{ $t('nav.sdk') }}</a>
              <a href="/blog" class="footer-link">{{ $t('nav.blog') }}</a>
              <a href="/license/query" class="footer-link">{{ $t('nav.license_query') }}</a>
              <a href="/build/open-platform" class="footer-link">{{ $t('footer.open_platform') }}</a>
            </div>
            <div class="footer-col">
              <h4 class="footer-col-title">{{ $t('footer.company') }}</h4>
              <a href="/about" class="footer-link">{{ $t('nav.about') }}</a>
              <a href="/contact" class="footer-link">{{ $t('nav.contact') }}</a>
              <a href="/build/status" class="footer-link">{{ $t('footer.status_page') }}</a>
            </div>
          </div>
          <div class="footer-divider"></div>
            <div class="footer-bottom">
            <p class="footer-copy">{{ siteSettings.footer_copyright || $t('footer.copyright', { year: new Date().getFullYear() }) }}</p>
            <div class="footer-legal">
              <a href="/privacy" class="legal-link">{{ $t('footer.privacy_policy') }}</a>
              <a href="/terms" class="legal-link">{{ $t('footer.terms_of_service') }}</a>
              <a href="/security-policy" class="legal-link">{{ $t('footer.security_policy') }}</a>
              <a href="/cookie-policy" class="legal-link">{{ $t('footer.cookie_policy') }}</a>
              <a v-if="siteSettings.icp_beian" :href="siteSettings.icp_beian_url || 'https://beian.miit.gov.cn/'" target="_blank" rel="noopener noreferrer" class="legal-link">{{ siteSettings.icp_beian }}</a>
              <a v-if="siteSettings.gongan_beian || siteSettings.police_beian" :href="siteSettings.gongan_beian_url || siteSettings.police_beian_url || '#'" target="_blank" rel="noopener noreferrer" class="legal-link">{{ siteSettings.gongan_beian || siteSettings.police_beian }}</a>
            </div>
          </div>
        </div>
      </div>
      <el-dialog v-model="showPayDialog" top="8vh" width="400px" class="pay-dialog" :close-on-click-modal="false" destroy-on-close>
        <template #header>
          <span class="text-base font-bold text-gray-800">{{ t('checkout_page.confirm_pay') }}</span>
        </template>
        <div class="text-center py-4">
          <div class="text-sm text-gray-400 mb-1">{{ t('checkout_page.pay_amount') }}</div>
          <div class="text-3xl font-bold" style="color:#0f172a">¥{{ total }}</div>
          <div class="text-xs text-gray-300 mt-3">{{ t('checkout_page.pay_method_label') }}{{ paymentMethods.find(p => p.value === paymentMethod)?.label }}</div>
        </div>
        <template #footer>
          <div class="flex gap-2">
            <button class="dlg-btn2 dlg-cancel2" @click="showPayDialog = false">{{ t('actions.cancel') }}</button>
            <button class="dlg-btn2 dlg-confirm2" :disabled="paying" @click="handlePay">{{ paying ? t('checkout_page.paying') : t('checkout_page.confirm_pay') }}</button>
          </div>
        </template>
      </el-dialog>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Box, Wallet, Coin, Document } from '@element-plus/icons-vue';
import orderApi from '@/api/order';
import { isMiniProgram, shouldEscapePayment, copyCurrentUrl } from '@/utils/miniprogramEnv';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const loading = ref(true);
const error = ref('');
const submitting = ref(false);
const paying = ref(false);
const inMiniProgram = ref(false);
const product = ref(null);
const plans = ref([]);
const selectedPlanId = ref(null);
const quantity = ref(1);
const couponCode = ref('');
const discount = ref(0);
const showPayDialog = ref(false);
const paymentMethod = ref('alipay');
const createdOrder = ref(null);
const invoiceType = ref('personal');
const invoice = ref({ title: '', tax_no: '', address: '', phone: '', bank: '', bank_account: '' });
const autoRenew = ref(false);
const contact = ref({ email: '', phone: '', name: '' });
const orderNotes = ref('');
const showPriceDetail = ref(false);
const expireSeconds = ref(1800); // 30分钟倒计时
const recommendProducts = ref([]);
const imgLoadFailed = ref(false);
let expireTimer = null;

function onImgError() {
  imgLoadFailed.value = true;
}

function isHttpUrl(v) {
  const s = String(v || '');
  return s.startsWith('http://') || s.startsWith('https://');
}

function toggleRecommend(item) {
  item.added = !item.added;
  if (item.added) {
    ElMessage.success(t('checkout_page.messages.added_item', { name: item.name }));
  }
}

onMounted(() => {
  expireTimer = setInterval(() => {
    if (expireSeconds.value > 0) {
      expireSeconds.value--;
    } else {
      clearInterval(expireTimer);
      ElMessage.warning(t('checkout_page.messages.order_expired'));
    }
  }, 1000);
});

onUnmounted(() => {
  if (expireTimer) clearInterval(expireTimer);
});

const isSubscription = computed(() => {
  const plan = selectedPlan.value;
  return plan && (plan.billing_cycle === 'monthly' || plan.billing_cycle === 'yearly' || plan.billing_cycle === 'quarterly');
});
const siteSettings = ref({});
const billingCycleLabels = computed(() => ({
  monthly: t('checkout_page.cycle_monthly'),
  quarterly: t('checkout_page.cycle_quarterly'),
  yearly: t('checkout_page.cycle_yearly'),
  'one-time': t('checkout_page.cycle_onetime'),
}));
const paymentMethods = computed(() => [
  { value: 'alipay', label: t('checkout_page.pay_alipay'), desc: t('checkout_page.pay_alipay') },
  { value: 'wxpay', label: t('checkout_page.pay_wechat'), desc: t('checkout_page.pay_wechat') },
  { value: 'yipay', label: t('checkout_page.pay_yipay'), desc: t('checkout_page.pay_yipay') },
  { value: 'balance', label: t('checkout_page.pay_balance'), desc: t('checkout_page.pay_balance') },
]);

const selectedPlan = computed(() => plans.value.find(p => p.id === selectedPlanId.value));
const subtotal = computed(() => { const p = selectedPlan.value?.price || 0; return (p * quantity.value).toFixed(2); });
const total = computed(() => (parseFloat(subtotal.value) - discount.value).toFixed(2));
function periodLabelFor(plan) {
    const cycle = plan?.billing_cycle || 'monthly';
    return billingCycleLabels.value[cycle] || billingCycleLabels.value.monthly;
}
const periodLabel = computed(() => {
    const cycle = selectedPlan.value?.billing_cycle || 'monthly';
    return billingCycleLabels.value[cycle] || billingCycleLabels.value.monthly;
});

onMounted(async () => {
  inMiniProgram.value = isMiniProgram();
  fetch('/api/settings/public').then(r => r.json()).then(d => { if (d.data) siteSettings.value = d.data; }).catch(() => {});
  const planId = route.query.plan_id;
  const skuId = route.query.sku_id;
  const productSlug = route.query.product;
  try {
    const prodResp = await fetch('/api/public/products?per_page=50').then(r => r.json());
    const allProducts = prodResp.data || [];
    if (skuId) {
      try {
        const token = localStorage.getItem('auth_token');
        const r = await fetch('/api/admin/product-skus/' + skuId, { headers: { Authorization: 'Bearer ' + token, Accept: 'application/json' } });
        const d = await r.json();
        const sku = d.data || d;
        if (sku && sku.id) {
          product.value = allProducts.find(p => p.id == sku.product_id) || { name: t('checkout_page.product_fallback'), id: sku.product_id };
          plans.value = [{ id: sku.id, product_id: sku.product_id, name: sku.name, description: '', price: parseFloat(sku.price) || 0, billing_cycle: sku.billing_cycle || 'monthly', sku_id: sku.id }];
          selectedPlanId.value = sku.id;
          // 生成推荐搭配
          const currentProduct = allProducts.find(p => p.id == sku.product_id);
          if (currentProduct) {
            const others = allProducts.filter(p => p.id != sku.product_id && p.is_active !== false);
            recommendProducts.value = others.slice(0, 3).map(p => ({
              id: p.id, name: p.name, price: parseFloat(p.lowest_price || p.base_price || 0) || t('checkout_page.price_tbd'), added: false,
              sku_id: p.skus?.[0]?.id || null
            }));
          }
        }
      } catch(e) {}
    }
    if ((!plans.value || plans.value.length === 0) && planId) {
      try {
        const r = await fetch('/api/public/pricing-plans').then(r => r.json());
        const allPlans = r.data || [];
        const plan = allPlans.find(p => p.id == planId);
        if (plan) { selectedPlanId.value = plan.id; product.value = allProducts.find(p => p.id == plan.product_id) || { name: plan.name, id: plan.product_id }; plans.value = allPlans.filter(p => p.product_id == plan.product_id); }
      } catch(e) {}
    }
    if ((!plans.value || plans.value.length === 0) && productSlug) {
      product.value = allProducts.find(p => p.slug === productSlug);
      if (product.value) {
        try { const r = await fetch('/api/public/pricing-plans').then(r => r.json()); const allPlans = r.data || []; plans.value = allPlans.filter(p => p.product_id == product.value.id); if (plans.value.length > 0) selectedPlanId.value = plans.value[0].id; } catch(e) {}
      }
    }
    if (!product.value && allProducts.length > 0) product.value = allProducts[0];
    if ((!plans.value || plans.value.length === 0) && product.value) { plans.value = [{ id: 0, product_id: product.value.id, name: t('checkout_page.default_license'), description: '', price: 0, billing_cycle: 'monthly' }]; selectedPlanId.value = 0; }
    if (!product.value) error.value = t('checkout_page.messages.load_product_failed');
  } catch (e) { error.value = t('checkout_page.messages.load_product_failed'); }
  finally { loading.value = false; }
});

function onPlanChange() { discount.value = 0; couponCode.value = ''; }
async function applyCoupon() {
  if (!couponCode.value) return;
  if (!selectedPlan.value || !product.value) { ElMessage.warning(t('checkout_page.messages.select_plan_first')); return; }
  const amount = (selectedPlan.value.price || 0) * quantity.value;
  try {
    const token = localStorage.getItem('auth_token');
    const r = await fetch('/api/billing/coupons/validate?code=' + encodeURIComponent(couponCode.value) + '&amount=' + amount + '&plan=' + encodeURIComponent(selectedPlan.value.name || '') + '&product_id=' + (product.value.id || ''), {
      headers: { 'Accept': 'application/json', 'Authorization': token ? 'Bearer ' + token : '' }
    });
    const d = await r.json();
    if (d.success && d.data?.valid) {
      discount.value = d.data.discount || 0;
      ElMessage.success(t('checkout_page.messages.coupon_applied', { amount: (d.data.discount || 0).toFixed(2) }));
    } else {
      discount.value = 0;
      ElMessage.warning(d.data?.message || d.data?.error || t('checkout_page.messages.coupon_invalid'));
    }
  } catch (e) { ElMessage.error(t('checkout_page.messages.coupon_validate_failed')); discount.value = 0; }
}
async function submitOrder() {
  if (!selectedPlan.value) { ElMessage.warning(t('checkout_page.messages.select_plan')); return; }
  if (!contact.value.email) { ElMessage.warning(t('checkout_page.messages.email_required')); return; }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contact.value.email)) { ElMessage.warning(t('checkout_page.messages.email_invalid')); return; }
  submitting.value = true;
  try {
    const orderPayload = { 
      items: [{ sku_id: selectedPlan.value.sku_id || selectedPlan.value.id, quantity: quantity.value, item_type: 'license', unit_price: selectedPlan.value.price }], 
      currency: 'CNY' 
    };
    // 自动续费
    if (isSubscription.value) {
      orderPayload.auto_renew = autoRenew.value;
    }
    // 联系信息
    if (contact.value.email) {
      orderPayload.contact = { email: contact.value.email, phone: contact.value.phone || '', name: contact.value.name || '' };
    }
    // 订单备注
    if (orderNotes.value) {
      orderPayload.notes = orderNotes.value;
    }
    // 发票信息
    if (invoice.value.title) {
      orderPayload.invoice = {
        type: invoiceType.value,
        title: invoice.value.title,
        tax_no: invoice.value.tax_no || '',
        address: invoice.value.address || '',
        phone: invoice.value.phone || '',
        bank: invoice.value.bank || '',
        bank_account: invoice.value.bank_account || '',
      };
    }
    const res = await orderApi.create(orderPayload);
    createdOrder.value = res.data?.data || res.data;
    ElMessage.success(t('checkout_page.messages.order_created'));
    showPayDialog.value = true;
  } catch (e) { ElMessage.error(e?.response?.data?.error?.message || e?.response?.data?.message || t('checkout_page.messages.order_failed')); }
  finally { submitting.value = false; }
}
async function handlePay() {
  if (!createdOrder.value) return;

  // 小程序 web-view 内微信/支付宝支付易失败 → 引导复制到浏览器
  if (shouldEscapePayment(paymentMethod.value)) {
    try {
      await copyCurrentUrl();
      ElMessage.success(t('checkout_page.messages.link_copied_browser'));
    } catch {
      ElMessage.warning(t('checkout_page.messages.copy_manual'));
    }
    return;
  }

  paying.value = true;
  try {
    await orderApi.markPaid(createdOrder.value.id, { payment_method: paymentMethod.value, transaction_id: 'TXN' + Date.now() });
    ElMessage.success(t('checkout_page.messages.pay_success'));
    showPayDialog.value = false;
    setTimeout(() => router.push('/billing'), 1500);
  } catch (e) { ElMessage.error(e?.response?.data?.error?.message || t('checkout_page.messages.pay_failed')); }
  finally { paying.value = false; }
}

async function copyPayLink() {
  try {
    await copyCurrentUrl();
    ElMessage.success(t('checkout_page.messages.link_copied'));
  } catch {
    ElMessage.error(t('checkout_page.messages.copy_failed'));
  }
}
</script>

<style scoped>
.mp-pay-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 14px;
  background: #fff7e6;
  border-bottom: 1px solid #ffd591;
  color: #ad6800;
  font-size: 13px;
  line-height: 1.4;
}
.mp-pay-copy {
  flex-shrink: 0;
  border: none;
  background: #fa8c16;
  color: #fff;
  border-radius: 16px;
  padding: 6px 12px;
  font-size: 12px;
  cursor: pointer;
}

/* ═══════════════════════════════════════
   确认订单页面 - 现代化设计
   ═══════════════════════════════════════ */

/* ── 变量 ── */
.checkout-page {
  --primary: #0f172a;
  --primary-light: #f1f5f9;
  --primary-dark: #1e293b;
  --success: #52c41a;
  --danger: #f56c6c;
  --warning: #fa8c16;
  --text-primary: #1a1a2e;
  --text-secondary: #5a5a7a;
  --text-muted: #999;
  --border: #e8e8e8;
  --bg-body: #f5f6fa;
  --bg-card: #fff;
  --radius: 8px;
  --radius-sm: 6px;
  --shadow-sm: 0 1px 2px rgba(0,0,0,0.04), 0 1px 4px rgba(0,0,0,0.06);
  --shadow-md: 0 2px 8px rgba(0,0,0,0.06), 0 1px 4px rgba(0,0,0,0.04);
  --shadow-lg: 0 4px 16px rgba(0,0,0,0.08), 0 2px 8px rgba(0,0,0,0.04);
  --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  min-height: 100vh;
  background: var(--bg-body);
}

/* ── 顶部栏 ── */
.jd-topbar { background: var(--bg-card); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
.jd-topbar-inner { max-width: 990px; margin: 0 auto; display: flex; align-items: center; height: 50px; padding: 0 15px; }
.jd-logo { display: flex; align-items: center; gap: 6px; text-decoration: none; }
.jd-logo-icon { width: 26px; height: 26px; background: linear-gradient(135deg, #0f172a, #1e293b); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; font-weight: 700; box-shadow: 0 2px 6px rgba(15,23,42,0.3); }
.jd-logo-text { font-size: 16px; font-weight: 700; color: var(--text-primary); }
.jd-topbar-nav { margin-left: 24px; display: flex; align-items: center; gap: 8px; font-size: 12px; }
.jd-nav-item { color: var(--text-muted); }
.jd-nav-sep { color: #ddd; }
.jd-nav-cur { color: var(--primary); font-weight: 600; }

/* ── 步骤条 ── */
.jd-stepbar { background: var(--bg-card); border-bottom: 1px solid var(--border); }
.jd-stepbar-inner { max-width: 990px; margin: 0 auto; display: flex; align-items: center; justify-content: center; padding: 0 15px; height: 48px; gap: 24px; }
.jd-step { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #ccc; position: relative; }
.jd-step + .jd-step::before { content: '›'; position: absolute; left: -16px; top: 50%; transform: translateY(-50%); font-size: 16px; color: #ddd; font-weight: 700; }
.jd-step.active { color: var(--primary); font-weight: 600; }
.jd-step.done { color: var(--success); }
.jd-step-num { width: 20px; height: 20px; border-radius: 50%; background: #e0e0e0; color: #fff; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; transition: var(--transition); }
.jd-step.active .jd-step-num { background: linear-gradient(135deg, #0f172a, #1e293b); box-shadow: 0 2px 6px rgba(15,23,42,0.35); }
.jd-step.done .jd-step-num { background: var(--success); }

/* ── 主布局 ── */
.main-wrap { max-width: 990px; margin: 0 auto; padding: 20px 15px; }
.main-layout { display: flex; gap: 20px; align-items: flex-start; }
.main-left { flex: 1; min-width: 0; }
.main-right { width: 290px; flex-shrink: 0; }

/* ═══════════════════════════════════════
   卡片通用样式
   ═══════════════════════════════════════ */
.goods-table,
.plan-tmall,
.form-row,
.pay-tmall,
.renew-box,
.contact-box,
.notes-box,
.invoice-box,
.side-card {
  background: var(--bg-card);
  border-radius: var(--radius);
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border);
  margin-bottom: 16px;
  transition: var(--transition);
}

/* ── 商品信息 ── */
.goods-thead { display: flex; align-items: center; height: 38px; background: #f8f9fc; border-bottom: 1px solid var(--border); border-radius: var(--radius) var(--radius) 0 0; padding: 0 16px; font-size: 12px; color: var(--text-muted); font-weight: 500; }
.goods-trow { display: flex; align-items: center; padding: 16px; font-size: 13px; color: var(--text-primary); }
.goods-col-info { flex: 1; display: flex; align-items: center; gap: 12px; }
.goods-col-price { width: 100px; text-align: center; }
.goods-col-qty { width: 50px; text-align: center; }
.goods-col-total { width: 90px; text-align: right; color: var(--primary); font-weight: 700; font-size: 14px; }
.goods-img { width: 52px; height: 52px; background: #f8f9fc; border: 1px solid #eee; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; }
.goods-thumb { width: 100%; height: 100%; object-fit: cover; }
.goods-name { font-size: 14px; font-weight: 600; color: var(--text-primary); }
.goods-name:hover { color: var(--primary); }
.goods-sku { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

/* ── 章节标题 ── */
.section-h2 { font-size: 14px; font-weight: 700; color: var(--text-primary); padding-left: 12px; margin-bottom: 10px; line-height: 1; display: flex; align-items: center; gap: 8px; }
.section-h2::before { content: ''; width: 3px; height: 14px; background: linear-gradient(180deg, var(--primary), #66b1ff); border-radius: 2px; flex-shrink: 0; }

/* ── 授权方案 ── */
.plan-tmall { padding: 6px; }
.plan-trow { display: flex; align-items: center; gap: 10px; padding: 12px 14px; cursor: pointer; border: 1.5px solid transparent; margin: 3px 0; border-radius: var(--radius-sm); transition: var(--transition); }
.plan-trow:hover { background: #f8f9fc; border-color: #e8e8e8; }
.plan-trow.sel { background: var(--primary-light); border-color: var(--primary); box-shadow: 0 0 0 3px rgba(15,23,42,0.08); }
.plan-tradio { position: absolute; opacity: 0; width: 0; height: 0; }
.plan-tdot { width: 16px; height: 16px; border-radius: 50%; border: 2px solid #d0d0d0; display: inline-block; position: relative; flex-shrink: 0; transition: var(--transition); }
.plan-tdot.on { border-color: var(--primary); }
.plan-tdot.on::after { content: ''; position: absolute; inset: 3px; border-radius: 50%; background: var(--primary); }
.plan-tname { font-size: 13px; font-weight: 600; color: var(--text-primary); width: 80px; }
.plan-tsub { font-size: 12px; color: var(--text-muted); flex: 1; }
.plan-tprice { font-size: 15px; font-weight: 700; color: var(--primary); }

/* ── 数量与优惠 ── */
.form-row { display: flex; gap: 32px; padding: 16px 20px; }
.form-label { font-size: 12px; color: var(--text-muted); display: block; margin-bottom: 6px; font-weight: 500; }
.qty-tmall { display: inline-flex; align-items: center; border: 1px solid #d9d9d9; border-radius: var(--radius-sm); overflow: hidden; }
.qty-tbtn { width: 30px; height: 30px; border: none; background: #fafafa; font-size: 15px; color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: var(--transition); }
.qty-tbtn:hover:not(:disabled) { background: var(--primary-light); color: var(--primary); }
.qty-tbtn:disabled { opacity: 0.3; cursor: not-allowed; }
.qty-tnum { width: 40px; text-align: center; font-size: 14px; font-weight: 600; color: var(--text-primary); border-left: 1px solid #d9d9d9; border-right: 1px solid #d9d9d9; line-height: 30px; }
.coupon-tmall { display: flex; }
.coupon-tinp { width: 170px; height: 30px; padding: 0 10px; border: 1px solid #d9d9d9; border-right: none; border-radius: var(--radius-sm) 0 0 var(--radius-sm); font-size: 12px; outline: none; transition: var(--transition); }
.coupon-tinp:focus { border-color: var(--primary); }
.coupon-tbtn { height: 30px; padding: 0 14px; border: 1px solid #d9d9d9; border-radius: 0 var(--radius-sm) var(--radius-sm) 0; background: #fafafa; color: var(--text-secondary); font-size: 12px; cursor: pointer; transition: var(--transition); }
.coupon-tbtn.on { background: var(--primary); color: #fff; border-color: var(--primary); }
.coupon-tbtn:disabled:not(.on) { opacity: 0.5; cursor: not-allowed; }

/* ── 支付方式 ── */
.pay-tmall { display: flex; overflow: hidden; }
.pay-topt { flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 14px 8px; cursor: pointer; border-right: 1px solid var(--border); font-size: 13px; transition: var(--transition); position: relative; }
.pay-topt:last-child { border-right: none; }
.pay-topt:hover { background: #f8f9fc; }
.pay-topt.sel { background: var(--primary-light); color: var(--primary); font-weight: 600; }
.pay-topt.sel::after { content: ''; position: absolute; bottom: 0; left: 20%; right: 20%; height: 2px; background: var(--primary); border-radius: 2px 2px 0 0; }
.pay-tradio { position: absolute; opacity: 0; width: 0; height: 0; }
.pay-tdot { width: 14px; height: 14px; border-radius: 50%; border: 2px solid #d0d0d0; display: inline-block; position: relative; flex-shrink: 0; transition: var(--transition); }
.pay-tdot.on { border-color: var(--primary); }
.pay-tdot.on::after { content: ''; position: absolute; inset: 2px; border-radius: 50%; background: var(--primary); }
.pay-icon { width: 20px; height: 20px; flex-shrink: 0; }

/* ── 侧边栏 ── */
.side-card { position: sticky; top: 66px; overflow: hidden; }
.side-title { font-size: 14px; font-weight: 700; color: var(--text-primary); padding: 16px 18px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; justify-content: space-between; background: linear-gradient(180deg, #fafbff 0%, #fff 100%); }

/* ── 支付倒计时 ── */
.countdown-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; color: var(--danger); background: #fef0f0; padding: 3px 10px; border-radius: 12px; }
.countdown-badge.urgent { animation: pulse 1s ease-in-out infinite; color: #fff; background: linear-gradient(135deg, #f56c6c, #e04040); box-shadow: 0 2px 8px rgba(245,108,108,0.3); }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.65; } }

/* ── 推荐搭配 ── */
.recommend-body { padding: 10px 14px; }
.recommend-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f5f5f5; transition: var(--transition); }
.recommend-item:last-child { border-bottom: none; }
.recommend-item:hover { padding-left: 4px; }
.recommend-info { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.recommend-name { font-size: 12px; color: var(--text-primary); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.recommend-price { font-size: 12px; color: var(--primary); font-weight: 700; }
.recommend-add { flex-shrink: 0; font-size: 11px; padding: 4px 12px; border-radius: 4px; border: 1px solid var(--primary); color: var(--primary); background: #fff; cursor: pointer; transition: var(--transition); margin-left: 8px; font-weight: 500; }
.recommend-add:hover { background: var(--primary-light); transform: translateY(-1px); }
.recommend-add.added { border-color: var(--success); color: #fff; background: var(--success); }

/* ── 信任标识 ── */
.trust-badges { margin-top: 16px; padding: 14px 16px; background: linear-gradient(135deg, #f0f9f0 0%, #f5faff 100%); border-radius: var(--radius); border: 1px solid #d4edda; }
.trust-badge-item { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #2d6a4f; padding: 6px 0; }
.trust-badge-item + .trust-badge-item { border-top: 1px dashed #e8f0e8; margin-top: 2px; padding-top: 8px; }
.trust-badge-icon { width: 16px; height: 16px; flex-shrink: 0; }

/* ── 侧边栏内容 ── */
.side-body { padding: 14px 18px; }
.side-row { font-size: 12px; color: var(--text-secondary); padding: 4px 0; }
.side-green { color: var(--success); }
.side-total { padding: 14px 18px; border-top: 1px dashed #e0e0e0; display: flex; align-items: baseline; justify-content: flex-end; gap: 6px; background: linear-gradient(180deg, #fafbff 0%, #fff 100%); }
.side-total-label { font-size: 13px; color: var(--text-secondary); }
.side-total-price { font-size: 24px; font-weight: 700; background: linear-gradient(135deg, #0f172a, #334155); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }

/* ── 价格明细 ── */
.price-detail { border-top: 1px solid #f0f0f0; margin-top: 8px; padding-top: 8px; }
.price-detail-header { display: flex; align-items: center; justify-content: space-between; font-size: 12px; color: var(--text-muted); cursor: pointer; padding: 6px 0; user-select: none; border-radius: 4px; transition: var(--transition); }
.price-detail-header:hover { color: var(--text-secondary); background: #f8f9fc; padding: 6px 8px; margin: 0 -8px; }
.price-detail-arrow { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.price-detail-arrow.up { transform: rotate(180deg); }
.price-detail-body { overflow: hidden; max-height: 0; transition: max-height 0.35s cubic-bezier(0.4, 0, 0.2, 1); }
.price-detail.open .price-detail-body { max-height: 250px; }
.price-row { display: flex; justify-content: space-between; font-size: 12px; color: var(--text-secondary); padding: 4px 0; }
.price-row-green { color: var(--success); }
.price-row-blue { color: var(--primary); }
.price-row-total { border-top: 1px dashed #e0e0e0; margin-top: 6px; padding-top: 8px; font-size: 13px; font-weight: 600; color: var(--text-primary); }

/* ── 提交按钮 ── */
.side-btn-wrap { padding: 0 18px 16px; }
.order-btn { width: 100%; height: 44px; border: none; background: linear-gradient(135deg, #0f172a, #334155); color: #fff; font-size: 15px; font-weight: 700; border-radius: var(--radius-sm); cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: var(--transition); box-shadow: 0 4px 12px rgba(15,23,42,0.3); }
.order-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(15,23,42,0.4); }
.order-btn:active:not(:disabled) { transform: translateY(0); }
.order-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.side-agree { font-size: 11px; color: #bbb; text-align: center; padding: 0 18px 16px; }
.side-agree a { color: var(--primary); text-decoration: none; }
.side-agree a:hover { text-decoration: underline; }

/* ── 发票信息 ── */
.invoice-box { padding: 18px; }
.section-sub { font-size: 12px; color: var(--text-muted); font-weight: 400; }
.invoice-type-row { display: flex; gap: 24px; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid #f0f0f0; }
.invoice-radio { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; color: var(--text-secondary); transition: var(--transition); padding: 4px 8px; border-radius: 4px; }
.invoice-radio:hover { background: #f8f9fc; }
.invoice-radio.on { color: var(--primary); font-weight: 600; }
.invoice-radio-input { position: absolute; opacity: 0; width: 0; height: 0; }
.invoice-radio-dot { width: 14px; height: 14px; border-radius: 50%; border: 2px solid #d0d0d0; display: inline-block; position: relative; flex-shrink: 0; transition: var(--transition); }
.invoice-radio-dot.on { border-color: var(--primary); }
.invoice-radio-dot.on::after { content: ''; position: absolute; inset: 2px; border-radius: 50%; background: var(--primary); }
.invoice-fields { display: flex; flex-direction: column; gap: 14px; }
.invoice-field { display: flex; flex-direction: column; gap: 5px; }
.invoice-label { font-size: 12px; color: var(--text-muted); font-weight: 500; }
.invoice-input { height: 34px; padding: 0 12px; border: 1px solid #d9d9d9; border-radius: var(--radius-sm); font-size: 13px; outline: none; transition: var(--transition); }
.invoice-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(15,23,42,0.1); }
.invoice-row { display: flex; gap: 16px; }
.flex-1 { flex: 1; }

/* ── 自动续费 ── */
.renew-box { padding: 18px; }
.renew-toggle { display: flex; align-items: center; gap: 14px; cursor: pointer; user-select: none; }
.renew-checkbox { position: absolute; opacity: 0; width: 0; height: 0; }
.renew-slider { position: relative; width: 42px; height: 24px; background: #d0d0d0; border-radius: 12px; transition: background 0.3s; flex-shrink: 0; }
.renew-slider::after { content: ''; position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 50%; background: #fff; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 1px 4px rgba(0,0,0,0.2); }
.renew-checkbox:checked + .renew-slider { background: linear-gradient(135deg, #0f172a, #1e293b); }
.renew-checkbox:checked + .renew-slider::after { transform: translateX(18px); }
.renew-label { display: flex; flex-direction: column; gap: 3px; }
.renew-label strong { font-size: 13px; color: var(--text-primary); }
.renew-desc { font-size: 11px; color: var(--text-muted); }

/* ── 联系信息 ── */
.contact-box { padding: 18px; }
.contact-row { display: flex; gap: 18px; margin-bottom: 14px; }
.contact-field { display: flex; flex-direction: column; gap: 5px; }
.contact-label { font-size: 12px; color: var(--text-muted); font-weight: 500; }
.contact-input { height: 36px; padding: 0 12px; border: 1px solid #d9d9d9; border-radius: var(--radius-sm); font-size: 13px; outline: none; transition: var(--transition); }
.contact-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(15,23,42,0.1); }
.contact-hint { font-size: 11px; color: #bbb; margin: 0; }
.text-red-400 { color: var(--danger); }

/* ── 订单备注 ── */
.notes-box { padding: 14px 18px; }
.notes-textarea { width: 100%; border: 1px solid #d9d9d9; border-radius: var(--radius-sm); padding: 10px 12px; font-size: 13px; resize: vertical; outline: none; transition: var(--transition); font-family: inherit; box-sizing: border-box; min-height: 70px; }
.notes-textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(15,23,42,0.1); }
.notes-counter { text-align: right; font-size: 11px; color: #bbb; margin-top: 5px; }

/* ── 页脚 ── */
.checkout-footer { position: relative; background: #111827; color: #9ca3af; margin-top: 10px; overflow: hidden; }
.footer-bg { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(31,41,55,0.5) 0%, transparent 100%); pointer-events: none; }
.footer-inner { max-width: 990px; margin: 0 auto; padding: 40px 15px 28px; position: relative; }
.footer-grid { display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr; gap: 24px; }
.footer-logo { display: flex; align-items: center; gap: 8px; text-decoration: none; margin-bottom: 12px; }
.footer-logo-icon { width: 34px; height: 34px; background: #0f172a; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 16px; font-weight: 700; }
.footer-logo-text { font-size: 18px; font-weight: 700; color: #fff; }
.footer-desc { font-size: 13px; line-height: 1.7; color: #6b7280; max-width: 300px; margin-bottom: 0; }
.footer-cta { display: inline-block; margin-top: 12px; font-size: 13px; color: #60a5fa; text-decoration: none; font-weight: 500; }
.footer-cta:hover { color: #93c5fd; }
.footer-social { display: flex; gap: 8px; margin-top: 16px; flex-wrap: wrap; }
.social-icon { width: 34px; height: 34px; background: #1f2937; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #d1d5db; text-decoration: none; transition: all 0.3s ease; }
.social-icon:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.4); }
.social-icon.hover-green:hover { background: #22c55e; color: #fff; }
.social-icon.hover-gray:hover { background: #6b7280; color: #fff; }
.social-icon.hover-blue:hover { background: #334155; color: #fff; }
.footer-col-title { font-size: 13px; font-weight: 600; color: #fff; letter-spacing: 0.3px; margin-bottom: 14px; }
.footer-link { display: block; font-size: 13px; color: #6b7280; text-decoration: none; padding: 4px 0; transition: color 0.2s; }
.footer-link:hover { color: #fff; }
.footer-divider { height: 1px; background: #1f2937; margin: 28px 0 18px; }
.footer-bottom { display: flex; flex-direction: column; gap: 10px; }
@media (min-width: 768px) {
  .footer-bottom { flex-direction: row; align-items: center; justify-content: space-between; }
}
.footer-copy { font-size: 13px; color: #6b7280; margin: 0; }
.footer-legal { display: flex; align-items: center; flex-wrap: wrap; gap: 12px 16px; }
.legal-link { font-size: 12px; color: #9ca3af; text-decoration: none; transition: color 0.2s; }
.legal-link:hover { color: #d1d5db; }
@media (max-width: 767px) {
  .footer-grid { grid-template-columns: 1fr 1fr; }
  .footer-brand { grid-column: 1 / -1; }
}

/* ── 支付弹窗 ── */
.pay-dialog :deep(.el-dialog__header) { padding: 24px 24px 0; }
.pay-dialog :deep(.el-dialog__body) { padding: 4px 24px; }
.pay-dialog :deep(.el-dialog__footer) { padding: 0 24px 24px; }
.pay-dialog :deep(.el-dialog) { border-radius: 12px; overflow: hidden; }
.dlg-btn2 { flex: 1; height: 40px; font-size: 13px; cursor: pointer; border: 1px solid #d9d9d9; border-radius: var(--radius-sm); transition: var(--transition); }
.dlg-cancel2 { background: #fff; color: var(--text-secondary); }
.dlg-cancel2:hover { border-color: #bbb; background: #f8f9fc; }
.dlg-confirm2 { background: linear-gradient(135deg, #0f172a, #334155); color: #fff; border: none; font-weight: 600; box-shadow: 0 4px 12px rgba(15,23,42,0.3); }
.dlg-confirm2:hover:not(:disabled) { background: linear-gradient(135deg, #1e293b, #1d4ed8); transform: translateY(-1px); }
.dlg-confirm2:disabled { opacity: 0.5; cursor: not-allowed; }

/* ── 响应式 ── */
@media (max-width: 800px) {
  .main-layout { flex-direction: column; }
  .main-right { width: 100%; }
  .side-card { position: static; }
  .form-row { flex-direction: column; gap: 16px; }
  .pay-tmall { flex-direction: column; }
  .pay-topt { border-right: none; border-bottom: 1px solid var(--border); }
  .jd-stepbar { display: none; }
  .footer-grid { grid-template-columns: 1fr 1fr; }
  .contact-row { flex-direction: column; gap: 12px; }
  .invoice-row { flex-direction: column; gap: 12px; }
}
</style>
