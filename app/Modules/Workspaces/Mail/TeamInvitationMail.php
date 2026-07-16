<?php

namespace App\Modules\Workspaces\Mail;

use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Workspace $workspace,
        public WorkspaceInvitation $invitation,
        public User $invitedBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You have been invited to join :workspace', ['workspace' => $this->workspace->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'workspaces::emails.team-invitation',
            with: [
                'acceptUrl' => route('invite.show', $this->invitation->token),
            ],
        );
    }
}
