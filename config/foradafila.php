<?php

/*
| Decisões de negócio que podem mudar sem mexer no código.
*/
return [
    // O próprio comprador pode cancelar uma inscrição GRATUITA até o início do evento
    // (o ingresso deixa de valer e a vaga volta ao lote). Decisão provisória do piloto:
    // desligue com FF_COMPRADOR_CANCELA_GRATUITO=false no .env para exigir contato com o organizador.
    'comprador_cancela_gratuito' => env('FF_COMPRADOR_CANCELA_GRATUITO', true),
];
