<x-mail::message>
# {{ __('app.mail.demo_notify.heading') }}

**{{ __('app.mail.demo_notify.company') }}**: {{ $booking->company_name }}
**{{ __('app.mail.demo_notify.contact') }}**: {{ $booking->contact_name }}
**{{ __('app.mail.demo_notify.email') }}**: {{ $booking->email }}
**{{ __('app.mail.demo_notify.phone') }}**: {{ $booking->phone ?: __('app.mail.demo_notify.not_provided') }}
**{{ __('app.mail.demo_notify.employees') }}**: {{ $booking->employee_count ?: __('app.mail.demo_notify.not_provided') }}
**{{ __('app.mail.demo_notify.interest') }}**: {{ $booking->product_interest ?: __('app.mail.demo_notify.not_provided') }}
**{{ __('app.mail.demo_notify.source') }}**: {{ $booking->source }}

**{{ __('app.mail.demo_notify.notes') }}**:
{{ $booking->message ?: __('app.mail.demo_notify.none') }}

<x-mail::button :url="url('/admin/demo-booking')">
{{ __('app.mail.demo_notify.cta') }}
</x-mail::button>
</x-mail::message>
