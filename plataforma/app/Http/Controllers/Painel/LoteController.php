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
            'status' => Carbon::parse($dados['venda_inicio'], 'UTC')->isPast() ? 'ativo' : 'agendado',
        ]), 'lote');
        Auditoria::registrar('lote.criar', $lote);

        return back()->with('ok', "Lote “{$lote->nome}” criado.");
    }

    public function update(Request $request, Lote $lote)
    {
        $evento = $lote->evento;
        $this->autorizar($evento);
        $dados = $this->validarLote($request, $evento, $lote);
        if ($request->filled('status')) {
            $dados['status'] = $request->validate(['status' => [Rule::in(['agendado', 'ativo', 'pausado', 'encerrado'])]])['status'];
        }

        $antes = $lote->getAttributes();
        // trg_lote_bu recusa mudar o preço de lote com venda ou reserva.
        RegraDoBanco::executar(fn () => $lote->update($dados), 'lote');
        Auditoria::registrar($antes['preco'] != $lote->preco ? 'lote.alterar_preco' : 'lote.editar', $lote, $antes);

        return back()->with('ok', "Lote “{$lote->nome}” atualizado.");
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
        $dados = $request->validate([
            'ingresso_tipo_id' => ['required', Rule::exists('ingresso_tipo', 'id')->where('evento_id', $evento->id)],
            'nome' => ['required', 'string', 'max:80'],
            'modalidade' => ['required', Rule::in(array_keys(Lote::MODALIDADES))],
            'preco' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'quantidade' => ['required', 'integer', 'min:1'],
            'min_por_pedido' => ['required', 'integer', 'min:1'],
            'max_por_pedido' => ['required', 'integer', 'gte:min_por_pedido'],
            'venda_inicio' => ['required', 'date'],
            'venda_fim' => ['required', 'date', 'after:venda_inicio'],
            'virada' => ['required', Rule::in(array_keys(Lote::VIRADAS))],
            'visibilidade' => ['required', Rule::in(array_keys(Lote::VISIBILIDADES))],
            'lote_anterior_id' => ['nullable', Rule::exists('lote', 'id')->where('evento_id', $evento->id)],
        ], [
            'max_por_pedido.gte' => 'O máximo por pedido deve ser maior ou igual ao mínimo.',
        ], [
            'ingresso_tipo_id' => 'tipo de ingresso', 'venda_inicio' => 'início das vendas', 'venda_fim' => 'fim das vendas',
            'min_por_pedido' => 'mínimo por pedido', 'max_por_pedido' => 'máximo por pedido',
        ]);

        if ($dados['modalidade'] === 'cortesia') {
            $dados['preco'] = 0;
        }
        if ($lote && $dados['lote_anterior_id'] == $lote->id) {
            $dados['lote_anterior_id'] = null;
        }
        $dados['venda_inicio'] = utc_de_local($dados['venda_inicio']);
        $dados['venda_fim'] = utc_de_local($dados['venda_fim']);

        $erros = [];
        if (Carbon::parse($dados['venda_fim'], 'UTC')->gt($evento->data_inicio)) {
            $erros['venda_fim'] = 'As vendas do lote precisam terminar até o início do evento ('.data_local($evento->data_inicio).').';
        }
        $outros = (int) $evento->lotes()->when($lote, fn ($q) => $q->where('id', '<>', $lote->id))->sum('quantidade');
        if ($outros + $dados['quantidade'] > $evento->capacidade) {
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
