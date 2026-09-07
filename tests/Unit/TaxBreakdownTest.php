<?php

namespace Tests\Unit;

use App\Support\TaxBreakdown;
use PHPUnit\Framework\TestCase;

/** Reglas del reparto del IVA, sin base de datos ni Ajustes de por medio. */
class TaxBreakdownTest extends TestCase
{
    private function breakdown(array $lines, float $discount = 0.0): TaxBreakdown
    {
        return TaxBreakdown::forLines($lines, $discount, 0.16);
    }

    public function test_every_line_taxed_behaves_like_the_old_flat_rate(): void
    {
        $taxes = $this->breakdown([
            ['amount' => 1000, 'taxed' => true],
            ['amount' => 500, 'taxed' => true],
        ]);

        $this->assertSame(1500.0, $taxes->subtotal);
        $this->assertSame(240.0, $taxes->iva);
        $this->assertSame(1740.0, $taxes->total);
    }

    public function test_exempt_lines_add_to_the_subtotal_but_not_to_the_tax(): void
    {
        $taxes = $this->breakdown([
            ['amount' => 1000, 'taxed' => true],
            ['amount' => 500, 'taxed' => false],
        ]);

        $this->assertSame(1500.0, $taxes->subtotal);
        $this->assertSame(160.0, $taxes->iva);
        $this->assertSame(1660.0, $taxes->total);
    }

    public function test_a_fully_exempt_sale_carries_no_tax(): void
    {
        $taxes = $this->breakdown([['amount' => 1000, 'taxed' => false]]);

        $this->assertSame(0.0, $taxes->iva);
        $this->assertSame(1000.0, $taxes->total);
    }

    /** Sin la marca, una línea causa IVA: es lo prudente al facturar. */
    public function test_a_line_without_the_flag_is_taxed(): void
    {
        $this->assertSame(160.0, $this->breakdown([['amount' => 1000]])->iva);
    }

    public function test_a_discount_only_reduces_the_taxable_share(): void
    {
        // Mitad gravada y mitad exenta: de 400 de descuento, 200 tocan la base.
        $taxes = $this->breakdown([
            ['amount' => 1000, 'taxed' => true],
            ['amount' => 1000, 'taxed' => false],
        ], discount: 400);

        $this->assertSame(400.0, $taxes->discount);
        $this->assertSame(1600.0, $taxes->net);
        $this->assertSame(128.0, $taxes->iva);
        $this->assertSame(1728.0, $taxes->total);
    }

    public function test_the_discount_never_exceeds_the_subtotal(): void
    {
        $taxes = $this->breakdown([['amount' => 100, 'taxed' => true]], discount: 500);

        $this->assertSame(100.0, $taxes->discount);
        $this->assertSame(0.0, $taxes->net);
        $this->assertSame(0.0, $taxes->total);
    }

    public function test_an_empty_sale_totals_zero(): void
    {
        $taxes = $this->breakdown([], discount: 50);

        $this->assertSame(0.0, $taxes->subtotal);
        $this->assertSame(0.0, $taxes->iva);
        $this->assertSame(0.0, $taxes->total);
    }

    /** El impuesto se redondea a dos decimales, no se arrastra. */
    public function test_the_tax_is_rounded_to_cents(): void
    {
        $taxes = $this->breakdown([['amount' => 99.99, 'taxed' => true]]);

        $this->assertSame(16.0, $taxes->iva);
        $this->assertSame(115.99, $taxes->total);
    }

    public function test_a_single_line_shortcut_matches_the_general_case(): void
    {
        $this->assertEquals(
            $this->breakdown([['amount' => 750, 'taxed' => false]]),
            TaxBreakdown::forLine(750, taxed: false, rate: 0.16),
        );
    }
}
