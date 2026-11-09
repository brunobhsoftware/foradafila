<?php

namespace App\Support;

use App\Models\Evento;
use App\Models\Pedido;
use Illuminate\Support\Collection;

/**
 * PDF dos ingressos (uma página A4 por ingresso, com o QR Code), sem bibliotecas: PDF 1.4 escrito à mão,
 * fontes Helvetica padrão (WinAnsi) e o QR desenhado em retângulos (App\Support\QrCode).
 */
class IngressoPdf
{
    private const L = 595.28; // A4 em pontos

    private const A = 841.89;

    public static function gerar(Pedido $pedido, Evento $evento, Collection $ingressos): string
    {
        $paginas = [];
        foreach ($ingressos as $i) {
            $paginas[] = self::pagina($pedido, $evento, $i);
        }

        return self::documento($paginas);
    }

    private static function pagina(Pedido $pedido, Evento $evento, $ingresso): string
    {
        $s = '';
        // faixa da marca
        $s .= "0.063 0.094 0.157 rg 0 ".(self::A - 90)." ".self::L." 90 re f\n";
        $s .= self::texto(40, self::A - 55, 26, 'fora da fila', true, [1, 1, 1]);
        $s .= "0.949 0.361 0.020 rg 184 ".(self::A - 56)." 6 6 re f\n";
        $s .= self::texto(self::L - 200, self::A - 52, 11, 'INGRESSO', true, [1, 0.839, 0.039]);

        $y = self::A - 140;
        foreach (self::quebrar($evento->titulo, 34) as $linha) {
            $s .= self::texto(40, $y, 22, $linha, true);
            $y -= 28;
        }
        $y -= 4;
        $local = $evento->local ? trim($evento->local->nome.' — '.$evento->local->enderecoCurto(), ' —') : 'Evento online';
        foreach ([
            ['Data', data_local($evento->data_inicio, 'd/m/Y \à\s H:i')],
            ['Local', $local],
            ['Participante', $ingresso->participante?->nome ?? '—'],
            ['Ingresso', trim(($ingresso->tipo?->nome ?? '').' — '.($ingresso->lote?->nome ?? ''), ' —')],
            ['Número', $ingresso->numeroExibicao()],
            ['Pedido', $pedido->codigo],
        ] as [$rotulo, $valor]) {
            $s .= self::texto(40, $y, 9, mb_strtoupper($rotulo), true, [0.4, 0.44, 0.52]);
            $y -= 16;
            foreach (self::quebrar((string) $valor, 38) as $linha) {
                $s .= self::texto(40, $y, 13, $linha);
                $y -= 17;
            }
            $y -= 8;
        }

        // QR Code
        $m = QrCode::matriz($ingresso->conteudoQr());
        $n = count($m);
        $tam = 230;
        $mod = $tam / $n;
        $x0 = self::L - 40 - $tam;
        $y0 = self::A - 140 - $tam - 30;
        $s .= "1 1 1 rg\n0 g\n";
        foreach ($m as $r => $linha) {
            foreach ($linha as $c => $escuro) {
                if ($escuro) {
                    $s .= sprintf("%.2f %.2f %.3f %.3f re\n", $x0 + $c * $mod, $y0 + ($n - 1 - $r) * $mod, $mod + 0.02, $mod + 0.02);
                }
            }
        }
        $s .= "f\n";
        $s .= self::texto($x0, $y0 - 18, 9, 'Apresente este QR Code na entrada.', false, [0.4, 0.44, 0.52]);

        // rodapé
        $s .= "0.85 0.87 0.9 RG 40 150 m ".(self::L - 40)." 150 l S\n";
        $org = $evento->organizador;
        $rodape = [
            'Evento organizado por '.($org?->razao_social ?? $org?->nomeExibicao() ?? '').($org ? ' — '.$org->documentoFormatado() : '').'.',
            'O ingresso é pessoal: não compartilhe o QR Code. Cada código entra uma vez só.',
            'Ingresso emitido pelo Fora da Fila (foradafila.com.br), plataforma de tecnologia da BH Software.',
        ];
        $y = 130;
        foreach ($rodape as $l) {
            foreach (self::quebrar($l, 95) as $linha) {
                $s .= self::texto(40, $y, 9, $linha, false, [0.4, 0.44, 0.52]);
                $y -= 13;
            }
        }

        return $s;
    }

    private static function texto(float $x, float $y, float $tam, string $t, bool $negrito = false, array $cor = [0.063, 0.094, 0.157]): string
    {
        $t = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $t);
        $t = strtr((string) $t, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);

        return sprintf("BT %.3f %.3f %.3f rg /%s %.1f Tf %.2f %.2f Td (%s) Tj ET\n", $cor[0], $cor[1], $cor[2], $negrito ? 'F2' : 'F1', $tam, $x, $y, $t);
    }

    /** @return string[] */
    private static function quebrar(string $t, int $max): array
    {
        $linhas = explode("\n", wordwrap(trim($t), $max, "\n", true));

        return array_slice($linhas, 0, 3);
    }

    private static function documento(array $conteudos): string
    {
        $obj = [];
        $obj[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $obj[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $obj[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $kids = [];
        $n = 5;
        foreach ($conteudos as $c) {
            $z = gzcompress($c);
            $obj[$n] = '<< /Length '.strlen($z)." /Filter /FlateDecode >>\nstream\n".$z."\nendstream";
            $obj[$n + 1] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::L.' '.self::A.'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$n.' 0 R >>';
            $kids[] = ($n + 1).' 0 R';
            $n += 2;
        }
        $obj[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($obj);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($obj as $i => $o) {
            $offsets[$i] = strlen($pdf);
            $pdf .= "$i 0 obj\n$o\nendobj\n";
        }
        $xref = strlen($pdf);
        $total = max(array_keys($obj)) + 1;
        $pdf .= "xref\n0 $total\n0000000000 65535 f \n";
        for ($i = 1; $i < $total; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size $total /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";

        return $pdf;
    }
}
