<?php

namespace Tests\Feature;

use App\Services\CosmotownService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La normalización de las respuestas de Cosmotown estaba copiada palabra por
 * palabra entre el panel y el portal. Ahora vive en el servicio.
 */
class CosmotownNormalizationTest extends TestCase
{
    use RefreshDatabase; // el constructor del servicio lee ajustes de la BD

    private function service(): CosmotownService
    {
        return app(CosmotownService::class);
    }

    /** Cosmotown anida los datos bajo 'domain' o los deja en la raíz. */
    public function test_domain_info_is_read_from_either_shape(): void
    {
        $nested = $this->service()->normalizeDomainInfo([
            'domain' => ['name' => 'ejemplo.mx', 'autoRenew' => true],
        ]);
        $flat = $this->service()->normalizeDomainInfo([
            'domain' => 'ejemplo.mx', 'auto_billing' => true,
        ]);

        $this->assertTrue($nested['domain']['auto_billing']);
        $this->assertTrue($flat['domain']['auto_billing']);
    }

    /** Cada campo llega con varios nombres posibles según el endpoint. */
    public function test_field_name_variants_are_unified(): void
    {
        $info = $this->service()->normalizeDomainInfo([
            'domain' => [
                'whoisPrivacy'   => true,
                'registrarLock'  => true,
                'createdDate'    => '2026-01-01',
                'expirationDate' => '2027-01-01',
            ],
        ]);

        $this->assertTrue($info['domain']['whois_privacy']);
        $this->assertTrue($info['domain']['locked']);
        $this->assertSame('2026-01-01', $info['domain']['created']);
        $this->assertSame('2027-01-01', $info['domain']['expiration_date']);
    }

    /** Sin lista de nameservers se reconstruye desde ns1..ns4. */
    public function test_nameservers_fall_back_to_numbered_fields(): void
    {
        $info = $this->service()->normalizeDomainInfo([
            'domain' => ['ns1' => 'a.ns.mx', 'ns2' => 'b.ns.mx', 'ns3' => null],
        ]);

        $this->assertSame(['a.ns.mx', 'b.ns.mx'], $info['nameservers']);
    }

    /** TXT usa 'data'; el resto 'pointsTo'; MX añade prioridad. */
    public function test_dns_records_are_flattened_per_type(): void
    {
        $records = $this->service()->denormalizeDnsRecords([
            'a'   => [['host' => '@', 'pointto' => '1.1.1.1', 'ttl' => 600]],
            'txt' => [['host' => '@', 'content' => 'v=spf1 -all']],
            'mx'  => [['host' => '@', 'pointto' => 'mail.mx', 'priority' => 20]],
        ]);

        $this->assertSame(
            ['type' => 'A', 'host' => '@', 'ttl' => 600, 'pointsTo' => '1.1.1.1'],
            $records[0]
        );
        $this->assertSame('v=spf1 -all', $records[1]['data']);
        $this->assertArrayNotHasKey('pointsTo', $records[1]);
        $this->assertSame(20, $records[2]['priority']);
    }

    public function test_dns_records_use_sensible_defaults(): void
    {
        $records = $this->service()->denormalizeDnsRecords(['mx' => [['pointto' => 'mail.mx']]]);

        $this->assertSame('@', $records[0]['host']);
        $this->assertSame(300, $records[0]['ttl']);
        $this->assertSame(10, $records[0]['priority']);
    }
}
