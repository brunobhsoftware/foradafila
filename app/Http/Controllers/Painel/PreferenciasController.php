<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Organizador;
use App\Models\OrganizadorUsuario;
use App\Support\Auditoria;
use App\Support\Esquema;
use App\Support\Tenant;
use Illuminate\Http\Request;

/**
 * Preferências de notificação de cada pessoa da equipe, nesta organização (V004):
 *  - avisar por e-mail a cada compra/inscrição nos eventos que a pessoa pode ver;
 *  - receber novidades do Fora da Fila por e-mail (consentimento LGPD, com data).
 * Mostra também "Mostrar eventos passados", que é da organização (o mesmo campo da tela
 * Organização): só quem pode editar a organização consegue alterar.
 */
class PreferenciasController extends Controller
{
    public function edit(Tenant $tenant)
    {
        return view('painel.preferencias', [
            'vinculo' => OrganizadorUsuario::findOrFail($tenant->vinculo()->id),
            'org' => Organizador::findOrFail($tenant->id()),
            'podeEditarOrg' => $tenant->pode('organizador.editar'),
            'temV004' => Esquema::temV004(),
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        abort_unless(Esquema::temV004(), 409, 'Importe o script banco/V004 antes de salvar as preferências.');
        $vinculo = OrganizadorUsuario::findOrFail($tenant->vinculo()->id);
        $antes = $vinculo->getAttributes();

        $querNovidades = $request->boolean('receber_novidades');
        $vinculo->notificar_compra = $request->boolean('notificar_compra');
        // Guarda a data do consentimento; desmarcar revoga (NULL).
        $vinculo->receber_novidades_em = $querNovidades ? ($vinculo->receber_novidades_em ?? now()) : null;
        $vinculo->save();
        Auditoria::registrar('preferencias.alterar', $vinculo, $antes);

        if ($tenant->pode('organizador.editar') && $request->has('mostrar_eventos_passados')) {
            $org = Organizador::findOrFail($tenant->id());
            $antesOrg = $org->getAttributes();
            $org->mostrar_eventos_passados = $request->boolean('mostrar_eventos_passados');
            if ($org->isDirty()) {
                $org->save();
                Auditoria::registrar('organizador.mostrar_eventos_passados', $org, $antesOrg);
            }
        }
        if ($tenant->pode('organizador.editar') && $request->has('copia_ingressos') && $request->boolean('copia_ingressos') !== \App\Support\CopiaIngressos::ativa($tenant->id())) {
            \App\Support\CopiaIngressos::definir($tenant->id(), $request->boolean('copia_ingressos'));
            Auditoria::registrar('organizador.copia_ingressos', Organizador::findOrFail($tenant->id()), null, ['ativa' => $request->boolean('copia_ingressos')]);
        }

        return back()->with('ok', 'Preferências salvas.');
    }
}
