<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function clientWithDocument(string $name, bool $createFile = true): array
    {
        $client = Client::create([
            'legal_name' => 'Docs SA', 'email' => 'docs@test.com',
            'tax_system' => '616', 'cfdi_use' => 'S01', 'portal_active' => true,
        ]);

        $path = "clients/{$client->id}/documents/archivo.zip";
        if ($createFile) {
            Storage::disk('local')->put($path, 'contenido-zip-de-prueba');
        }

        $doc = $client->documents()->create([
            'name'        => $name,
            'file_path'   => $path,
            'file_type'   => 'application/zip',
            'file_size'   => 23,
            'uploaded_by' => 'Admin',
        ]);

        return [$client, $doc];
    }

    /** Descarga bufferizada: 200 con contenido completo y extensión añadida al nombre. */
    public function test_portal_document_download_is_buffered_with_extension(): void
    {
        Storage::fake('local');
        [$client, $doc] = $this->clientWithDocument('Complemento de pago');

        $response = $this->get("/portal/{$client->portal_token}/documents/{$doc->id}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/zip');
        $this->assertStringContainsString('Complemento de pago.zip', $response->headers->get('Content-Disposition'));
        $this->assertSame('contenido-zip-de-prueba', $response->getContent());
    }

    /** Archivo faltante: 404 limpio, nunca una respuesta corrupta (ERR_INVALID_RESPONSE). */
    public function test_missing_file_returns_clean_404(): void
    {
        Storage::fake('local');
        [$client, $doc] = $this->clientWithDocument('Factura', createFile: false);

        $this->get("/portal/{$client->portal_token}/documents/{$doc->id}")->assertStatus(404);
    }

    /** Nombres con acentos no rompen el header Content-Disposition. */
    public function test_non_ascii_name_does_not_break_headers(): void
    {
        Storage::fake('local');
        [$client, $doc] = $this->clientWithDocument('Constancia de Situación Fiscal');

        $response = $this->get("/portal/{$client->portal_token}/documents/{$doc->id}");

        $response->assertStatus(200);
        $this->assertStringContainsString("filename*=UTF-8''", $response->headers->get('Content-Disposition'));
    }

    /** Un cliente no puede descargar documentos de otro. */
    public function test_cross_client_document_access_denied(): void
    {
        Storage::fake('local');
        [, $doc] = $this->clientWithDocument('Privado');

        $otro = Client::create([
            'legal_name' => 'Intruso SA', 'email' => 'intruso@test.com',
            'tax_system' => '616', 'cfdi_use' => 'S01', 'portal_active' => true,
        ]);

        $this->get("/portal/{$otro->portal_token}/documents/{$doc->id}")->assertStatus(403);
    }
}
