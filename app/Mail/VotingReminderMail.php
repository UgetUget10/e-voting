<?php

namespace App\Mail;

use App\Models\Election;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VotingReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $voter,
        public ?Election $election,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pengingat: '.($this->election?->name ?? 'Pemilihan').' menunggu suara Anda',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.voting-reminder',
            with: [
                'url' => url('/pemilih'),
            ],
        );
    }
}
