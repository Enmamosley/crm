<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::with(['client', 'lead', 'assignee']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }
        if ($request->filled('overdue')) {
            $query->where('due_at', '<', now())->whereNot('status', 'done');
        }

        $tasks = $query->orderByRaw("CASE status WHEN 'todo' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->orderBy('due_at')
            ->paginate(20)
            ->appends($request->query());

        $users   = User::orderBy('name')->get();
        $clients = Client::orderBy('legal_name')->get(['id', 'legal_name']);

        return view('admin.tasks.index', compact('tasks', 'users', 'clients'));
    }

    public function create(Request $request)
    {
        $users   = User::orderBy('name')->get();
        $clients = Client::orderBy('legal_name')->get(['id', 'legal_name']);
        $leads   = Lead::orderBy('name')->get(['id', 'name', 'business']);

        $selectedClient = $request->filled('client_id') ? Client::find($request->client_id) : null;
        $selectedLead   = $request->filled('lead_id')   ? Lead::find($request->lead_id)     : null;

        return view('admin.tasks.create', compact('users', 'clients', 'leads', 'selectedClient', 'selectedLead'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'required|in:todo,in_progress,done',
            'priority'    => 'required|in:low,medium,high',
            'due_at'      => 'nullable|date',
            'client_id'   => 'nullable|exists:clients,id',
            'lead_id'     => 'nullable|exists:leads,id',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if (($validated['status'] ?? '') === 'done' && empty($validated['completed_at'])) {
            $validated['completed_at'] = now();
        }

        $task = Task::create(array_merge($validated, ['created_by' => auth()->id()]));

        ActivityLog::log('task_created', $task, "Tarea '{$task->title}' creada");

        if ($request->filled('client_id')) {
            return redirect()->route('admin.clients.show', $request->client_id)
                ->with('success', 'Tarea creada.');
        }
        if ($request->filled('lead_id')) {
            return redirect()->route('admin.leads.show', $request->lead_id)
                ->with('success', 'Tarea creada.');
        }

        return redirect()->route('admin.tasks.index')
            ->with('success', 'Tarea creada.');
    }

    public function edit(Task $task)
    {
        $users   = User::orderBy('name')->get();
        $clients = Client::orderBy('legal_name')->get(['id', 'legal_name']);
        $leads   = Lead::orderBy('name')->get(['id', 'name', 'business']);

        return view('admin.tasks.edit', compact('task', 'users', 'clients', 'leads'));
    }

    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'required|in:todo,in_progress,done',
            'priority'    => 'required|in:low,medium,high',
            'due_at'      => 'nullable|date',
            'client_id'   => 'nullable|exists:clients,id',
            'lead_id'     => 'nullable|exists:leads,id',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if ($validated['status'] === 'done' && $task->status !== 'done') {
            $validated['completed_at'] = now();
        } elseif ($validated['status'] !== 'done') {
            $validated['completed_at'] = null;
        }

        $task->update($validated);

        ActivityLog::log('task_updated', $task, "Tarea '{$task->title}' actualizada");

        return back()->with('success', 'Tarea actualizada.');
    }

    public function destroy(Task $task)
    {
        ActivityLog::log('task_deleted', $task, "Tarea '{$task->title}' eliminada");
        $task->delete();

        return back()->with('success', 'Tarea eliminada.');
    }

    public function complete(Task $task)
    {
        if ($task->status === 'done') {
            $task->update(['status' => 'todo', 'completed_at' => null]);
        } else {
            $task->update(['status' => 'done', 'completed_at' => now()]);
        }

        return back()->with('success', $task->status === 'done' ? 'Tarea completada.' : 'Tarea reabierta.');
    }
}
