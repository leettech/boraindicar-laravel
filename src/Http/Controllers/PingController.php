<?php

namespace BoraIndicar\Laravel\Http\Controllers;

use BoraIndicar\Laravel\Actions\PingHub;
use Illuminate\Http\RedirectResponse;

class PingController
{
    public function store(PingHub $ping): RedirectResponse
    {
        $result = $ping->handle();

        return redirect()->route('boraindicar.dashboard')->with(
            'boraindicar.flash',
            $result['ok'] ? 'Conexão com o hub OK.' : "Falha ao conectar no hub: {$result['error']}",
        );
    }
}
