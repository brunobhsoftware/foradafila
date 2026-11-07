@if (session('ok'))
    <div class="alerta alerta--ok" role="status"><p>{{ session('ok') }}</p></div>
@endif
@if (session('erro'))
    <div class="alerta alerta--erro" role="alert"><p>{{ session('erro') }}</p></div>
@endif
@if ($errors->any())
    <div class="alerta alerta--erro" role="alert">
        <div>
            <b>Confira o que precisa ser corrigido:</b>
            <ul>
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
