<?php

namespace App\Support;

use RuntimeException;

/**
 * Gerador de QR Code (modo byte, correção M, versões 1 a 10) para o PDF do ingresso, sem dependências.
 * Segue a mesma lógica do qrcode-generator 1.4.4 (Kazuhiko Arase, MIT) usado no navegador, então o QR do PDF
 * é idêntico ao da segunda via. O conteúdo do ingresso (FF1.<43>.<16>, 64 caracteres) cabe na versão 5.
 */
class QrCode
{
    /** [blocos, total, dados] por versão, nível M. */
    private const BLOCOS = [
        1 => [[1, 26, 16]], 2 => [[1, 44, 28]], 3 => [[1, 70, 44]], 4 => [[2, 50, 32]], 5 => [[2, 67, 43]],
        6 => [[4, 43, 27]], 7 => [[4, 49, 31]], 8 => [[2, 60, 38], [2, 61, 39]], 9 => [[3, 58, 36], [2, 59, 37]],
        10 => [[4, 69, 43], [1, 70, 44]],
    ];

    private const POSICOES = [1 => [], [6, 18], [6, 22], [6, 26], [6, 30], [6, 34], [6, 22, 38], [6, 24, 42], [6, 26, 46], [6, 28, 50]];

    private const NIVEL_M = 0;

    private static array $exp = [];

    private static array $log = [];

    private int $versao;

    private int $n;

    private array $m = [];

    private array $dados;

    /** @return bool[][] matriz [linha][coluna], true = módulo escuro */
    public static function matriz(string $texto): array
    {
        return (new self($texto))->m;
    }

    private function __construct(string $texto)
    {
        self::tabelas();
        $bytes = array_values(unpack('C*', $texto) ?: []);
        foreach (self::BLOCOS as $v => $blocos) {
            $cap = 0;
            foreach ($blocos as [$q, , $d]) {
                $cap += $q * $d;
            }
            if (4 + ($v < 10 ? 8 : 16) + 8 * count($bytes) <= $cap * 8) {
                $this->versao = $v;
                break;
            }
        }
        if (! isset($this->versao)) {
            throw new RuntimeException('Conteúdo grande demais para o QR Code.');
        }
        $this->n = $this->versao * 4 + 17;
        $this->dados = $this->criarDados($bytes);

        $melhor = 0;
        $menor = null;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->montar(true, $mask);
            $p = $this->penalidade();
            if ($menor === null || $p < $menor) {
                $menor = $p;
                $melhor = $mask;
            }
        }
        $this->montar(false, $melhor);
    }

    private static function tabelas(): void
    {
        if (self::$exp) {
            return;
        }
        for ($i = 0; $i < 8; $i++) {
            self::$exp[$i] = 1 << $i;
        }
        for ($i = 8; $i < 256; $i++) {
            self::$exp[$i] = self::$exp[$i - 4] ^ self::$exp[$i - 5] ^ self::$exp[$i - 6] ^ self::$exp[$i - 8];
        }
        for ($i = 0; $i < 255; $i++) {
            self::$log[self::$exp[$i]] = $i;
        }
    }

    private static function gexp(int $n): int
    {
        return self::$exp[(($n % 255) + 255) % 255];
    }

    /** Polinômio: remove zeros à esquerda e acrescenta $shift zeros. */
    private static function poli(array $num, int $shift = 0): array
    {
        $o = 0;
        while ($o < count($num) - 1 && $num[$o] === 0) {
            $o++;
        }
        return array_merge(array_slice($num, $o), array_fill(0, $shift, 0));
    }

    private static function multiplicar(array $a, array $b): array
    {
        $r = array_fill(0, count($a) + count($b) - 1, 0);
        foreach ($a as $i => $x) {
            foreach ($b as $j => $y) {
                if ($x && $y) {
                    $r[$i + $j] ^= self::gexp(self::$log[$x] + self::$log[$y]);
                }
            }
        }
        return self::poli($r);
    }

    private static function resto(array $a, array $e): array
    {
        while (count($a) - count($e) >= 0) {
            if ($a[0] === 0) {
                $a = self::poli(array_slice($a, 1) ?: [0]);
                if (count($a) < count($e)) {
                    break;
                }
                continue;
            }
            $razao = self::$log[$a[0]] - self::$log[$e[0]];
            foreach ($e as $i => $c) {
                if ($c) {
                    $a[$i] ^= self::gexp(self::$log[$c] + $razao);
                }
            }
            $a = self::poli($a);
            if (count($a) === 1 && $a[0] === 0) {
                break;
            }
        }
        return $a;
    }

    private function criarDados(array $bytes): array
    {
        $bits = [];
        $por = function (int $valor, int $tam) use (&$bits) {
            for ($i = $tam - 1; $i >= 0; $i--) {
                $bits[] = ($valor >> $i) & 1;
            }
        };
        $por(4, 4);
        $por(count($bytes), $this->versao < 10 ? 8 : 16);
        foreach ($bytes as $b) {
            $por($b, 8);
        }
        $blocos = [];
        foreach (self::BLOCOS[$this->versao] as [$q, $t, $d]) {
            for ($i = 0; $i < $q; $i++) {
                $blocos[] = [$t, $d];
            }
        }
        $totalDados = array_sum(array_column($blocos, 1));
        if (count($bits) + 4 <= $totalDados * 8) {
            $por(0, 4);
        }
        while (count($bits) % 8) {
            $bits[] = 0;
        }
        $buf = [];
        foreach (array_chunk($bits, 8) as $oct) {
            $buf[] = bindec(implode('', $oct));
        }
        for ($pad = 0; count($buf) < $totalDados; $pad ^= 1) {
            $buf[] = $pad ? 0x11 : 0xEC;
        }

        $dc = [];
        $ec = [];
        $off = 0;
        foreach ($blocos as $k => [$t, $d]) {
            $dc[$k] = array_slice($buf, $off, $d);
            $off += $d;
            $nEc = $t - $d;
            $gerador = [1];
            for ($i = 0; $i < $nEc; $i++) {
                $gerador = self::multiplicar($gerador, [1, self::gexp($i)]);
            }
            $mod = self::resto(self::poli($dc[$k], count($gerador) - 1), $gerador);
            $ec[$k] = [];
            for ($i = 0; $i < count($gerador) - 1; $i++) {
                $idx = $i + count($mod) - (count($gerador) - 1);
                $ec[$k][$i] = $idx >= 0 ? $mod[$idx] : 0;
            }
        }
        $saida = [];
        foreach ([$dc, $ec] as $grupo) {
            $max = max(array_map('count', $grupo));
            for ($i = 0; $i < $max; $i++) {
                foreach ($grupo as $b) {
                    if ($i < count($b)) {
                        $saida[] = $b[$i];
                    }
                }
            }
        }
        return $saida;
    }

    private function montar(bool $teste, int $mask): void
    {
        $n = $this->n;
        $this->m = array_fill(0, $n, array_fill(0, $n, null));
        foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$lr, $lc]) {
            for ($r = -1; $r <= 7; $r++) {
                for ($c = -1; $c <= 7; $c++) {
                    if ($lr + $r < 0 || $lr + $r >= $n || $lc + $c < 0 || $lc + $c >= $n) {
                        continue;
                    }
                    $this->m[$lr + $r][$lc + $c] = ($r >= 0 && $r <= 6 && ($c === 0 || $c === 6)) || ($c >= 0 && $c <= 6 && ($r === 0 || $r === 6))
                        || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4);
                }
            }
        }
        $pos = self::POSICOES[$this->versao];
        foreach ($pos as $row) {
            foreach ($pos as $col) {
                if ($this->m[$row][$col] !== null) {
                    continue;
                }
                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        $this->m[$row + $r][$col + $c] = $r === -2 || $r === 2 || $c === -2 || $c === 2 || ($r === 0 && $c === 0);
                    }
                }
            }
        }
        for ($i = 8; $i < $n - 8; $i++) {
            $this->m[$i][6] ??= ($i % 2 === 0);
            $this->m[6][$i] ??= ($i % 2 === 0);
        }
        // formato
        $bits = self::bchFormato((self::NIVEL_M << 3) | $mask);
        for ($i = 0; $i < 15; $i++) {
            $v = ! $teste && (($bits >> $i) & 1) === 1;
            if ($i < 6) {
                $this->m[$i][8] = $v;
            } elseif ($i < 8) {
                $this->m[$i + 1][8] = $v;
            } else {
                $this->m[$n - 15 + $i][8] = $v;
            }
            if ($i < 8) {
                $this->m[8][$n - $i - 1] = $v;
            } elseif ($i < 9) {
                $this->m[8][15 - $i] = $v;
            } else {
                $this->m[8][15 - $i - 1] = $v;
            }
        }
        $this->m[$n - 8][8] = ! $teste;
        if ($this->versao >= 7) {
            $bits = self::bchVersao($this->versao);
            for ($i = 0; $i < 18; $i++) {
                $v = ! $teste && (($bits >> $i) & 1) === 1;
                $this->m[intdiv($i, 3)][$i % 3 + $n - 11] = $v;
                $this->m[$i % 3 + $n - 11][intdiv($i, 3)] = $v;
            }
        }
        // dados
        $inc = -1;
        $row = $n - 1;
        $bit = 7;
        $byte = 0;
        for ($col = $n - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--;
            }
            while (true) {
                for ($c = 0; $c < 2; $c++) {
                    if ($this->m[$row][$col - $c] === null) {
                        $escuro = $byte < count($this->dados) && (($this->dados[$byte] >> $bit) & 1) === 1;
                        if (self::mascara($mask, $row, $col - $c)) {
                            $escuro = ! $escuro;
                        }
                        $this->m[$row][$col - $c] = $escuro;
                        if (--$bit === -1) {
                            $byte++;
                            $bit = 7;
                        }
                    }
                }
                $row += $inc;
                if ($row < 0 || $row >= $n) {
                    $row -= $inc;
                    $inc = -$inc;
                    break;
                }
            }
        }
    }

    private static function mascara(int $p, int $i, int $j): bool
    {
        return match ($p) {
            0 => ($i + $j) % 2 === 0,
            1 => $i % 2 === 0,
            2 => $j % 3 === 0,
            3 => ($i + $j) % 3 === 0,
            4 => (intdiv($i, 2) + intdiv($j, 3)) % 2 === 0,
            5 => ($i * $j) % 2 + ($i * $j) % 3 === 0,
            6 => (($i * $j) % 2 + ($i * $j) % 3) % 2 === 0,
            default => (($i * $j) % 3 + ($i + $j) % 2) % 2 === 0,
        };
    }

    private static function digitos(int $d): int
    {
        $n = 0;
        while ($d !== 0) {
            $n++;
            $d >>= 1;
        }
        return $n;
    }

    private static function bchFormato(int $dado): int
    {
        $g = 0b10100110111;
        $d = $dado << 10;
        while (self::digitos($d) - self::digitos($g) >= 0) {
            $d ^= $g << (self::digitos($d) - self::digitos($g));
        }
        return (($dado << 10) | $d) ^ 0b101010000010010;
    }

    private static function bchVersao(int $dado): int
    {
        $g = 0b1111100100101;
        $d = $dado << 12;
        while (self::digitos($d) - self::digitos($g) >= 0) {
            $d ^= $g << (self::digitos($d) - self::digitos($g));
        }
        return ($dado << 12) | $d;
    }

    private function penalidade(): float
    {
        $n = $this->n;
        $m = $this->m;
        $p = 0;
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                $igual = 0;
                $e = $m[$r][$c];
                for ($dr = -1; $dr <= 1; $dr++) {
                    if ($r + $dr < 0 || $r + $dr >= $n) {
                        continue;
                    }
                    for ($dc = -1; $dc <= 1; $dc++) {
                        if ($c + $dc < 0 || $c + $dc >= $n || ($dr === 0 && $dc === 0)) {
                            continue;
                        }
                        if ($e === $m[$r + $dr][$c + $dc]) {
                            $igual++;
                        }
                    }
                }
                if ($igual > 5) {
                    $p += 3 + $igual - 5;
                }
            }
        }
        for ($r = 0; $r < $n - 1; $r++) {
            for ($c = 0; $c < $n - 1; $c++) {
                $q = (int) $m[$r][$c] + (int) $m[$r + 1][$c] + (int) $m[$r][$c + 1] + (int) $m[$r + 1][$c + 1];
                if ($q === 0 || $q === 4) {
                    $p += 3;
                }
            }
        }
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n - 6; $c++) {
                if ($m[$r][$c] && ! $m[$r][$c + 1] && $m[$r][$c + 2] && $m[$r][$c + 3] && $m[$r][$c + 4] && ! $m[$r][$c + 5] && $m[$r][$c + 6]) {
                    $p += 40;
                }
                if ($m[$c][$r] && ! $m[$c + 1][$r] && $m[$c + 2][$r] && $m[$c + 3][$r] && $m[$c + 4][$r] && ! $m[$c + 5][$r] && $m[$c + 6][$r]) {
                    $p += 40;
                }
            }
        }
        $escuros = 0;
        foreach ($m as $linha) {
            $escuros += count(array_filter($linha));
        }
        $p += abs(100 * $escuros / $n / $n - 50) / 5 * 10;

        return $p;
    }
}
