<?php

namespace App\Support;

use App\Models\Organizador;

/**
 * Modelo sugerido de "Política de cancelamento e reembolso" do evento, já com os dados da
 * organização. É um ponto de partida editável pelo produtor, baseado no Código de Defesa
 * do Consumidor (Lei nº 8.078/1990) — NÃO é texto validado juridicamente.
 * Só cita o que a lei estabelece com clareza; prazos que a lei não fixa ficam de fora.
 */
class PoliticaCancelamento
{
    public static function modelo(Organizador $org): string
    {
        $documento = ($org->tipo_pessoa === 'PJ' ? 'CNPJ ' : 'CPF ').$org->documentoFormatado();
        $contatos = collect([
            $org->email_contato ? 'e-mail '.$org->email_contato : null,
            $org->whatsapp ? 'WhatsApp '.self::telefone($org->whatsapp) : null,
        ])->filter()->implode(' ou ');

        return <<<TXT
        Este evento é organizado e realizado por {$org->razao_social}, {$documento}, responsável pelo evento, pelo atendimento ao comprador e pelos reembolsos devidos.

        1. Direito de arrependimento: conforme o art. 49 do Código de Defesa do Consumidor (Lei nº 8.078/1990), a compra feita pela internet pode ser cancelada em até 7 (sete) dias corridos contados da data da compra, com devolução integral do valor pago, desde que o pedido seja feito antes do início do evento.

        2. Cancelamento do evento: se o evento for cancelado pelo organizador, todos os valores pagos serão devolvidos integralmente aos compradores, pelo mesmo meio de pagamento usado na compra, sob as penas das leis de defesa do consumidor.

        3. Alteração de data, horário ou local: em caso de alteração relevante, o comprador poderá escolher entre manter o ingresso para a nova data ou local ou pedir o reembolso integral do valor pago.

        4. Após o prazo de arrependimento: não há reembolso por desistência do comprador, ressalvados os casos previstos em lei. Quando o evento permitir, o ingresso poderá ser transferido para outra pessoa pela área "Meus ingressos", dentro do prazo indicado.

        5. Como solicitar: entre em contato pelo {$contatos}, informando o número do pedido.

        A plataforma Fora da Fila fornece apenas a tecnologia de venda e emissão dos ingressos e não é responsável pela realização do evento nem pelos reembolsos.
        TXT;
    }

    private static function telefone(string $digitos): string
    {
        $d = preg_replace('/\D/', '', $digitos);

        return strlen($d) === 11 ? sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7))
            : (strlen($d) === 10 ? sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6)) : $digitos);
    }
}
