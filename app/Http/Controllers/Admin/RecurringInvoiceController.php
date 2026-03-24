<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Quote;
use App\Models\RecurringInvoiceSchedule;
use App\Models\Setting;
use Illuminate\Http\Request;

class RecurringInvoiceController extends Controller
{
    public function index()
    {
        $schedules = RecurringInvoiceSchedule::with('client')
            ->latest()
            ->paginate(20);

        return view('admin.recurring-invoices.index', compact('schedules'));
    }

    public function create(Request $request)
    {
        $clients = Client::orderBy('legal_name')->get(['id', 'legal_name', 'tax_id']);
        $selectedClient = $request->filled('client_id') ? Client::find($request->client_id) : null;
        $ivaPercentage = (float) Setting::get('iva_percentage', 16);

        return view('admin.recurring-invoices.create', compact('clients', 'selectedClient', 'ivaPercentage'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id'       => 'required|exists:clients,id',
            'quote_id'        => 'nullable|exists:quotes,id',
            'series'          => 'required|string|max:10',
            'payment_form'    => 'required|string',
            'payment_method'  => 'required|string|in:PUE,PPD',
            'use_cfdi'        => 'required|string',
            'subtotal'        => 'required|numeric|min:0',
            'iva_amount'      => 'required|numeric|min:0',
            'total'           => 'required|numeric|min:0',
            'frequency'       => 'required|in:monthly,quarterly,yearly',
            'day_of_month'    => 'required|integer|min:1|max:28',
            'next_issue_date' => 'required|date|after_or_equal:today',
            'end_date'        => 'nullable|date|after:next_issue_date',
            'auto_stamp'      => 'boolean',
            'notes'           => 'nullable|string',
        ]);

        $schedule = RecurringInvoiceSchedule::create($validated);
        ActivityLog::log('recurring_created', $schedule, "Programación recurrente creada para {$schedule->client->legal_name}");

        return redirect()->route('admin.recurring-invoices.index')
            ->with('success', 'Programación de factura recurrente creada.');
    }

    public function show(RecurringInvoiceSchedule $recurringInvoice)
    {
        $recurringInvoice->load(['client', 'quote']);
        return view('admin.recurring-invoices.show', compact('recurringInvoice'));
    }

    public function edit(RecurringInvoiceSchedule $recurringInvoice)
    {
        $clients = Client::orderBy('legal_name')->get(['id', 'legal_name', 'tax_id']);
        return view('admin.recurring-invoices.edit', compact('recurringInvoice', 'clients'));
    }

    public function update(Request $request, RecurringInvoiceSchedule $recurringInvoice)
    {
        $validated = $request->validate([
            'series'          => 'required|string|max:10',
            'payment_form'    => 'required|string',
            'payment_method'  => 'required|string|in:PUE,PPD',
            'use_cfdi'        => 'required|string',
            'subtotal'        => 'required|numeric|min:0',
            'iva_amount'      => 'required|numeric|min:0',
            'total'           => 'required|numeric|min:0',
            'frequency'       => 'required|in:monthly,quarterly,yearly',
            'day_of_month'    => 'required|integer|min:1|max:28',
            'next_issue_date' => 'required|date',
            'end_date'        => 'nullable|date',
            'auto_stamp'      => 'boolean',
            'active'          => 'boolean',
            'notes'           => 'nullable|string',
        ]);

        $recurringInvoice->update($validated);

        return redirect()->route('admin.recurring-invoices.index')
            ->with('success', 'Programación actualizada.');
    }

    public function destroy(RecurringInvoiceSchedule $recurringInvoice)
    {
        ActivityLog::log('recurring_deleted', $recurringInvoice, "Programación recurrente #{$recurringInvoice->id} eliminada");
        $recurringInvoice->delete();

        return redirect()->route('admin.recurring-invoices.index')
            ->with('success', 'Programación eliminada.');
    }
}
