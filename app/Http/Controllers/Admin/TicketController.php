<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportTicket::with(['client', 'assignee']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $tickets = $query->latest()->paginate(15)->appends($request->query());
        $users = User::all();

        return view('admin.tickets.index', compact('tickets', 'users'));
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load(['client', 'assignee', 'replies.user', 'replies.client']);
        $users = User::all();
        return view('admin.tickets.show', compact('ticket', 'users'));
    }

    public function update(Request $request, SupportTicket $ticket)
    {
        $validated = $request->validate([
            'status'      => 'nullable|in:' . implode(',', SupportTicket::STATUSES),
            'priority'    => 'nullable|in:' . implode(',', SupportTicket::PRIORITIES),
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $oldAssignee = $ticket->assigned_to;
        $ticket->update(array_filter($validated, fn ($v) => $v !== null));

        if (isset($validated['assigned_to']) && $validated['assigned_to'] != $oldAssignee) {
            Notification::notify($validated['assigned_to'], 'ticket_assigned',
                "Ticket asignado: #{$ticket->id}",
                "Se te asignó el ticket '{$ticket->subject}' de {$ticket->client->legal_name}",
                route('admin.tickets.show', $ticket));
        }

        ActivityLog::log('ticket_updated', $ticket, "Ticket #{$ticket->id} actualizado");

        return back()->with('success', 'Ticket actualizado.');
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $validated = $request->validate([
            'body'        => 'required|string',
            'is_internal' => 'boolean',
        ]);

        TicketReply::create([
            'support_ticket_id' => $ticket->id,
            'user_id'           => auth()->id(),
            'body'              => $validated['body'],
            'is_internal'       => $request->boolean('is_internal'),
        ]);

        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        return back()->with('success', 'Respuesta enviada.');
    }
}
