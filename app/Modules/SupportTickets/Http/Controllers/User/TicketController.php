<?php

namespace App\Modules\SupportTickets\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\NotificationTemplates\Services\NotificationRecipientResolver;
use App\Modules\SupportTickets\Http\Requests\ReplyTicketRequest;
use App\Modules\SupportTickets\Http\Requests\StoreTicketRequest;
use App\Modules\SupportTickets\Mail\TicketCreatedMail;
use App\Modules\SupportTickets\Mail\TicketRepliedMail;
use App\Modules\SupportTickets\Models\SupportTicket;
use App\Modules\SupportTickets\Models\SupportTicketReply;
use App\Modules\SupportTickets\Services\SupportTicketAttachmentService;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function __construct(
        protected SystemNotificationService $notifications,
        protected NotificationRecipientResolver $recipientResolver,
        protected SupportTicketAttachmentService $attachments,
    ) {}

    public function index(Request $request): View
    {
        $query = SupportTicket::where('user_id', auth()->id())->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $tickets = $query->paginate(15)->withQueryString();

        return view('support-tickets::user.tickets.index', compact('tickets'));
    }

    public function create(): View
    {
        return view('support-tickets::user.tickets.create');
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $ticket = SupportTicket::create([
            'user_id' => auth()->id(),
            'subject' => $request->subject,
            'status' => 'open',
            'priority' => $request->priority,
            'last_replied_at' => now(),
        ]);

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => $request->message,
            'is_staff' => false,
        ]);

        if ($request->hasFile('attachments')) {
            $this->attachments->storeMany($request->file('attachments'), $ticket, $reply, 'user', auth()->id());
        }

        $user = auth()->user();

        Mail::to($user)->queue(new TicketCreatedMail($user, $ticket));

        $this->notifications->send($user, [
            'title' => $ticket->formatted_id.' opened',
            'body' => $ticket->subject,
            'icon' => 'life-buoy',
            'url' => route('user.support-tickets.show', $ticket),
            'type' => 'info',
        ]);

        $this->notifications->send($user, [
            'title' => __('You have a new mail'),
            'body' => __('A confirmation email has been sent for your ticket. Please check your mailbox.'),
            'icon' => 'mail',
            'url' => null,
            'type' => 'info',
        ]);

        $admins = $this->recipientResolver->resolve('all_admins');
        $this->notifications->sendToMany($admins, [
            'title' => __('New support ticket :id', ['id' => $ticket->formatted_id]),
            'body' => $user->name.': '.$ticket->subject,
            'icon' => 'ph-ticket',
            'url' => route('admin.support-tickets.show', $ticket),
            'type' => 'warning',
        ], 'new_ticket');

        return redirect()->route('user.support-tickets.show', $ticket)
            ->with('success', __('Your support ticket has been submitted. We\'ll be in touch shortly.'));
    }

    public function show(SupportTicket $ticket): View
    {
        $this->authorize('view', $ticket);

        $replies = $ticket->replies()->with('user')->oldest()->get();

        return view('support-tickets::user.tickets.show', compact('ticket', 'replies'));
    }

    public function reply(ReplyTicketRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorize('reply', $ticket);

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => $request->message,
            'is_staff' => false,
        ]);

        if ($request->hasFile('attachments')) {
            $this->attachments->storeMany($request->file('attachments'), $ticket, $reply, 'user', auth()->id());
        }

        if (in_array($ticket->status, ['resolved', 'closed'])) {
            $ticket->update(['status' => 'open', 'last_replied_at' => now()]);
        } else {
            $ticket->update(['last_replied_at' => now()]);
        }

        $user = auth()->user();

        Mail::to($user)->queue(new TicketRepliedMail($user, $ticket));

        $this->notifications->send($user, [
            'title' => __('You have a new mail'),
            'body' => __('A confirmation email has been sent for your reply. Please check your mailbox.'),
            'icon' => 'mail',
            'url' => null,
            'type' => 'info',
        ]);

        $admins = $this->recipientResolver->resolve('all_admins');
        $this->notifications->sendToMany($admins, [
            'title' => __('User replied on Ticket :id', ['id' => $ticket->formatted_id]),
            'body' => $user->name.': '.$ticket->subject,
            'icon' => 'ph-chat-circle-text',
            'url' => route('admin.support-tickets.show', $ticket),
            'type' => 'info',
        ], 'ticket_reply');

        return back()->with('success', __('Your reply has been sent.'));
    }

    public function replyAjax(ReplyTicketRequest $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorize('reply', $ticket);

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => $request->message,
            'is_staff' => false,
        ]);

        if ($request->hasFile('attachments')) {
            $this->attachments->storeMany($request->file('attachments'), $ticket, $reply, 'user', auth()->id());
        }

        if (in_array($ticket->status, ['resolved', 'closed'])) {
            $ticket->update(['status' => 'open', 'last_replied_at' => now()]);
        } else {
            $ticket->update(['last_replied_at' => now()]);
        }

        $user = auth()->user();

        Mail::to($user)->queue(new TicketRepliedMail($user, $ticket));

        $this->notifications->send($user, [
            'title' => __('You have a new mail'),
            'body' => __('A confirmation email has been sent for your reply. Please check your mailbox.'),
            'icon' => 'mail',
            'url' => null,
            'type' => 'info',
        ]);

        $admins = $this->recipientResolver->resolve('all_admins');
        $this->notifications->sendToMany($admins, [
            'title' => __('User replied on Ticket :id', ['id' => $ticket->formatted_id]),
            'body' => $user->name.': '.$ticket->subject,
            'icon' => 'ph-chat-circle-text',
            'url' => route('admin.support-tickets.show', $ticket),
            'type' => 'info',
        ], 'ticket_reply');

        return response()->json([
            'success' => true,
            'status' => $ticket->fresh()->status,
            'html' => view('support-tickets::components.conversation-message', [
                'reply' => $reply->load(['user', 'admin', 'attachments']),
                'perspective' => 'user',
            ])->render(),
        ]);
    }

    public function poll(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $after = (int) $request->input('after', 0);

        $replies = $ticket->replies()
            ->with(['user', 'admin', 'attachments'])
            ->where('id', '>', $after)
            ->oldest()
            ->get();

        return response()->json([
            'status' => $ticket->fresh()->status,
            'html' => $replies->isEmpty()
                ? ''
                : view('support-tickets::components.conversation-thread', [
                    'ticket' => $ticket,
                    'replies' => $replies,
                    'perspective' => 'user',
                ])->render(),
        ]);
    }
}
