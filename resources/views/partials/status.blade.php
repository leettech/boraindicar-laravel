<section class="card">
    <div class="card-head">
        <h2>Conexão com o hub</h2>
        <div class="actions">
            <form method="POST" action="{{ route('boraindicar.ping') }}">
                @csrf
                <button type="submit" class="btn">Testar conexão</button>
            </form>
        </div>
    </div>
    <div class="card-body conn">
        @if ($lastPing === null)
            <span class="dot"></span>
            <span class="muted">Nenhum teste ainda. Clique em "Testar conexão" ou rode <span class="mono">php artisan boraindicar:ping</span>.</span>
        @elseif ($lastPing['ok'])
            <span class="dot ok"></span>
            <strong>Conectado</strong>
            <span class="muted">ao produto {{ $lastPing['product']['name'] ?? '?' }} (<span class="mono">{{ $lastPing['product']['slug'] ?? '?' }}</span>)</span>
            <span class="muted">· última conexão {{ \BoraIndicar\Laravel\Support\RelativeTime::of($lastPing['at']) }}</span>
        @else
            <span class="dot error"></span>
            <strong>Sem conexão</strong>
            <span class="error-text" style="margin:0">{{ $lastPing['error'] }}</span>
            <span class="muted">· último teste {{ \BoraIndicar\Laravel\Support\RelativeTime::of($lastPing['at']) }}</span>
        @endif
    </div>
</section>

<div class="stats">
    @foreach (\BoraIndicar\Laravel\Enums\EventStatus::cases() as $case)
        <a class="card stat" href="{{ route('boraindicar.dashboard', ['status' => $case->value]) }}" style="color:inherit;text-decoration:none">
            <div class="label">{{ $case->pluralLabel() }}</div>
            <div class="value {{ $case->value }}">{{ number_format($counts[$case->value], 0, ',', '.') }}</div>
        </a>
    @endforeach
</div>
