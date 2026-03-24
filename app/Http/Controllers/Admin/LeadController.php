<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('business', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $leads = $query->with('assignee')->latest()->paginate(15)->appends($request->query());
        $salesUsers = User::whereIn('role', ['admin', 'sales'])->get();

        return view('admin.leads.index', compact('leads', 'salesUsers'));
    }

    public function create()
    {
        $salesUsers = User::whereIn('role', ['admin', 'sales'])->get();
        return view('admin.leads.create', compact('salesUsers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'business' => 'nullable|string|max:255',
            'project_description' => 'nullable|string',
            'source' => 'nullable|string|max:100',
            'assigned_to' => 'nullable|exists:users,id',
            'estimated_value' => 'nullable|numeric|min:0',
        ]);

        $lead = Lead::create(array_merge($validated, ['status' => 'nuevo']));

        if ($lead->assigned_to) {
            Notification::notify($lead->assigned_to, 'lead_assigned',
                "Lead asignado: {$lead->name}",
                "Se te asignó el lead '{$lead->name}' de {$lead->business}",
                route('admin.leads.show', $lead));
        }

        $lead->statusHistory()->create([
            'old_status' => null,
            'new_status' => 'nuevo',
            'changed_by' => 'admin',
        ]);

        ActivityLog::log('lead_created', $lead, "Lead '{$lead->name}' creado");

        return redirect()->route('admin.leads.show', $lead)
            ->with('success', 'Lead creado exitosamente.');
    }

    public function show(Lead $lead)
    {
        $lead->load(['notes', 'statusHistory', 'quotes.items', 'assignee', 'tasks.assignee']);
        return view('admin.leads.show', compact('lead'));
    }

    public function edit(Lead $lead)
    {
        $salesUsers = User::whereIn('role', ['admin', 'sales'])->get();
        return view('admin.leads.edit', compact('lead', 'salesUsers'));
    }

    public function update(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'business' => 'nullable|string|max:255',
            'project_description' => 'nullable|string',
            'source' => 'nullable|string|max:100',
            'assigned_to' => 'nullable|exists:users,id',
            'estimated_value' => 'nullable|numeric|min:0',
        ]);

        $oldAssignee = $lead->assigned_to;
        $lead->update($validated);

        if ($lead->assigned_to && $lead->assigned_to !== $oldAssignee) {
            Notification::notify($lead->assigned_to, 'lead_assigned',
                "Lead asignado: {$lead->name}",
                "Se te asignó el lead '{$lead->name}' de {$lead->business}",
                route('admin.leads.show', $lead));
        }

        ActivityLog::log('lead_updated', $lead, "Lead '{$lead->name}' actualizado");

        return redirect()->route('admin.leads.show', $lead)
            ->with('success', 'Lead actualizado exitosamente.');
    }

    public function destroy(Lead $lead)
    {
        ActivityLog::log('lead_deleted', $lead, "Lead '{$lead->name}' eliminado");
        $lead->delete();
        return redirect()->route('admin.leads.index')
            ->with('success', 'Lead eliminado.');
    }

    public function updateStatus(Request $request, Lead $lead)
    {
        $request->validate([
            'status' => 'required|in:' . implode(',', Lead::STATUSES),
        ]);

        $lead->updateStatus($request->status, 'admin');

        ActivityLog::log('lead_status_changed', $lead, "Lead '{$lead->name}' cambió a estado: {$request->status}");

        return redirect()->route('admin.leads.show', $lead)
            ->with('success', 'Estado actualizado a: ' . $request->status);
    }

    public function addNote(Request $request, Lead $lead)
    {
        $request->validate(['content' => 'required|string']);

        $lead->notes()->create([
            'content' => $request->content,
            'author' => auth()->user()->name ?? 'admin',
        ]);

        return redirect()->route('admin.leads.show', $lead)
            ->with('success', 'Nota agregada.');
    }
}
