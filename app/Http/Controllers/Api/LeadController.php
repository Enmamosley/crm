<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadNote;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'business' => 'nullable|string|max:255',
            'project_description' => 'nullable|string',
            'source' => 'nullable|string|max:100',
        ]);

        $validated['source'] = $validated['source'] ?? 'agente';

        $lead = Lead::create(array_merge($validated, ['status' => 'nuevo']));

        $lead->statusHistory()->create([
            'old_status' => null,
            'new_status' => 'nuevo',
            'changed_by' => 'agente',
        ]);

        return response()->json([
            'success' => true,
            'data' => $lead,
            'message' => 'Lead creado exitosamente.',
        ], 201);
    }

    public function show(Lead $lead)
    {
        $lead->load(['notes', 'statusHistory', 'quotes']);
        return response()->json(['success' => true, 'data' => $lead]);
    }

    public function index(Request $request)
    {
        $query = Lead::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $leads = $query->latest()->paginate(20);

        return response()->json(['success' => true, 'data' => $leads]);
    }

    public function search(Request $request)
    {
        $request->validate([
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'name'  => 'nullable|string',
        ]);

        if (!$request->filled('phone') && !$request->filled('email') && !$request->filled('name')) {
            return response()->json([
                'success' => false,
                'message' => 'Debes enviar al menos un parámetro: phone, email o name.',
            ], 422);
        }

        $query = Lead::query();

        if ($request->filled('phone')) {
            $query->where('phone', $request->phone);
        }
        if ($request->filled('email')) {
            $query->where('email', $request->email);
        }
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        $leads = $query->latest()->get();

        return response()->json(['success' => true, 'data' => $leads]);
    }

    public function updateStatus(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'status'     => 'required|string|in:' . implode(',', Lead::STATUSES),
            'changed_by' => 'nullable|string|max:100',
        ]);

        $lead->updateStatus($validated['status'], $validated['changed_by'] ?? 'agente');

        return response()->json([
            'success' => true,
            'data'    => $lead->fresh(),
            'message' => 'Estado del lead actualizado.',
        ]);
    }

    public function addNote(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'author'  => 'nullable|string|max:100',
        ]);

        $note = $lead->notes()->create([
            'content' => $validated['content'],
            'author'  => $validated['author'] ?? 'agente',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $note,
            'message' => 'Nota agregada al lead.',
        ], 201);
    }
}
