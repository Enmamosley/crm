<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Setting;
use App\Services\MetaConversionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaPixelTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_is_empty_when_pixel_not_configured(): void
    {
        $html = view('partials.meta-pixel')->render();
        $this->assertStringNotContainsString('fbq', $html);
    }

    public function test_partial_renders_init_and_pageview_when_configured(): void
    {
        Setting::set('meta_pixel_id', '1234567890123456');

        $html = view('partials.meta-pixel')->render();

        $this->assertStringContainsString("fbq('init'", $html);
        $this->assertStringContainsString('1234567890123456', $html);
        $this->assertStringContainsString("fbq('track', 'PageView')", $html);
    }

    public function test_partial_renders_purchase_from_flash(): void
    {
        Setting::set('meta_pixel_id', '1234567890123456');
        session()->flash('meta_purchase', [
            'value' => 100.0, 'currency' => 'MXN', 'event_id' => 'order_5',
        ]);

        $html = view('partials.meta-pixel')->render();

        $this->assertStringContainsString("fbq('track', 'Purchase'", $html);
        $this->assertStringContainsString('order_5', $html);
    }

    public function test_capi_event_id_is_deterministic_and_unconfigured_by_default(): void
    {
        $service = new MetaConversionsService();
        $this->assertFalse($service->isConfigured());

        $order = new Order(['total' => 100]);
        $order->id = 42;
        $this->assertSame('order_42', MetaConversionsService::purchaseEventId($order));
    }
}
