<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="data:,">
    <title>@yield('title', 'boraindicar')</title>
    <style>
        :root {
            --bg: #f4f5f7; --card: #ffffff; --border: #e3e6ea; --text: #1f2933; --muted: #6b7785;
            --brand: #4f46e5; --brand-soft: #eef0ff;
            --green: #15803d; --green-soft: #e8f7ee; --amber: #b45309; --amber-soft: #fdf3e1; --red: #b91c1c; --red-soft: #fdecec;
            --mono: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 14px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        a { color: var(--brand); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .topbar { background: var(--card); border-bottom: 1px solid var(--border); }
        .topbar .inner { max-width: 1180px; margin: 0 auto; padding: 14px 20px; display: flex; align-items: center; gap: 12px; }
        .logo { width: 28px; height: 28px; border-radius: 8px; background: var(--brand); color: #fff; display: grid; place-items: center; font-weight: 700; }
        .topbar h1 { font-size: 16px; margin: 0; font-weight: 600; }
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
        .btn-primary:hover { background: #4338ca; }
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
        .flash { background: var(--brand-soft); border: 1px solid #d6d9ff; color: #3730a3; border-radius: 10px; padding: 10px 16px; }
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
            <div class="logo">i</div>
            <h1>boraindicar</h1>
            <span class="hub">{{ $hubUrl }}</span>
        </div>
    </header>
    <main>
        @yield('content')
    </main>
</body>
</html>
