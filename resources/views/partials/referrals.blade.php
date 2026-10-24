<section class="card">
    <div class="card-head">
        <h2>Indicações recentes</h2>
        <div class="actions"><span class="muted">clientes que vieram de afiliado</span></div>
    </div>

    @if ($referrals->isEmpty())
        <div class="empty">Nenhuma indicação ainda.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Código</th>
                        <th>Origem</th>
                        <th>No hub</th>
                        <th>Cadastro</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($referrals as $referral)
                        @php($customer = $customers->get($referral->customer_id))
                        <tr>
                            <td>
                                @if ($customer)
                                    <div>{{ $customer->getAttribute('name') ?? $customer->getAttribute('email') }}</div>
                                    <div class="muted mono">{{ $customer->getAttribute('email') }}</div>
                                @else
                                    <span class="mono">{{ $referral->customer_id }}</span>
                                @endif
                            </td>
                            <td class="mono">{{ $referral->code }}</td>
                            <td class="muted">
                                {{ $referral->click_id ? 'link do hub' : 'link direto' }}
                                @if (! empty($referral->utm['utm_source']))
                                    · {{ $referral->utm['utm_source'] }}
                                @endif
                            </td>
                            <td>
                                @if ($referral->attributed)
                                    <span class="badge badge-sent">Atribuída</span>
                                @else
                                    <span class="badge badge-muted">Não atribuída</span>
                                @endif
                            </td>
                            <td class="muted when" title="{{ $referral->created_at }}">{{ \BoraIndicar\Laravel\Support\RelativeTime::of($referral->created_at) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
