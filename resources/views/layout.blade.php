<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath d='M0.9 12a2.1 2.1 0 1 0 4.2 0a2.1 2.1 0 1 0-4.2 0Z' fill='%230d4f61'/%3E%3Cpath d='M9.9 12a2.1 2.1 0 1 0 4.2 0a2.1 2.1 0 1 0-4.2 0Z' fill='%23ee6f61'/%3E%3Cpath d='M3 8.4C3 1.6 12 1.6 12 8.4M9.9 6.8l2.1 2.1 2.1-2.1' fill='none' stroke='%230d4f61' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
    <title>@yield('title', 'Bora Indicar')</title>
    <style>
        /* The hub's petróleo brand (docs/design-decisions.md, 21), self-contained: the panel runs inside the product. */
        :root {
            --bg: #f3f6f6; --card: #ffffff; --border: #d7e0e2; --text: #10222a; --muted: #586a70;
            --brand: #0d4f61; --brand-strong: #093c4a; --brand-soft: #e1eff2; --brand-line: #a9cdd6; --accent: #ee6f61;
            --green: #15803d; --green-soft: #e8f7ee; --amber: #b45309; --amber-soft: #fdf3e1; --red: #b91c1c; --red-soft: #fdecec;
            --mono: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 14px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        a { color: var(--brand); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .topbar { background: var(--card); border-bottom: 1px solid var(--border); }
        .topbar .inner { max-width: 1180px; margin: 0 auto; padding: 14px 20px; display: flex; align-items: center; gap: 12px; }
        .logo { width: 26px; height: 26px; flex: none; }
        .logo .dot-affiliate { fill: var(--brand); }
        .logo .dot-client { fill: var(--accent); }
        .logo .hop { fill: none; stroke: var(--brand); stroke-width: 6; stroke-linecap: round; stroke-linejoin: round; }
        .topbar h1 { font-size: 17px; margin: 0; font-weight: 800; letter-spacing: -0.02em; }
        .topbar h1 span { color: var(--brand); }
        .topbar .hub { margin-left: auto; color: var(--muted); font-family: var(--mono); font-size: 12px; }
        main { max-width: 1180px; margin: 0 auto; padding: 24px 20px 48px; display: grid; gap: 20px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 10px; }
        .card-head { display: flex; align-items: center; gap: 12px; padding: 14px 18px; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
        .card-head h2 { font-size: 14px; margin: 0; font-weight: 600; }
        .card-head .actions { margin-left: auto; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .card-body { padding: 16px 18px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; }
        .stat { padding: 16px 18px; }
        .stat .label { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .stat .value { font-size: 26px; font-weight: 600; margin-top: 4px; font-variant-numeric: tabular-nums; }
        .stat .value.failed { color: var(--red); }
        .stat .value.pending { color: var(--amber); }
        .stat .value.sent { color: var(--green); }
        .conn { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .dot { width: 10px; height: 10px; border-radius: 50%; background: var(--muted); flex: none; }
        .dot.ok { background: var(--green); box-shadow: 0 0 0 4px var(--green-soft); }
        .dot.error { background: var(--red); box-shadow: 0 0 0 4px var(--red-soft); }
        .muted { color: var(--muted); }
        .mono { font-family: var(--mono); font-size: 12px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--border); background: var(--card); color: var(--text); border-radius: 7px; padding: 6px 12px; font: inherit; font-size: 13px; cursor: pointer; }
        .btn:hover { border-color: #c7ccd3; text-decoration: none; }
        .btn-primary { background: var(--brand); border-color: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-strong); }
        .btn-sm { padding: 3px 9px; font-size: 12px; }
        select { border: 1px solid var(--border); border-radius: 7px; padding: 6px 8px; font: inherit; font-size: 13px; background: var(--card); color: var(--text); }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 18px; border-bottom: 1px solid var(--border); vertical-align: top; }
        th { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); font-weight: 600; background: #fafbfc; }
        tr:last-child td { border-bottom: 0; }
        td.when { white-space: nowrap; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .badge { display: inline-block; border-radius: 999px; padding: 1px 9px; font-size: 12px; font-weight: 500; white-space: nowrap; }
        .badge-sent { background: var(--green-soft); color: var(--green); }
        .badge-pending { background: var(--amber-soft); color: var(--amber); }
        .badge-failed { background: var(--red-soft); color: var(--red); }
        .badge-type { background: var(--brand-soft); color: var(--brand); }
        .badge-muted { background: #eef0f2; color: var(--muted); }
        .error-text { color: var(--red); font-size: 12px; margin-top: 4px; max-width: 420px; word-break: break-word; }
        .empty { padding: 28px 18px; text-align: center; color: var(--muted); }
        .flash { background: var(--brand-soft); border: 1px solid var(--brand-line); color: var(--brand-strong); border-radius: 10px; padding: 10px 16px; }
        .warning { background: var(--amber-soft); border: 1px solid #f5dcae; color: var(--amber); border-radius: 10px; padding: 10px 16px; }
        .pagination { display: flex; gap: 8px; justify-content: space-between; align-items: center; padding: 12px 18px; border-top: 1px solid var(--border); }
        .table-wrap { overflow-x: auto; }
        details summary { cursor: pointer; color: var(--muted); font-size: 12px; }
        details pre { margin: 6px 0 0; background: #fafbfc; border: 1px solid var(--border); border-radius: 6px; padding: 8px; max-width: 420px; overflow-x: auto; font-size: 11px; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="inner">
            <svg class="logo" viewBox="0 0 64 64" aria-hidden="true">
                <path class="dot-affiliate" d="M4 47a7 7 0 1 0 14 0a7 7 0 1 0-14 0Z"/>
                <path class="dot-client" d="M42 47a7 7 0 1 0 14 0a7 7 0 1 0-14 0Z"/>
                <path class="hop" d="M11 34C11 7 49 7 49 34M41 27l8 8 8-8"/>
            </svg>
            <h1>Bora <span>Indicar</span></h1>
            <span class="hub">{{ $hubUrl }}</span>
        </div>
    </header>
    <main>
        @yield('content')
    </main>
</body>
</html>
