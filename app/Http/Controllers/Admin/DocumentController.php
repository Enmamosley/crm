<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function store(Request $request, Client $client)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'document' => 'required|file|max:20480|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip',
        ]);

        $file = $request->file('document');
        $path = $file->store("clients/{$client->id}/documents", 'local');

        $client->documents()->create([
            'name'        => $request->name,
            'file_path'   => $path,
            'file_type'   => $file->getMimeType(),
            'file_size'   => $file->getSize(),
            'uploaded_by' => auth()->user()->name,
        ]);

        ActivityLog::log('document_uploaded', $client, "Documento '{$request->name}' subido para cliente '{$client->legal_name}'");

        return back()->with('success', 'Documento subido correctamente.');
    }

    public function download(Client $client, ClientDocument $document)
    {
        abort_if($document->client_id !== $client->id, 403);

        return response()->download(
            storage_path('app/' . $document->file_path),
            $document->name
        );
    }

    public function destroy(Client $client, ClientDocument $document)
    {
        abort_if($document->client_id !== $client->id, 403);

        \Storage::disk('local')->delete($document->file_path);
        $docName = $document->name;
        $document->delete();

        ActivityLog::log('document_deleted', $client, "Documento '{$docName}' eliminado para cliente '{$client->legal_name}'");

        return back()->with('success', 'Documento eliminado.');
    }
}
