<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El SAT exige que serie+folio sea único por emisor. No había restricción, y
 * convivían dos reglas de asignación: el panel numeraba por serie global y las
 * facturas recurrentes por cliente, de modo que dos clientes en la misma serie
 * recibían el mismo folio y ambos se timbraban. El checkout y la conversión
 * desde cotización no asignaban folio en absoluto.
 *
 * Antes de crear el índice hay que sanear lo que ya existe. Se opera sobre la
 * tabla en crudo para incluir también las órdenes borradas: su folio ya se
 * emitió y sigue ocupando sitio.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->failOnStampedDuplicates();
        $this->renumberDuplicates();
        $this->backfillMissingFolios();

        Schema::table('orders', function (Blueprint $table) {
            $table->unique(['series', 'folio_number']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['series', 'folio_number']);
        });
    }

    /**
     * Renumerar una orden ya timbrada rompería la correspondencia con el CFDI
     * que el SAT tiene registrado, así que eso no lo decide una migración.
     */
    private function failOnStampedDuplicates(): void
    {
        $stamped = DB::table('orders')
            ->join('fiscal_documents', 'fiscal_documents.order_id', '=', 'orders.id')
            ->whereNotNull('orders.folio_number')
            ->groupBy('orders.series', 'orders.folio_number')
            ->havingRaw('COUNT(DISTINCT orders.id) > 1')
            ->select('orders.series', 'orders.folio_number')
            ->get();

        if ($stamped->isEmpty()) {
            return;
        }

        $list = $stamped->map(fn ($row) => "{$row->series}{$row->folio_number}")->implode(', ');

        throw new RuntimeException(
            "Hay folios duplicados en órdenes ya timbradas: {$list}. " .
            'Renumerarlas dejaría el CFDI del SAT apuntando a un folio que ya no existe, ' .
            'así que hay que resolverlas a mano (cancelar y reexpedir la que corresponda) ' .
            'antes de aplicar esta migración.'
        );
    }

    /** De cada grupo duplicado se conserva la orden más antigua; el resto se renumera. */
    private function renumberDuplicates(): void
    {
        $duplicates = DB::table('orders')
            ->whereNotNull('folio_number')
            ->groupBy('series', 'folio_number')
            ->havingRaw('COUNT(*) > 1')
            ->select('series', 'folio_number')
            ->get();

        foreach ($duplicates as $duplicate) {
            $ids = DB::table('orders')
                ->where('series', $duplicate->series)
                ->where('folio_number', $duplicate->folio_number)
                ->orderBy('id')
                ->pluck('id')
                ->slice(1); // la primera se queda con el folio

            foreach ($ids as $id) {
                DB::table('orders')->where('id', $id)->update([
                    'folio_number' => $this->nextFolio($duplicate->series),
                ]);
            }
        }
    }

    /** Las órdenes de checkout y de conversión nacían sin folio. */
    private function backfillMissingFolios(): void
    {
        $pending = DB::table('orders')->whereNull('folio_number')->orderBy('id')->get(['id', 'series']);

        foreach ($pending as $order) {
            DB::table('orders')->where('id', $order->id)->update([
                'folio_number' => $this->nextFolio($order->series),
            ]);
        }
    }

    private function nextFolio(string $series): int
    {
        return (int) DB::table('orders')->where('series', $series)->max('folio_number') + 1;
    }
};
