<?php

namespace App\Http\Controllers;

use App\Pagamentos\MercadoPago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Avisos das operadoras. Grava tudo em webhook_evento e responde 200 rápido;
 * o processamento (consultar o pagamento na API e atualizar o pedido) entra com o checkout.
 */
class WebhookController extends Controller
{
    public function mercadoPago(Request $request)
    {
        $corpo = $request->json()->all() ?: $request->all();
        $dataId = (string) ($request->query('data_id') ?? data_get($corpo, 'data.id') ?? $request->query('id') ?? '');
        $requestId = $request->header('x-request-id');
        $origem = MercadoPago::origemAssinatura($request->header('x-signature'), $requestId, $dataId);
        $valida = $origem !== null;
        $idExterno = (string) (data_get($corpo, 'id') ?: ($requestId ?: sha1($request->getContent())));

        $cabecalhos = collect($request->headers->all())->only(['x-signature', 'x-request-id', 'user-agent', 'content-type'])
            ->map(fn ($v) => $v[0] ?? null);

        DB::table('webhook_evento')->insertOrIgnore([
            'gateway_id' => DB::table('gateway')->where('codigo', 'mercadopago')->value('id'),
            'id_evento_externo' => mb_substr($idExterno, 0, 150),
            'tipo' => mb_substr((string) (data_get($corpo, 'type') ?? $request->query('type') ?? $request->query('topic') ?? ''), 0, 60) ?: null,
            'acao' => mb_substr((string) data_get($corpo, 'action', ''), 0, 60) ?: null,
            'recurso_id' => $dataId !== '' ? mb_substr($dataId, 0, 100) : null,
            'request_id' => $requestId ? mb_substr($requestId, 0, 100) : null,
            'cabecalhos' => json_encode($cabecalhos),
            'corpo' => json_encode($corpo ?: new \stdClass),
            'assinatura_valida' => $valida ? 1 : 0,
            'status' => $valida ? 'recebido' : 'ignorado',
            'erro' => $valida ? ($origem === 'teste' ? 'Assinatura de teste' : null) : 'Assinatura ausente ou inválida',
        ]);

        // 200 mesmo com assinatura inválida (só é registrado e ignorado), para não revelar a conferência.
        return response()->json(['ok' => true]);
    }
}
