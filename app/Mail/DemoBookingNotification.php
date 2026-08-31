<?php

namespace App\Mail;

use App\Models\DemoBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DemoBookingNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DemoBooking $booking,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('app.mail.demo_booking_new_subject', ['company' => $this->booking->company_name, 'contact' => $this->booking->contact_name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.demo-booking-notification',
            with: ['booking' => $this->booking],
        );
    }
}
