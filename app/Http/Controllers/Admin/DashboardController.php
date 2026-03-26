<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Order;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Service;
use App\Models\AgentControl;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        $currentMonth = now()->startOfMonth();

        $metrics = Cache::remember('dashboard_metrics_' . $currentMonth->format('Y_m'), 300, function () use ($currentMonth) {
            return [
                'total_leads' => Lead::count(),
                'leads_nuevos' => Lead::where('status', 'nuevo')->count(),
                'leads_contactados' => Lead::where('status', 'contactado')->count(),
                'leads_cotizados' => Lead::where('status', 'cotizado')->count(),
                'leads_cerrados' => Lead::where('status', 'cerrado')->count(),
                'leads_perdidos' => Lead::where('status', 'perdido')->count(),
                'total_cotizaciones' => Quote::count(),
                'cotizaciones_enviadas' => Quote::where('status', 'enviada')->count(),
                'cotizaciones_aceptadas' => Quote::where('status', 'aceptada')->count(),
                'monto_total_cotizado' => Quote::sum('total'),
                'servicios_activos' => Service::where('active', true)->count(),
                'agent_paused' => AgentControl::isAgentPaused(),

                // Financieros
                'total_clientes' => Client::count(),
                'facturas_pendientes' => Order::whereNull('paid_at')
                    ->whereIn('status', ['sent', 'pending', 'draft'])
                    ->count(),
                'monto_por_cobrar' => Order::whereNull('paid_at')
                    ->whereIn('status', ['sent', 'pending', 'draft'])
                    ->sum('total'),
                'ingresos_mes' => Payment::where('status', 'approved')
                    ->where('paid_at', '>=', $currentMonth)
                    ->sum('amount'),
                'ingresos_total' => Payment::where('status', 'approved')->sum('amount'),
                'facturas_timbradas' => \App\Models\FiscalDocument::where('status', 'valid')->count(),

                // Conversión
                'tasa_conversion' => Lead::count() > 0
                    ? round((Lead::where('status', 'cerrado')->count() / Lead::count()) * 100, 1)
                    : 0,

                // Pipeline $ values
                'valor_nuevos' => Lead::where('status', 'nuevo')->sum('estimated_value'),
                'valor_contactados' => Lead::where('status', 'contactado')->sum('estimated_value'),
                'valor_cotizados' => Lead::where('status', 'cotizado')->sum('estimated_value'),
                'valor_cerrados' => Lead::where('status', 'cerrado')->sum('estimated_value'),
                'valor_perdidos' => Lead::where('status', 'perdido')->sum('estimated_value'),
            ];
        });

        $recent_leads = Lead::latest()->take(10)->get();
        $recent_quotes = Quote::with('lead')->latest()->take(5)->get();
        $unpaid_invoices = Order::with('client')
            ->whereNull('paid_at')
            ->whereIn('status', ['sent', 'pending', 'draft'])
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact('metrics', 'recent_leads', 'recent_quotes', 'unpaid_invoices'));
    }
}
