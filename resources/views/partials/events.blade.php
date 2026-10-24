<section class="card">
    <div class="card-head">
        <h2>Eventos enviados ao hub</h2>
        <div class="actions">
            <form method="GET" action="{{ route('boraindicar.dashboard') }}" style="display:flex;gap:8px">
                <select name="status" onchange="this.form.submit()" aria-label="Status">
                    <option value="">Todos os status</option>
                    @foreach (\BoraIndicar\Laravel\Enums\EventStatus::cases() as $case)
                        <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
                    @endforeach
                </select>
                <select name="type" onchange="this.form.submit()" aria-label="Tipo">
                    <option value="">Todos os tipos</option>
                    @foreach (\BoraIndicar\Laravel\Enums\EventType::cases() as $case)
                        <option value="{{ $case->value }}" @selected($type === $case)>{{ $case->label() }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="btn">Filtrar</button></noscript>
            </form>
            @if ($counts['failed'] > 0)
                <form method="POST" action="{{ route('boraindicar.events.retry-failed') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Reenviar todos com falha</button>
                </form>
            @endif
        </div>
    </div>

    @if ($events->isEmpty())
        <div class="empty">Nenhum evento{{ $status || $type ? ' com esse filtro' : ' ainda' }}.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tipo</th>
                        <th>Chave</th>
                        <th>Status</th>
                        <th class="num">Tentativas</th>
                        <th>Criado</th>
                        <th>Enviado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($events as $event)
                        <tr>
                            <td class="mono muted">{{ $event->id }}</td>
                            <td><span class="badge badge-type">{{ $event->type->label() }}</span></td>
                            <td>
                                <div class="mono">{{ $event->idempotency_key }}</div>
                                <details>
                                    <summary>payload</summary>
                                    <pre>{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                </details>
                            </td>
                            <td>
                                <span class="badge {{ $event->status->badge() }}">{{ $event->status->label() }}</span>
                                @if ($event->last_error)
                                    <div class="error-text">{{ $event->last_error }}</div>
                                @endif
                            </td>
                            <td class="num">{{ $event->attempts }}</td>
                            <td class="muted when" title="{{ $event->created_at }}">{{ \BoraIndicar\Laravel\Support\RelativeTime::of($event->created_at) }}</td>
                            <td class="muted when" title="{{ $event->sent_at }}">{{ \BoraIndicar\Laravel\Support\RelativeTime::of($event->sent_at) ?? '—' }}</td>
                            <td>
                                @if ($event->status === \BoraIndicar\Laravel\Enums\EventStatus::Failed)
                                    <form method="POST" action="{{ route('boraindicar.events.retry', $event) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm">Reenviar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($events->hasPages())
            <div class="pagination">
                @if ($events->onFirstPage())
                    <span class="btn btn-sm muted" aria-disabled="true">Anterior</span>
                @else
                    <a class="btn btn-sm" href="{{ $events->previousPageUrl() }}">Anterior</a>
                @endif
                <span class="muted">Página {{ $events->currentPage() }} de {{ $events->lastPage() }} · {{ $events->total() }} eventos</span>
                @if ($events->hasMorePages())
                    <a class="btn btn-sm" href="{{ $events->nextPageUrl() }}">Próxima</a>
                @else
                    <span class="btn btn-sm muted" aria-disabled="true">Próxima</span>
                @endif
            </div>
        @endif
    @endif
</section>
