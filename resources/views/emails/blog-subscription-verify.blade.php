<x-mail::message>
# 确认订阅

感谢你的订阅！你已选择接收以下类型的更新：

**{{ $types }}**

请点击下方按钮确认你的邮箱地址：

<x-mail::button :url="$verifyUrl">
确认订阅
</x-mail::button>

如果你没有请求此订阅，请忽略此邮件。

---

如果你不想收到此类邮件，可以 <a href="{{ $unsubscribeUrl }}" style="color: #909399;">取消订阅</a>
</x-mail::message>
