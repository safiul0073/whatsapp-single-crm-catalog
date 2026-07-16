<?php

namespace App\Modules\Workspaces\Mail;

use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamMemberWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Workspace $workspace,
        public User $member,
        public string $plainPassword,
        public User $invitedBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Welcome to :workspace', ['workspace' => $this->workspace->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'workspaces::emails.team-member-welcome',
            with: [
                'loginUrl' => route('login'),
            ],
        );
    }
}
