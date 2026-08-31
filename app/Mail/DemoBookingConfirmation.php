<?php

namespace App\Mail;

use App\Models\DemoBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DemoBookingConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DemoBooking $booking,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('app.mail.demo_booking_confirm_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.demo-booking-confirmation',
            with: [
                'name' => $this->booking->contact_name,
                'company' => $this->booking->company_name,
            ],
        );
    }
}
