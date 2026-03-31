<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Quote;
use App\Models\RecurringInvoiceSchedule;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $clients = Client::orderBy('name')->get(['id', 'name', 'legal_name', 'tax_id']);
        $selectedClient = $request->filled('client_id') ? Client::find($request->client_id) : null;
        $ivaPercentage = (float) Setting::get('iva_percentage', 16);
        $services = Service::active()->orderBy('name')->get(['id', 'name', 'price']);

        return view('admin.recurring-invoices.create', compact('clients', 'selectedClient', 'ivaPercentage', 'services'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id'                        => 'required|exists:clients,id',
            'quote_id'                         => 'nullable|exists:quotes,id',
            'series'                           => 'required|string|max:10',
            'payment_form'                     => 'required|string',
            'payment_method'                   => 'required|string|in:PUE,PPD',
            'use_cfdi'                         => 'required|string',
            'frequency'                        => 'required|in:monthly,quarterly,yearly',
            'day_of_month'                     => 'required|integer|min:1|max:28',
            'next_issue_date'                  => 'required|date|after_or_equal:today',
            'end_date'                         => 'nullable|date|after:next_issue_date',
            'auto_stamp'                       => 'boolean',
            'notes'                            => 'nullable|string',
            'items'                            => 'required|array|min:1',
            'items.*.description'              => 'required|string|max:500',
            'items.*.quantity'                 => 'required|numeric|min:0.001',
            'items.*.unit_price'               => 'required|numeric|min:0',
            'items.*.sat_product_key'          => 'nullable|string|max:20',
            'items.*.sat_unit_key'             => 'nullable|string|max:10',
            'items.*.sat_unit_name'            => 'nullable|string|max:100',
            'items.*.tax_object'               => 'nullable|string|max:5',
            'items.*.iva_exempt'               => 'nullable|boolean',
        ]);

        $ivaRate = (float) Setting::get('iva_percentage', 16) / 100;
        $subtotal = 0;
        foreach ($validated['items'] as $row) {
            $subtotal += (float)$row['quantity'] * (float)$row['unit_price'];
        }
        $ivaAmount = $subtotal * $ivaRate;
        $total     = $subtotal + $ivaAmount;

        $schedule = DB::transaction(function () use ($validated, $subtotal, $ivaAmount, $total) {
            $schedule = RecurringInvoiceSchedule::create(array_merge(
                collect($validated)->except('items')->toArray(),
                ['subtotal' => $subtotal, 'iva_amount' => $ivaAmount, 'total' => $total]
            ));

            foreach ($validated['items'] as $row) {
                $lineTotal = (float)$row['quantity'] * (float)$row['unit_price'];
                $schedule->items()->create([
                    'description'     => $row['description'],
                    'sat_product_key' => $row['sat_product_key'] ?? '80101501',
                    'sat_unit_key'    => $row['sat_unit_key']    ?? 'E48',
                    'sat_unit_name'   => $row['sat_unit_name']   ?? 'Servicio',
                    'tax_object'      => $row['tax_object']      ?? '02',
                    'iva_exempt'      => !empty($row['iva_exempt']),
                    'quantity'        => $row['quantity'],
                    'unit_price'      => $row['unit_price'],
                    'total'           => $lineTotal,
                ]);
            }

            return $schedule;
        });

        $clientName = $schedule->client->name ?? $schedule->client->legal_name;
        ActivityLog::log('recurring_created', $schedule, "Programación recurrente creada para {$clientName}");

        return redirect()->route('admin.recurring-invoices.show', $schedule)
            ->with('success', 'Programación de factura recurrente creada.');
    }

    public function show(RecurringInvoiceSchedule $recurringInvoice)
    {
        $recurringInvoice->load(['client', 'quote', 'items']);
        return view('admin.recurring-invoices.show', compact('recurringInvoice'));
    }

    public function edit(RecurringInvoiceSchedule $recurringInvoice)
    {
        $recurringInvoice->load('items');
        $clients = Client::orderBy('name')->get(['id', 'name', 'legal_name', 'tax_id']);
        $services = Service::active()->orderBy('name')->get(['id', 'name', 'price']);
        $ivaPercentage = (float) Setting::get('iva_percentage', 16);
        return view('admin.recurring-invoices.edit', compact('recurringInvoice', 'clients', 'services', 'ivaPercentage'));
    }

    public function update(Request $request, RecurringInvoiceSchedule $recurringInvoice)
    {
        $validated = $request->validate([
            'series'                  => 'required|string|max:10',
            'payment_form'            => 'required|string',
            'payment_method'          => 'required|string|in:PUE,PPD',
            'use_cfdi'                => 'required|string',
            'frequency'               => 'required|in:monthly,quarterly,yearly',
            'day_of_month'            => 'required|integer|min:1|max:28',
            'next_issue_date'         => 'required|date',
            'end_date'                => 'nullable|date',
            'auto_stamp'              => 'boolean',
            'active'                  => 'boolean',
            'notes'                   => 'nullable|string',
            'items'                   => 'required|array|min:1',
            'items.*.description'     => 'required|string|max:500',
            'items.*.quantity'        => 'required|numeric|min:0.001',
            'items.*.unit_price'      => 'required|numeric|min:0',
            'items.*.sat_product_key' => 'nullable|string|max:20',
            'items.*.sat_unit_key'    => 'nullable|string|max:10',
            'items.*.sat_unit_name'   => 'nullable|string|max:100',
            'items.*.tax_object'      => 'nullable|string|max:5',
            'items.*.iva_exempt'      => 'nullable|boolean',
        ]);

        $ivaRate = (float) Setting::get('iva_percentage', 16) / 100;
        $subtotal = 0;
        foreach ($validated['items'] as $row) {
            $subtotal += (float)$row['quantity'] * (float)$row['unit_price'];
        }
        $ivaAmount = $subtotal * $ivaRate;
        $total     = $subtotal + $ivaAmount;

        DB::transaction(function () use ($validated, $subtotal, $ivaAmount, $total, $recurringInvoice) {
            $recurringInvoice->update(array_merge(
                collect($validated)->except('items')->toArray(),
                ['subtotal' => $subtotal, 'iva_amount' => $ivaAmount, 'total' => $total]
            ));

            $recurringInvoice->items()->delete();
            foreach ($validated['items'] as $row) {
                $lineTotal = (float)$row['quantity'] * (float)$row['unit_price'];
                $recurringInvoice->items()->create([
                    'description'     => $row['description'],
                    'sat_product_key' => $row['sat_product_key'] ?? '80101501',
                    'sat_unit_key'    => $row['sat_unit_key']    ?? 'E48',
                    'sat_unit_name'   => $row['sat_unit_name']   ?? 'Servicio',
                    'tax_object'      => $row['tax_object']      ?? '02',
                    'iva_exempt'      => !empty($row['iva_exempt']),
                    'quantity'        => $row['quantity'],
                    'unit_price'      => $row['unit_price'],
                    'total'           => $lineTotal,
                ]);
            }
        });

        return redirect()->route('admin.recurring-invoices.show', $recurringInvoice)
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
