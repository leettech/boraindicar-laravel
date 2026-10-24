@extends('boraindicar::layout')

@section('title', 'boraindicar — painel')

@section('content')
    @if (session('boraindicar.flash'))
        <div class="flash">{{ session('boraindicar.flash') }}</div>
    @endif

    @unless ($enabled)
        <div class="warning">Integração desativada: defina <span class="mono">BORAINDICAR_SECRET</span> (ou <span class="mono">BORAINDICAR_ENABLED=true</span>) no .env. Nada é gravado nem enviado ao hub.</div>
    @endunless

    @include('boraindicar::partials.status')
    @include('boraindicar::partials.events')
    @include('boraindicar::partials.referrals')
@endsection
