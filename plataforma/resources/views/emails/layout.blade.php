{{-- Layout dos e-mails do Fora da Fila. Tabelas e estilos inline: clientes de e-mail não leem CSS externo. --}}
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $titulo }}</title></head>
<body style="margin:0;padding:0;background:#F7F7F8;font-family:Arial,Helvetica,sans-serif;color:#344054">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F7F8;padding:24px 12px">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #E4E7EC;border-radius:14px;overflow:hidden">
        <tr><td style="background:#101828;padding:22px 28px">
          <span style="font-size:22px;font-weight:bold;color:#ffffff;letter-spacing:-0.5px">fora da fila<span style="color:#F25C05">.</span></span>
        </td></tr>
        <tr><td style="padding:28px">
          <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#101828">{{ $titulo }}</h1>
          <div style="font-size:15px;line-height:1.6">{!! $blocos !!}</div>
          @if ($botaoTexto && $botaoUrl)
            <p style="margin:24px 0 8px"><a href="{{ $botaoUrl }}" style="display:inline-block;background:#FFD60A;color:#101828;text-decoration:none;font-weight:bold;padding:13px 24px;border-radius:99px">{{ $botaoTexto }}</a></p>
            <p style="margin:0;font-size:12px;color:#667085">Se o botão não abrir, copie este endereço: {{ $botaoUrl }}</p>
          @endif
        </td></tr>
        <tr><td style="padding:18px 28px;background:#FCFCFD;border-top:1px solid #E4E7EC;font-size:12px;line-height:1.5;color:#667085">
          {!! $rodape ?? 'Fora da Fila — plataforma de tecnologia da BH Software para eventos e ingressos.' !!}<br>
          <a href="{{ route('inicio') }}" style="color:#F25C05">foradafila.com.br</a>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
