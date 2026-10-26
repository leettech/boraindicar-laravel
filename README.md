# leettech/boraindicar-laravel

Integração dos produtos Leettech com o [boraindicar](https://app.boraindicar.com), o programa de afiliados. Com o pacote instalado:

- o link do afiliado (`?ref=` / `?via=`) vira um cookie de servidor, first-touch; o prazo do link é o do produto, que o hub aplica no cadastro;
- o cadastro (`Illuminate\Auth\Events\Registered`) vira indicação no hub;
- com Laravel Cashier, `invoice.paid`, `charge.refunded` e `customer.subscription.deleted` viram pagamento, reembolso e cancelamento no hub;
- tudo passa por uma fila com retry (outbox em `boraindicar_events`) e aparece no painel `/boraindicar`.

Requer PHP 8.3+ e Laravel 12 ou 13. Cashier 16.5+ é opcional.

## Instalação

O pacote é público no GitHub ([leettech/boraindicar-laravel](https://github.com/leettech/boraindicar-laravel)), fora do Packagist. Acrescente o repositório no `composer.json` do produto:

```json
"repositories": [
    {"type": "vcs", "url": "https://github.com/leettech/boraindicar-laravel.git"}
]
```

```sh
composer require leettech/boraindicar-laravel
php artisan boraindicar:install
php artisan migrate
```

`boraindicar:install` publica `config/boraindicar.php` e as migrations, e mostra o que pôr no `.env`:

```dotenv
BORAINDICAR_URL=https://app.boraindicar.com
BORAINDICAR_KEY=pk_...              # chave pública (cliques e snippet)
BORAINDICAR_SECRET=sk_...           # chave secreta (todo o resto)
BORAINDICAR_COOKIE_DOMAIN=.seudominio.com.br
```

As chaves ficam no hub, em **Admin › Programas › (o programa) › Chaves**. A secreta aparece uma vez só.

Teste a conexão:

```sh
php artisan boraindicar:ping
# Conectado ao produto Meu SaaS (meu-saas).
```

Rode um worker de fila (`php artisan queue:work` ou Horizon). Os eventos são enviados por job.

## Como funciona

1. **Captura**: o middleware `CaptureReferral` entra no grupo `web` sozinho. Em todo `GET` com `?ref=` (ou `?via=`), se ainda não existe cookie válido, ele grava o cookie `boraindicar` com `{c, k, t, u}` (código, click id, timestamp, UTMs). O cookie é JSON puro (fora do `EncryptCookies`, sem `httpOnly`) para o snippet do hub ler o mesmo valor. A atribuição também fica na sessão (`boraindicar.attribution`), o que cobre o vai e volta de login social. Se veio `ref` sem `ix` (link direto no site, não pelo `/r/{code}` do hub), o clique é registrado no hub com a chave pública.
2. **Cadastro**: no `Registered`, se há atribuição, o pacote manda a indicação (`POST /api/v1/referrals`, com `clicked_at`, a hora da captura), grava `boraindicar_referrals` e apaga o cookie. O hub recusa (`attributed: false`) o cadastro que veio depois do prazo do link do produto.
3. **Pagamento, reembolso, cancelamento**: automático com Cashier (abaixo) ou manual pela facade.

Cada fato vira uma linha em `boraindicar_events` com uma chave de idempotência (`payment:{id}`, `lead:{id}`...). Chamar duas vezes com os mesmos dados não duplica nada.

## Cashier

Com `laravel/cashier` instalado, o pacote escuta `Laravel\Cashier\Events\WebhookReceived` (fila):

| Webhook Stripe | Vira | Detalhe |
|---|---|---|
| `invoice.paid` | `payment` | valor base sem imposto: `total_excluding_tax` (ou `amount_paid` − impostos); fatura zerada é ignorada |
| `charge.refunded` | `refund` | total quando `refunded=true`; parcial manda o acumulado `amount_refunded`. A fatura vem de `charge.invoice` ou, na API `basil`, do pagamento da fatura do payment intent |
| `customer.subscription.deleted` | `cancel` | chaveado pela assinatura: cancelar, assinar de novo e cancelar outra vez é reportado de novo |

O cliente é achado com `Cashier::findBillable($customer)`. Garanta que o webhook do Stripe inclui esses três eventos.

## Desconto no checkout

O produto (ou o modelo de programa) no hub pode prometer um desconto a quem chega por indicação, por exemplo 10% por 12 meses. O hub devolve esse desconto na resposta do cadastro e o pacote guarda em `boraindicar_referrals` (`discount_bp`, `discount_months`).

```php
use BoraIndicar\Laravel\Facades\BoraIndicar;

BoraIndicar::discount($user);          // ?BoraIndicar\Laravel\Discount
BoraIndicar::discount($user)?->percent(); // 10.0, para mostrar na tela de planos
BoraIndicar::discount($user)?->label();   // "boraindicar: 10% por 12 meses"
```

Com Cashier, `BoraIndicar::stripeCouponId($user)` garante no Stripe um cupom `boraindicar-{pct}pct-{m}m` (`duration=repeating`, `percent_off`, `duration_in_months`), criado no primeiro uso e reaproveitado depois, e devolve o id. Sem desconto, devolve `null` sem chamar o Stripe. O cupom vai na sessão de checkout:

```php
$coupon = BoraIndicar::stripeCouponId($user);

$checkout = $user->newSubscription('default', $price)->checkout([
    'success_url' => route('billing.success'),
    'cancel_url' => route('billing'),
    'discounts' => $coupon === null ? [] : [['coupon' => $coupon]],
]);

// Ou direto no Stripe:
Cashier::stripe()->checkout->sessions->create([
    // ...
    'discounts' => [['coupon' => $coupon]],
]);
```

Se o cadastro do cliente ainda está na fila (worker atrasado ou parado), `BoraIndicar::discount()` o envia na hora e usa o desconto da resposta. Chamadas ao hub feitas no checkout têm timeout curto (1s para conectar, 2s no total) e não repetem.

O checkout nunca quebra por causa do boraindicar: hub fora do ar ou erro do Stripe viram `null` (sem desconto) e vão para o `report()`. Com o hub fora, nada é gravado: a próxima chamada tenta de novo. Sem Cashier instalado, `stripeCouponId` lança `LogicException`; use `BoraIndicar::discount()` e aplique o desconto no seu gateway.

**Upgrade de versão anterior**: publique e rode a migration nova (as que você já tem não são copiadas de novo):

```sh
php artisan vendor:publish --tag=boraindicar-migrations
php artisan migrate
```

Clientes indicados antes do upgrade ficam com `discount_bp` nulo. Na primeira chamada de `BoraIndicar::discount()` o pacote consulta o hub (`GET /api/v1/referrals/{external_id}`) e grava a resposta; cliente que o hub não conhece fica sem desconto.

## Uso manual (outros gateways)

```php
use BoraIndicar\Laravel\Facades\BoraIndicar;

BoraIndicar::payment($user->id, $invoice->id, amountCents: 9700, currency: 'BRL', paidAt: $invoice->paid_at);
BoraIndicar::refund($invoice->id);              // reembolso total
BoraIndicar::refund($invoice->id, 4000);        // parcial: valor acumulado já reembolsado
BoraIndicar::cancel($user, reference: $subscription->id);

// Cadastro fora do Registered (ex.: criado por API):
BoraIndicar::lead($user);                       // usa o cookie/sessão do request atual
BoraIndicar::lead(['id' => 15, 'email' => 'ana@ex.com', 'name' => 'Ana'], $attribution);

BoraIndicar::attribution();                     // ?Attribution do request atual
```

`amount_cents` é sempre a base da comissão: depois de desconto, sem imposto.

O `external_id` do cliente é o atributo `customer_id` da config (padrão `id`). Para outra regra:

```php
// AppServiceProvider::boot()
BoraIndicar::resolveCustomerIdUsing(fn ($user) => $user->uuid);
```

## Snippet do hub nas landings

O middleware cobre as páginas servidas pelo Laravel. Para landings que não passam por ele, use o snippet do hub no `<head>`.

Landing em Blade:

```blade
<head>
    @boraindicarScript
</head>
```

Landing em HTML estático (fora do Laravel), a mesma tag escrita à mão:

```html
<script src="https://app.boraindicar.com/js/boraindicar.js" data-key="pk_..." data-domain=".seudominio.com.br" defer></script>
```

O cookie `boraindicar` é o JSON `{c, k, t, u}` codificado para URL no `Set-Cookie` (ex.: `%7B%22c%22%3A%22mar7k2qp%22...`). Quem lê pelo JS precisa de `decodeURIComponent` antes do `JSON.parse`; o pacote decodifica sozinho.

## Configuração

`config/boraindicar.php`:

| Chave | Padrão | Para quê |
|---|---|---|
| `url`, `key`, `secret` | env | hub e chaves |
| `enabled` | `true` se há `BORAINDICAR_SECRET` | desligado: nada é gravado nem enviado |
| `query_params` | `['ref', 'via']` | parâmetros com o código do afiliado |
| `cookie.name` / `days` / `domain` | `boraindicar` / 365 / `BORAINDICAR_COOKIE_DOMAIN` | cookie de atribuição; `days` é só quanto o navegador guarda (o prazo do link é o do produto, no hub) |
| `attribution` | `first` | first-touch (o primeiro link vale) |
| `user_model` | `App\Models\User` | usado pelo painel para mostrar nome/e-mail |
| `customer_id` | `id` | atributo enviado como `external_id` |
| `queue.connection` / `queue.queue` | padrão do app / `default` | fila dos envios e do listener do Stripe |
| `middleware_group` | `web` | `null` para registrar `CaptureReferral` você mesmo |
| `auto.registered` / `auto.cashier` | `true` / `true` | listeners automáticos |
| `dashboard.path` / `middleware` | `boraindicar` / `['web', 'auth']` | painel |
| `timeout` | 5 | segundos por chamada ao hub |

O pacote tira o cookie `boraindicar` da criptografia sozinho. Se o seu app tiver um `EncryptCookies` próprio que ignore isso, adicione em `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->encryptCookies(except: ['boraindicar']);
})
```

## Painel `/boraindicar`

Status da conexão (último ping), contadores por status, eventos com filtro por status e tipo, botão **Reenviar** nos que falharam e as últimas indicações.

Nome e e-mail do cliente nas indicações saem de `user_model`, buscando pela coluna `customer_id` da config. Com `BoraIndicar::resolveCustomerIdUsing()` o painel não tem como desfazer o callback e mostra só o `external_id`.

Por padrão só abre em `APP_ENV=local`. Para liberar em produção, defina o gate:

```php
// AppServiceProvider::boot()
Gate::define('viewBoraIndicar', fn ($user) => $user->is_admin);
```

## Retry

- Erro de conexão ou 5xx: 3 tentativas rápidas na hora; depois a fila tenta de novo até 10 vezes, com espera crescente (30 s → 1 h). Esgotou: `failed`.
- 422: `failed` na hora, com a mensagem de validação em `last_error`. Corrija a causa e reenvie.
- Outros 4xx (401, 403, 404): `failed` na hora, sem retry (chave ou URL errada).

Cada evento tem no máximo um envio na fila por vez (job único, trava de até 1 h). Reenviar um evento que já está na fila não faz nada e o painel avisa "já está na fila de envio".

Reenviar:

```sh
php artisan boraindicar:retry 42      # um evento
php artisan boraindicar:retry --all   # todos com falha
```

## Troubleshooting

- **`boraindicar:ping` dá 401**: `BORAINDICAR_SECRET` errada ou revogada. Gere outra no hub.
- **Nada aparece em `boraindicar_events`**: `BORAINDICAR_SECRET` vazia desliga o pacote (`enabled`). Veja o aviso no topo do painel.
- **Eventos parados em "Pendente"**: o worker da fila não está rodando, ou está em outra fila (`BORAINDICAR_QUEUE`).
- **Cookie não chega no app**: landing e app em subdomínios diferentes precisam de `BORAINDICAR_COOKIE_DOMAIN=.seudominio.com.br`. Em domínios diferentes, repasse `ref` e `ix` nos links para o app.
- **Cadastro sem indicação**: o `Registered` precisa disparar no mesmo request que tem o cookie ou a sessão. Para cadastro por API ou fila, chame `BoraIndicar::lead($user, $attribution)` passando a atribuição guardada.
- **Fila `sync`**: funciona, mas sem retry: com o hub fora do ar o evento fica `failed` (o cadastro não quebra) e precisa de `boraindicar:retry`.

## Desenvolvimento

Dentro do repo do boraindicar, sempre no Docker:

```sh
bin/test   # roda o Pest do hub e do pacote
```

Painel com dados de exemplo (testbench workbench), em `packages/boraindicar-laravel`:

```sh
docker run --rm -u $(id -u):$(id -g) -e HOME=/tmp -p 8095:8000 \
  -v "$(git rev-parse --show-toplevel)":/var/www/html -w /var/www/html/packages/boraindicar-laravel \
  --entrypoint bash boraindicar-app:dev \
  -c 'composer install -q && vendor/bin/testbench workbench:build && vendor/bin/testbench serve --host=0.0.0.0 --port=8000'
```

Abra <http://localhost:8095/_workbench> (entra como o usuário 1 e vai para `/boraindicar`).
