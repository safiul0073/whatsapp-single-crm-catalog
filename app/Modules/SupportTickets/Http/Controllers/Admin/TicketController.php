<?php

namespace App\Modules\SupportTickets\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\SupportTickets\Http\Requests\AdminReplyTicketRequest;
use App\Modules\SupportTickets\Http\Requests\UpdateTicketStatusRequest;
use App\Modules\SupportTickets\Mail\TicketRepliedMail;
use App\Modules\SupportTickets\Models\SupportTicket;
use App\Modules\SupportTickets\Models\SupportTicketReply;
use App\Modules\SupportTickets\Services\SupportTicketAttachmentService;
use App\Modules\SupportTickets\Tables\SupportTicketsTable;
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
        protected SupportTicketAttachmentService $attachments,
    ) {}

    public function index(Request $request): View
    {
        $query = SupportTicket::with('user')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('sort_by')) {
            $query->orderBy($request->sort_by, $request->input('sort_order', 'asc'));
        }

        $tickets = $query->paginate($request->input('per_page', 15))->withQueryString();
        $table = SupportTicketsTable::make();

        return view('support-tickets::admin.tickets.index', compact('tickets', 'table'));
    }

    public function show(SupportTicket $ticket): View
    {
        $ticket->load('user');
        $replies = $ticket->replies()->with('user')->oldest()->get();

        return view('support-tickets::admin.tickets.show', compact('ticket', 'replies'));
    }

    public function reply(AdminReplyTicketRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $admin = auth('admin')->user();

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => null,
            'admin_id' => $admin->id,
            'message' => $request->message,
            'is_staff' => true,
        ]);

        if ($request->hasFile('attachments')) {
            $this->attachments->storeMany($request->file('attachments'), $ticket, $reply, 'admin', $admin->id);
        }

        $ticket->update(['status' => 'in_progress', 'last_replied_at' => now()]);

        $ticketUser = $ticket->user;

        if ($ticketUser) {
            Mail::to($ticketUser)->queue(new TicketRepliedMail($ticketUser, $ticket));

            $this->notifications->send($ticketUser, [
                'title' => __('New reply on Ticket :id', ['id' => $ticket->formatted_id]),
                'body' => $ticket->subject,
                'icon' => 'headset',
                'url' => route('user.support-tickets.show', $ticket),
                'type' => 'info',
            ]);
        }

        return back()->with('success', __('Reply sent successfully.'));
    }

    public function replyAjax(AdminReplyTicketRequest $request, SupportTicket $ticket): JsonResponse
    {
        $admin = auth('admin')->user();

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => null,
            'admin_id' => $admin->id,
            'message' => $request->message,
            'is_staff' => true,
        ]);

        if ($request->hasFile('attachments')) {
            $this->attachments->storeMany($request->file('attachments'), $ticket, $reply, 'admin', $admin->id);
        }

        $ticket->update(['status' => 'in_progress', 'last_replied_at' => now()]);

        $ticketUser = $ticket->user;

        if ($ticketUser) {
            Mail::to($ticketUser)->queue(new TicketRepliedMail($ticketUser, $ticket));

            $this->notifications->send($ticketUser, [
                'title' => __('New reply on Ticket :id', ['id' => $ticket->formatted_id]),
                'body' => $ticket->subject,
                'icon' => 'headset',
                'url' => route('user.support-tickets.show', $ticket),
                'type' => 'info',
            ]);
        }

        return response()->json([
            'success' => true,
            'status' => $ticket->fresh()->status,
            'html' => view('support-tickets::components.conversation-message', [
                'reply' => $reply->load(['user', 'admin', 'attachments']),
                'perspective' => 'admin',
            ])->render(),
        ]);
    }

    public function poll(Request $request, SupportTicket $ticket): JsonResponse
    {
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
                    'perspective' => 'admin',
                ])->render(),
        ]);
    }

    public function updateStatus(UpdateTicketStatusRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $ticket->update(['status' => $request->status]);

        $ticketUser = $ticket->user;

        if ($ticketUser) {
            $this->notifications->send($ticketUser, [
                'title' => __('Ticket :id status updated', ['id' => $ticket->formatted_id]),
                'body' => __('Your ticket status has been changed to :status.', ['status' => $ticket->fresh()->status_label]),
                'icon' => 'life-buoy',
                'url' => route('user.support-tickets.show', $ticket),
                'type' => 'info',
            ]);
        }

        return back()->with('success', __('Ticket status updated.'));
    }

    public function destroy(SupportTicket $ticket): RedirectResponse
    {
        $ticket->delete();

        return redirect()->route('admin.support-tickets.index')
            ->with('success', __('Ticket deleted successfully.'));
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);

        if (! empty($ids)) {
            SupportTicket::whereIn('id', $ids)->delete();
        }

        return back()->with('success', __('Selected tickets deleted.'));
    }
}
