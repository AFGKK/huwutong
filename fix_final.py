# -*- coding: utf-8 -*-
import sys
sys.stdout.reconfigure(encoding='utf-8')

with open(r"d:\phpEnv\www\88.huwutong.com\resources\views\public\product-detail.blade.php", "r", encoding="utf-8") as f:
    content = f.read()

# Use VERY specific context for each replacement to avoid ambiguity
replacements = []

# 1. Rating star + count (line 561-563 area)
replacements.append((
    '<span class="text-yellow-400">?</span>\n\n                                {{ number_format($_avgRating, 1) }} <span class="text-gray-400">({{ $_ratingCount }}?\ufffd???</span>',
    '<span class="text-yellow-400">\u2605</span>\n\n                                {{ number_format($_avgRating, 1) }} <span class="text-gray-400">({{ $_ratingCount }} \u6761\u8bc4\u4ef7)</span>'
))

# 2. Sold count
replacements.append((
    '<span>??? {{ $_soldTotal }}</span>',
    '<span>\u5df2\u552e {{ $_soldTotal }}</span>'
))

# 3. Wishlist count
replacements.append((
    '{{ $_wishlistCount ?? 0 }} ???\n\n                            </span>\n\n                            <span class="flex items-center gap-1">\n\n                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>',
    '{{ $_wishlistCount ?? 0 }} \u6536\u85cf\n\n                            </span>\n\n                            <span class="flex items-center gap-1">\n\n                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>'
))

# 4. View count
replacements.append((
    '{{ $product->view_count ?? 0 }} ?????\n\n                            </span>',
    '{{ $product->view_count ?? 0 }} \u6b21\u6d4f\u89c8\n\n                            </span>'
))

# 5. Description fallback
replacements.append((
    "{{ $product->description ?: '?????????' }}",
    "{{ $product->description ?: '\u6682\u65e0\u63cf\u8ff0' }}"
))

# 6. Pricing subtitle
replacements.append((
    '<p class="text-gray-500">???????????????????????{ $product->name }}</p>',
    '<p class="text-gray-500">\u9009\u62e9\u9002\u5408\u60a8\u7684\u65b9\u6848\uff0c\u5f00\u542f {{ $product->name }}</p>'
))

# 7. Popular badge
replacements.append((
    '<div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-primary-600 text-white text-xs font-semibold px-4 py-1 rounded-full">??????</div>',
    '<div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-primary-600 text-white text-xs font-semibold px-4 py-1 rounded-full">\u6700\u4f73\u9009\u62e9</div>'
))

# 8. Best value badge
replacements.append((
    '<div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-amber-500 text-white text-xs font-semibold px-4 py-1 rounded-full">???????</div>',
    '<div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-amber-500 text-white text-xs font-semibold px-4 py-1 rounded-full">\u6027\u4ef7\u6bd4\u4e4b\u9009</div>'
))

# 9. Price unit
replacements.append((
    '/??span>',
    '/\u6708</span>'
))

# 10. Yearly savings
replacements.append((
    '<p class="text-xs text-green-600 mb-4">?? \ufffd{{ $plan->price_yearly }}?? {{ round((1 - $plan->price_yearly / ($plan->price_monthly * 12)) * 100) }}%?</p>',
    '<p class="text-xs text-green-600 mb-4">\u5e74\u4ed8 \uffe5{{ $plan->price_yearly }}\uff0c\u7701 {{ round((1 - $plan->price_yearly / ($plan->price_monthly * 12)) * 100) }}%</p>'
))

# 11. Discount label
replacements.append((
    '<span class="text-xs text-red-500 ml-1">???{{ $sku->compare_at_price - $sku->price }}</span>',
    '<span class="text-xs text-red-500 ml-1">\u7701 {{ $sku->compare_at_price - $sku->price }}</span>'
))

# 12. Recommended product description fallback
replacements.append((
    "{{ $rp->description ?: '??????' }}",
    "{{ $rp->description ?: '\u6682\u65e0\u63cf\u8ff0' }}"
))

# 13. Price fallback
replacements.append((
    '<span class="text-xs text-gray-400">??????</span>',
    '<span class="text-xs text-gray-400">\u54a8\u8be2\u4ef7\u683c</span>'
))

# 14. Sold total
replacements.append((
    '{{ $rp->sold_total ?? 0 }} ???',
    '{{ $rp->sold_total ?? 0 }} \u5df2\u552e'
))

# 15. Loading text
replacements.append((
    '<div class="px-4 py-6 text-center text-xs text-gray-400">\n\n                            <svg class="w-6 h-6 mx-auto mb-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>\n\n                            ??????..',
    '<div class="px-4 py-6 text-center text-xs text-gray-400">\n\n                            <svg class="w-6 h-6 mx-auto mb-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>\n\n                            \u52a0\u8f7d\u4e2d..'
))

# 16. Chat header
replacements.append((
    '<div class="text-xs font-semibold text-gray-500 dark-text-muted px-4 pt-3 pb-2 border-b border-gray-100 dark-border">??????</div>',
    '<div class="text-xs font-semibold text-gray-500 dark-text-muted px-4 pt-3 pb-2 border-b border-gray-100 dark-border">\u5e38\u89c1\u95ee\u9898</div>'
))

# Apply all replacements
success = 0
fail = 0
for old, new in replacements:
    if old in content:
        content = content.replace(old, new)
        success += 1
    else:
        fail += 1
        print(f"NOT FOUND: {old[:60]}...")

print(f"Replacements: {success} success, {fail} failed")

with open(r"d:\phpEnv\www\88.huwutong.com\resources\views\public\product-detail.blade.php", "w", encoding="utf-8") as f:
    f.write(content)

print("Done!")
