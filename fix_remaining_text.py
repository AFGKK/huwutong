import re

with open(r"d:\phpEnv\www\88.huwutong.com\resources\views\public\product-detail.blade.php", "r", encoding="utf-8") as f:
    content = f.read()

replacements = [
    # === NAVIGATION / MENU ===
    # Logout button (desktop)
    ('<button type="submit" class="text-sm text-gray-500 hover:text-primary-600 transition">??</button>',
     '<button type="submit" class="text-sm text-gray-500 hover:text-primary-600 transition">\u9000\u51fa</button>'),
    # Mobile nav pricing link
    ('<a href="{{ url(\'/pricing\') }}" class="block px-3 py-2 text-gray-600 hover:text-primary-600">??</a>',
     '<a href="{{ url(\'/pricing\') }}" class="block px-3 py-2 text-gray-600 hover:text-primary-600">\u5b9a\u4ef7</a>'),
    # Mobile cart link
    ('>???<span id="cart-badge-mobile"',
     '>\u8d2d\u7269\u8f66<span id="cart-badge-mobile"'),
    # Mobile logout button
    ('<button type="submit" class="w-full text-left py-2 text-gray-600 hover:text-primary-600">??</button>',
     '<button type="submit" class="w-full text-left py-2 text-gray-600 hover:text-primary-600">\u9000\u51fa</button>'),
    # Mobile login link
    ('<a href="/build/login" class="block py-2 text-gray-600 hover:text-primary-600">??</a>',
     '<a href="/build/login" class="block py-2 text-gray-600 hover:text-primary-600">\u767b\u5f55</a>'),

    # === BADGES ===
    # Seller badge
    ('<span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium shrink-0">???</span>',
     '<span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium shrink-0">\u5b98\u65b9\u6388\u6743</span>'),
    # Product badges (new, hot, discount)
    ('<span class="px-2 py-0.5 bg-green-500 text-white text-xs font-bold rounded-full">???</span>',
     '<span class="px-2 py-0.5 bg-green-500 text-white text-xs font-bold rounded-full">\u65b0\u54c1</span>'),
    ('<span class="px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full">???</span>',
     '<span class="px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full">\u70ed\u9500</span>'),
    ('<span class="px-2 py-0.5 bg-orange-500 text-white text-xs font-bold rounded-full">???</span>',
     '<span class="px-2 py-0.5 bg-orange-500 text-white text-xs font-bold rounded-full">\u7279\u4ef7</span>'),

    # === FORM LABELS ===
    ('<label class="block text-sm text-gray-600 mb-2">???</label>',
     '<label class="block text-sm text-gray-600 mb-2">\u60a8\u7684\u8bc4\u5206</label>'),

    # === SHARE BUTTONS ===
    ('<span class="text-sm text-gray-600 font-medium">???</span>\n                </button>\n                <!-- ??? -->\n                <button onclick="shareWeibo()"',
     '<span class="text-sm text-gray-600 font-medium">\u5fae\u4fe1</span>\n                </button>\n                <!-- \u5206\u4eab\u5230\u5fae\u535a -->\n                <button onclick="shareWeibo()"'),
    ('<span class="text-sm text-gray-600 font-medium">???</span>\n                </button>\n                <!-- ?????? -->\n                <button onclick="shareCopyLink()"',
     '<span class="text-sm text-gray-600 font-medium">\u5fae\u535a</span>\n                </button>\n                <!-- \u590d\u5236\u94fe\u63a5 -->\n                <button onclick="shareCopyLink()"'),

    # === STOCK NOTIFY ===
    ('<label class="text-sm text-gray-600 mb-1 block">??????</label>\n                    <input id="stock-notify-email"',
     '<label class="text-sm text-gray-600 mb-1 block">\u90ae\u7bb1\u5730\u5740</label>\n                    <input id="stock-notify-email"'),
    ('<label class="text-sm text-gray-600 mb-1 block">???????????</label>\n                    <input id="stock-notify-phone"',
     '<label class="text-sm text-gray-600 mb-1 block">\u624b\u673a\u53f7\u7801</label>\n                    <input id="stock-notify-phone"'),

    # === COMPARE BAR ===
    ('<button onclick="saveCompareList([])" class="shrink-0 text-xs text-gray-400 hover:text-red-500 transition">??</button>',
     '<button onclick="saveCompareList([])" class="shrink-0 text-xs text-gray-400 hover:text-red-500 transition">\u6e05\u7a7a</button>'),

    # === CHAT: Send product link ===
    ('title="\?\?\?\?\?\?\?\?\?\?\?"',
     'title="\u53d1\u9001\u5546\u54c1\u94fe\u63a5"'),
]

# Apply all replacements
for old, new in replacements:
    if old in content:
        content = content.replace(old, new)
    else:
        print("NOT FOUND (len=%d): [%s]" % (len(old), old[:60]))

# === CHAT: button text after the SVG ===
# Fix "???????" after send product link SVG
content = content.replace(
    '\/></svg>\n                \?\?\?\?\?\?\?',
    '\/></svg>\n                \u53d1\u9001\u5546\u54c1'
)

# Fix FAQ button text "??????"
content = content.replace(
    '\/></svg>\n                    \?\?\?\?\?\?\?',
    '\/></svg>\n                    \u5e38\u89c1\u95ee\u9898'
)

# Fix FAQ panel header
content = content.replace(
    '\u5e38\u89c1\u95ee\u9898"',
    '\u5e38\u89c1\u95ee\u9898"'
)

# Wait, let me just do simpler replacements for the remaining items

# FAQ panel header "??????"
old_faq_header = '<div class="text-xs font-semibold text-gray-500 dark-text-muted px-4 pt-3 pb-2 border-b border-gray-100 dark-border">\?\?\?\?\?\?</div>'
new_faq_header = '<div class="text-xs font-semibold text-gray-500 dark-text-muted px-4 pt-3 pb-2 border-b border-gray-100 dark-border">\u5e38\u89c1\u95ee\u9898</div>'
content = content.replace(old_faq_header, new_faq_header)

# FAQ loading text "?????.."
content = content.replace(
    '\/></svg>\n                            \?\?\?\?\?\?..',
    '\/></svg>\n                            \u52a0\u8f7d\u4e2d..'
)

# Handoff button title "?????????"
content = content.replace(
    'title="\?\?\?\?\?\?\?\?\?\?"',
    'title="\u8f6c\u4eba\u5de5\u670d\u52a1"'
)

# Handoff button text "?????"
content = content.replace(
    '\/></svg>\n                \?\?\?\?\?\?',
    '\/></svg>\n                \u8f6c\u4eba\u5de5'
)

# Emoji picker title "???"
content = content.replace(
    'title="\?\?\?\?"',
    'title="\u8868\u60c5"'
)

# FAQ button title "??????"
content = content.replace(
    'toggleFaqQuick()" class="text-xs text-green-600 bg-green-50 hover:bg-green-100 px-3 py-1.5 rounded-lg transition flex items-center gap-1" title="\?\?\?\?\?\?\?"',
    'toggleFaqQuick()" class="text-xs text-green-600 bg-green-50 hover:bg-green-100 px-3 py-1.5 rounded-lg transition flex items-center gap-1" title="\u5e38\u89c1\u95ee\u9898"'
)

# Send product link button title "??????????"
content = content.replace(
    'sendProductLink()" class="text-xs text-primary-600 bg-primary-50 hover:bg-primary-100 px-3 py-1.5 rounded-lg transition flex items-center gap-1" title="\?\?\?\?\?\?\?\?\?\?\?\?"',
    'sendProductLink()" class="text-xs text-primary-600 bg-primary-50 hover:bg-primary-100 px-3 py-1.5 rounded-lg transition flex items-center gap-1" title="\u53d1\u9001\u5546\u54c1\u94fe\u63a5"'
)

# === COMMENTS ===
content = content.replace('<!-- \?\?\? -->', '<!-- \u5728\u7ebf\u72b6\u6001 -->')
content = content.replace('<!-- \?\?\?\?\?\?\? -->', '<!-- \u4ea7\u54c1\u64cd\u4f5c -->')
content = content.replace('<!-- Emoji \?\?\? -->', '<!-- Emoji \u9009\u62e9\u5668 -->')

# === JS STRING ===
js_old_1 = "'<p class=\\\"text-sm text-gray-500 mb-2\\\">' + escHtml(img.label || '???') + '</p>' +"
js_new_1 = "'<p class=\\\"text-sm text-gray-500 mb-2\\\">' + escHtml(img.label || '\u56fe\u7247') + '</p>' +"
content = content.replace(js_old_1, js_new_1)

js_old_2 = "'<img src=\\\"' + escHtml(img.url) + '\\\" alt=\\\"' + escHtml(img.label || '???') + '\\\"'"
js_new_2 = "'<img src=\\\"' + escHtml(img.url) + '\\\" alt=\\\"' + escHtml(img.label || '\u56fe\u7247') + '\\\"'"
content = content.replace(js_old_2, js_new_2)

# === EMOJI PICKER: Fix broken quotes first ===
# Lines with missing closing quote in insertEmoji('??)
broken1_old = "<span onclick=\"insertEmoji('??)\" class=\"w-9 h-9 flex items-center justify-center text-xl cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition\">?</span>"
broken1_new = "<span onclick=\"insertEmoji('??')\" class=\"w-9 h-9 flex items-center justify-center text-xl cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition\">??</span>"
content = content.replace(broken1_old, broken1_new)

broken2_old = "<span onclick=\"insertEmoji('????)\" class=\"w-9 h-9 flex items-center justify-center text-xl cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition\">???</span>"
broken2_new = "<span onclick=\"insertEmoji('????')\" class=\"w-9 h-9 flex items-center justify-center text-xl cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition\">????</span>"
content = content.replace(broken2_old, broken2_new)

# Replace emoji placeholders with actual emojis
emoji_list = ['😊', '😂', '❤️', '😍', '🤔', '😎', '🔥', '👍', '🎉', '💡',
              '😀', '😁', '🤣', '😅', '😆', '😉', '😋', '😏', '😒',
              '😔', '😖', '😘', '😚', '😜', '😝', '😡', '😢', '😤', '😥',
              '😨', '😰', '😱', '😲', '😳', '😴', '😵', '😷', '🙏', '💪',
              '👏', '🤝', '🌈', '⭐', '💯', '✅', '❌', '💖', '💙', '💚']

emoji_idx = [0]  # Using list to allow mutation in nested function

def replace_emoji(m):
    idx = emoji_idx[0]
    emoji_idx[0] = idx + 1
    emoji = emoji_list[idx % len(emoji_list)]
    onclick = m.group(1)  # the ?? or ??? part
    return '<span onclick="insertEmoji(\'%s\')" class="w-9 h-9 flex items-center justify-center text-xl cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition">%s</span>' % (emoji, emoji)

pattern = r'<span onclick="insertEmoji\(\'(\?+)\'\)" class="w-9 h-9 flex items-center justify-center text-xl cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition">\?+</span>'
content = re.sub(pattern, replace_emoji, content)

# Count remaining
count = content.count('???')
print("Remaining ??? count: %d" % count)
if count > 0:
    for i, line in enumerate(content.split('\n'), 1):
        if '???' in line:
            print("  Line %d: %s" % (i, line.strip()[:120]))

with open(r"d:\phpEnv\www\88.huwutong.com\resources\views\public\product-detail.blade.php", "w", encoding="utf-8") as f:
    f.write(content)

print("\nDone!")
