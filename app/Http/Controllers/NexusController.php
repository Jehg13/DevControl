<?php

namespace App\Http\Controllers;

use App\Services\NexusAuditService;
use Illuminate\Http\Request;
use Throwable;

class NexusController extends Controller
{
    public function findings(Request $request, NexusAuditService $scanner)
    {
        $data = $request->validate([
            'proyecto_id' => ['nullable', 'integer', 'min:1'],
            'tipo' => ['nullable', 'string', 'max:40'],
        ]);
        try {
            return response()->json([
                'hallazgos' => $scanner->findings($data['proyecto_id'] ?? null, $data['tipo'] ?? null),
                'salud' => $scanner->health(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'No se pudieron consultar los hallazgos.'], 503);
        }
    }

    public function health(NexusAuditService $scanner)
    {
        return response()->json($scanner->health());
    }

    public function proposals(Request $request, NexusAuditService $scanner)
    {
        $data = $request->validate([
            'proyecto_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            return response()->json([
                'propuestas' => $scanner->proposals($data['proyecto_id'] ?? null),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'No se pudieron generar propuestas.'], 503);
        }
    }
}
