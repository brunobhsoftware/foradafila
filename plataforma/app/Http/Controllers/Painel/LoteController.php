<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\IngressoTipo;
use App\Models\Lote;
use App\Support\Auditoria;
use App\Support\RegraDoBanco;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Tipos de ingresso (setores) e lotes de um evento. */
class LoteController extends Controller
{
    // ---------- Tipos de ingresso ----------

    public function storeTipo(Request $request, Evento $evento)
    {
        $this->autorizar($evento);
        $dados = $this->validarTipo($request);
        $tipo = $evento->tipos()->create($dados + ['ordem' => $evento->tipos()->count()]);
        Auditoria::registrar('ingresso_tipo.criar', $tipo);

        return back()->with('ok', "Tipo de ingresso “{$tipo->nome}” criado. Agora adicione os lotes dele.");
    }

    public function updateTipo(Request $request, IngressoTipo $tipo)
    {
        $this->autorizar($tipo->evento);
        $antes = $tipo->getAttributes();
        $tipo->update($this->validarTipo($request) + ['ativo' => $request->boolean('ativo', true)]);
        Auditoria::registrar('ingresso_tipo.editar', $tipo, $antes);

        return back()->with('ok', 'Tipo de ingresso atualizado.');
    }

    public function destroyTipo(IngressoTipo $tipo)
    {
        $this->autorizar($tipo->evento);
        if ($tipo->lotes()->exists()) {
            throw ValidationException::withMessages(['tipo' => 'Remova os lotes deste tipo antes de excluí-lo.']);
        }
        Auditoria::registrar('ingresso_tipo.excluir', $tipo);
        $tipo->delete();

        return back()->with('ok', 'Tipo de ingresso excluído.');
    }

    // ---------- Lotes ----------

    public function store(Request $request, Evento $evento)
    {
        $this->autorizar($evento);
        $dados = $this->validarLote($request, $evento);

        $lote = RegraDoBanco::executar(fn () => $evento->lotes()->create($dados + [
            'ordem' => $evento->lotes()->count(),
            'status' => $request->boolean('ativo', true) ? self::statusAtivo($dados['venda_inicio']) : 'pausado',
        ]), 'lote');
        Auditoria::registrar('lote.criar', $lote);

        return back()->with('ok', "Lote “{$lote->nome}” criado.");
    }

    public function update(Request $request, Lote $lote)
    {
        $evento = $lote->evento;
        $this->autorizar($evento);
        $dados = $this->validarLote($request, $evento, $lote);
        if ($request->has('ativo')) {
            $dados['status'] = self::statusAoAtivar($lote, $request->boolean('ativo'), $dados['venda_inicio']);
        }

        $antes = $lote->getAttributes();
        // trg_lote_bu recusa mudar o preço de lote com venda ou reserva.
        RegraDoBanco::executar(fn () => $lote->update($dados), 'lote');
        Auditoria::registrar($antes['preco'] != $lote->preco ? 'lote.alterar_preco' : 'lote.editar', $lote, $antes);

        return back()->with('ok', "Lote “{$lote->nome}” atualizado.");
    }

    /** Botão "Ativar / Desativar" na lista de lotes. */
    public function alternar(Lote $lote)
    {
        $this->autorizar($lote->evento);
        $antes = $lote->getAttributes();
        $ativar = $lote->status === 'pausado';
        $lote->update(['status' => self::statusAoAtivar($lote, $ativar, $lote->venda_inicio)]);
        Auditoria::registrar($ativar ? 'lote.ativar' : 'lote.desativar', $lote, $antes);

        return back()->with('ok', "Lote “{$lote->nome}” ".($ativar ? 'ativado.' : 'desativado: não aparece nem é vendido.'));
    }

    public function destroy(Lote $lote)
    {
        $this->autorizar($lote->evento);
        if ($lote->temMovimento()) {
            throw ValidationException::withMessages(['lote' => 'Lote com vendas ou reservas não pode ser excluído. Encerre o lote.']);
        }
        Auditoria::registrar('lote.excluir', $lote);
        $lote->delete();

        return back()->with('ok', 'Lote excluído.');
    }

    // ---------- Apoio ----------

    private static function statusAtivo($vendaInicio): string
    {
        return Carbon::parse($vendaInicio, 'UTC')->isPast() ? 'ativo' : 'agendado';
    }

    /** Desativar = "pausado". Ativar volta para à venda/agendado; esgotado e encerrado só mudam se desativados. */
    private static function statusAoAtivar(Lote $lote, bool $ativo, $vendaInicio): string
    {
        if (! $ativo) {
            return 'pausado';
        }
        if (in_array($lote->status, ['esgotado', 'encerrado'], true)) {
            return $lote->status;
        }

        return self::statusAtivo($vendaInicio);
    }

    private function autorizar(Evento $evento): void
    {
        Gate::authorize('lote.gerenciar');
        abort_unless(Evento::visiveis()->whereKey($evento->id)->exists(), 404);
        abort_if(in_array($evento->status, ['encerrado', 'cancelado']), 403, 'Evento encerrado ou cancelado.');
    }

    private function validarTipo(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:80'],
            'descricao' => ['nullable', 'string', 'max:500'],
            'capacidade' => ['nullable', 'integer', 'min:1'],
        ], [], ['nome' => 'nome do tipo']);
    }

    private function validarLote(Request $request, Evento $evento, ?Lote $lote = null): array
    {
        $idsOutros = $evento->lotes()->when($lote, fn ($q) => $q->where('id', '<>', $lote->id))->pluck('id')->all();
        $dados = $request->validate([
            'ingresso_tipo_id' => ['required', Rule::exists('ingresso_tipo', 'id')->where('evento_id', $evento->id)],
            'nome' => ['required', 'string', 'max:80'],
            'cobranca' => ['required', Rule::in(['gratuito', 'pago'])],
            'preco' => ['exclude_unless:cobranca,pago', 'required', 'numeric', 'min:0.01', 'max:1000000'],
            'modalidade' => ['exclude_unless:cobranca,pago', 'required', Rule::in(['inteira', 'meia'])],
            'quantidade' => ['required', 'integer', 'min:1'],
            'min_por_pedido' => ['required', 'integer', 'min:1'],
            'max_por_pedido' => ['required', 'integer', 'gte:min_por_pedido'],
            'liberacao' => ['required', Rule::in(['por_data', 'por_lote'])],
            'venda_inicio' => ['exclude_unless:liberacao,por_data', 'required', 'date'],
            'lote_anterior_id' => ['exclude_unless:liberacao,por_lote', 'required', Rule::in($idsOutros)],
            'venda_fim' => ['required', 'date'],
            'visibilidade' => ['required', Rule::in(array_keys(Lote::VISIBILIDADES))],
        ], [
            'max_por_pedido.gte' => 'O máximo por pedido deve ser maior ou igual ao mínimo.',
            'preco.required' => 'Informe o valor do ingresso pago.',
            'preco.min' => 'O valor do ingresso pago precisa ser maior que zero.',
            'lote_anterior_id.required' => 'Escolha depois de qual lote as vendas começam.',
            'lote_anterior_id.in' => 'Escolha um lote anterior deste evento.',
        ], [
            'ingresso_tipo_id' => 'tipo de ingresso', 'venda_inicio' => 'início das vendas', 'venda_fim' => 'fim das vendas',
            'min_por_pedido' => 'mínimo por pedido', 'max_por_pedido' => 'máximo por pedido', 'preco' => 'valor',
        ]);

        // Gratuito: preço zero (a cortesia antiga continua cortesia).
        if ($dados['cobranca'] === 'gratuito') {
            $dados['preco'] = 0;
            $dados['modalidade'] = $lote?->modalidade === 'cortesia' ? 'cortesia' : 'inteira';
        }
        // Com vendas, o preço não muda (o trigger também recusa).
        if ($lote && $lote->temMovimento()) {
            $dados['preco'] = $lote->preco;
        }
        unset($dados['cobranca']);

        // Por lote: começa quando o anterior acabar; a data de início fica "já" (ou a que já tinha).
        if ($dados['liberacao'] === 'por_lote') {
            $dados['venda_inicio'] = $lote?->venda_inicio && $lote->lote_anterior_id ? $lote->venda_inicio->format('Y-m-d H:i:s') : now('UTC')->format('Y-m-d H:i:s');
        } else {
            $dados['lote_anterior_id'] = null;
            $dados['venda_inicio'] = utc_de_local($dados['venda_inicio']);
        }
        unset($dados['liberacao']);
        $dados['venda_fim'] = utc_de_local($dados['venda_fim']);

        $erros = [];
        if ($lote && $dados['lote_anterior_id'] && Lote::whereKey($dados['lote_anterior_id'])->value('lote_anterior_id') == $lote->id) {
            $erros['lote_anterior_id'] = 'Esse lote já começa depois deste: escolha outro lote anterior.';
        }
        if (! Carbon::parse($dados['venda_fim'], 'UTC')->gt(Carbon::parse($dados['venda_inicio'], 'UTC'))) {
            $erros['venda_fim'] = 'O fim das vendas precisa ser depois do início das vendas.';
        }
        if (Carbon::parse($dados['venda_fim'], 'UTC')->gt($evento->data_inicio)) {
            $erros['venda_fim'] = 'As vendas do lote precisam terminar até o início do evento ('.data_local($evento->data_inicio).').';
        }
        $outros = (int) $evento->lotes()->when($lote, fn ($q) => $q->where('id', '<>', $lote->id))->sum('quantidade');
        if ($evento->capacidade !== null && $outros + $dados['quantidade'] > $evento->capacidade) {
            $erros['quantidade'] = 'A soma dos lotes ('.numero($outros + $dados['quantidade']).') passa da capacidade do evento ('.numero($evento->capacidade).').';
        }
        if ($lote && $dados['quantidade'] < $lote->vendido + $lote->reservado) {
            $erros['quantidade'] = 'A quantidade não pode ser menor que o já vendido/reservado ('.($lote->vendido + $lote->reservado).').';
        }
        if ($erros) {
            throw ValidationException::withMessages($erros);
        }

        return $dados;
    }
}
