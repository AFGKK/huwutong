<x-mail::message>
# 新 Demo 预约

**公司**: {{ $booking->company_name }}
**联系人**: {{ $booking->contact_name }}
**邮箱**: {{ $booking->email }}
**手机**: {{ $booking->phone ?: '未提供' }}
**员工规模**: {{ $booking->employee_count ?: '未提供' }}
**感兴趣产品**: {{ $booking->product_interest ?: '未提供' }}
**来源**: {{ $booking->source }}

**备注**:
{{ $booking->message ?: '无' }}

<x-mail::button :url="url('/admin/demo-booking')">
查看预约详情
</x-mail::button>
</x-mail::message>
