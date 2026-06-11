<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Descargas de archivos BUFFERIZADAS (no streamed).
 *
 * Storage::download() responde en streaming: si la lectura falla después de
 * emitir los headers, el navegador recibe una respuesta corrupta
 * (ERR_INVALID_RESPONSE). Aquí se lee el archivo completo ANTES de responder:
 * cualquier fallo produce un 404 limpio. Además normaliza el nombre del
 * archivo (extensión garantizada + compatible con caracteres no-ASCII).
 */
class FileResponse
{
    public static function download(string $disk, ?string $path, string $name, ?string $mime = null)
    {
        $storage = Storage::disk($disk);

        abort_unless($path && $storage->exists($path), 404, 'El archivo no está disponible.');

        try {
            $contents = $storage->get($path);
        } catch (\Throwable $e) {
            Log::error('FileResponse: no se pudo leer el archivo', [
                'disk' => $disk, 'path' => $path, 'error' => $e->getMessage(),
            ]);
            abort(404, 'El archivo no está disponible.');
        }

        if ($contents === null) {
            abort(404, 'El archivo no está disponible.');
        }

        // Garantizar extensión en el nombre (si el path la tiene y el nombre no)
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== '' && !str_ends_with(strtolower($name), '.' . $ext)) {
            $name .= '.' . $ext;
        }

        // Fallback ASCII para el header + RFC 5987 para el nombre real (acentos, etc.)
        $fallback = trim(Str::ascii($name)) ?: ('archivo' . ($ext ? '.' . $ext : ''));
        $fallback = str_replace(['"', '\\', '/'], '_', $fallback);

        return response($contents, 200, [
            'Content-Type'        => $mime ?: 'application/octet-stream',
            'Content-Length'      => strlen($contents),
            'Content-Disposition' => "attachment; filename=\"{$fallback}\"; filename*=UTF-8''" . rawurlencode($name),
        ]);
    }
}
