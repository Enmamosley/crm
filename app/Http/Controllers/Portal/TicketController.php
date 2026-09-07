<?php

namespace App\Http\Controllers\Portal;

use App\Models\SupportTicket;
use App\Models\TicketReply;
use Illuminate\Http\Request;

/**
 * Tickets de soporte abiertos por el cliente desde el portal.
 */

class TicketController extends PortalController
{
    public function tickets(string $token)
    {
        $client = $this->client();
        $tickets = SupportTicket::where('client_id', $client->id)
            ->latest()
            ->paginate(10);

        return view('portal.tickets.index', compact('client', 'tickets'));
    }

    public function createTicket(string $token)
    {
        $client = $this->client();
        return view('portal.tickets.create', compact('client'));
    }

    public function storeTicket(Request $request, string $token)
    {
        $client = $this->client();

        $validated = $request->validate([
            'subject'     => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'priority'    => 'required|in:low,medium,high,urgent',
        ]);

        $ticket = SupportTicket::create([
            'client_id'   => $client->id,
            'subject'     => $validated['subject'],
            'description' => $validated['description'],
            'priority'    => $validated['priority'],
            'status'      => 'open',
        ]);

        // Notificar a admins
        $admins = \App\Models\User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            \App\Models\Notification::notify($admin->id, 'new_ticket',
                "Nuevo ticket: #{$ticket->id}",
                "{$client->legal_name} creó el ticket '{$ticket->subject}'",
                route('admin.tickets.show', $ticket));
        }

        return redirect()->route('portal.tickets.show', [$token, $ticket])
            ->with('success', 'Ticket creado exitosamente.');
    }

    public function showTicket(string $token, SupportTicket $ticket)
    {
        $client = $this->client();
        $this->authorizeOwnership($client, $ticket);

        $ticket->load(['replies' => fn ($q) => $q->where('is_internal', false)->with('user')]);

        return view('portal.tickets.show', compact('client', 'ticket'));
    }

    public function replyToTicket(Request $request, string $token, SupportTicket $ticket)
    {
        $client = $this->client();
        $this->authorizeOwnership($client, $ticket);

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        TicketReply::create([
            'support_ticket_id' => $ticket->id,
            'client_id'         => $client->id,
            'body'              => $validated['body'],
            'is_internal'       => false,
        ]);

        if (in_array($ticket->status, ['resolved', 'closed'])) {
            $ticket->update(['status' => 'open']);
        }

        return back()->with('success', 'Respuesta enviada.');
    }
}
