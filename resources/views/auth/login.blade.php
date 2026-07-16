<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · LedgerFlow</title>
    <link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f1f6f3;color:#10231d;font:14px/1.5 "Instrument Sans",system-ui,sans-serif;min-height:100vh;display:grid;place-items:center;padding:28px}.wrap{width:min(980px,100%);min-height:610px;display:grid;grid-template-columns:1.05fr .95fr;background:#fff;border:1px solid #dfe9e5;border-radius:22px;overflow:hidden;box-shadow:0 30px 80px #153d311c}.intro{position:relative;padding:48px;background:#0b2820;color:#fff;overflow:hidden;display:flex;flex-direction:column}.intro:after{content:"";position:absolute;width:420px;height:420px;border:1px solid #82d9b533;border-radius:50%;right:-190px;bottom:-210px;box-shadow:0 0 0 70px #82d9b50b,0 0 0 140px #82d9b508}.brand{display:flex;align-items:center;gap:11px;font-size:20px;font-weight:700}.mark{display:grid;place-items:center;width:38px;height:38px;border-radius:11px;background:#baf5d7;color:#075f47}.pitch{margin:auto 0;position:relative;z-index:1}.pitch h1{max-width:380px;font-size:42px;line-height:1.08;letter-spacing:-.05em;margin:0 0 18px}.pitch p{max-width:390px;color:#a9c2ba;font-size:16px}.proof{display:flex;gap:26px;position:relative;z-index:1}.proof strong{display:block;font-size:20px;color:#baf5d7}.proof span{font-size:11px;color:#86a99e;text-transform:uppercase;letter-spacing:.08em}.form{padding:58px;display:flex;flex-direction:column;justify-content:center}.form h2{font-size:29px;letter-spacing:-.04em;margin:0 0 5px}.muted{color:#6b7c76}label{display:block;font-weight:600;margin:18px 0 7px}input{width:100%;height:46px;border:1px solid #cad9d3;border-radius:10px;padding:0 13px;outline:none}input:focus{border-color:#3fb88d;box-shadow:0 0 0 3px #31a87d17}button{width:100%;height:46px;margin-top:22px;border:0;border-radius:10px;background:#087f5b;color:#fff;font-weight:700;cursor:pointer;box-shadow:0 5px 14px #087f5b2b}.demo{background:#f6faf8;border:1px solid #dfe9e5;border-radius:10px;padding:11px 13px;margin-top:10px}.demo code{display:block;color:#4c675e;font-size:11px}.demo-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}.error{color:#c2413b;margin-top:6px}.form a{color:#087f5b;text-decoration:none;font-weight:600}.foot{margin-top:24px}@media(max-width:760px){.wrap{grid-template-columns:1fr;min-height:0}.intro{display:none}.form{padding:38px 28px}.demo-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">
    <section class="intro">
        <div class="brand"><span class="mark">L</span>LedgerFlow</div>
        <div class="pitch"><h1>Financial infrastructure, made clear.</h1><p>A secure workspace for digital wallets, fund movements, immutable journals, and daily reconciliation.</p></div>
        <div class="proof"><div><strong>24/7</strong><span>Ledger visibility</span></div><div><strong>100%</strong><span>Traceable entries</span></div></div>
    </section>
    <form class="form" method="post" action="{{ route('login.store') }}">@csrf
        <h2>Welcome back</h2><p class="muted">Sign in to your LedgerFlow workspace.</p>
        <label>Email address</label><input name="email" type="email" value="{{ old('email') }}" placeholder="you@company.com" required autofocus>@error('email')<div class="error">{{ $message }}</div>@enderror
        <label>Password</label><input name="password" type="password" placeholder="Enter your password" required>
        <button>Sign in securely</button>
        @if(config('demo.enabled'))<p class="muted" style="margin:24px 0 4px;font-size:12px;font-weight:600">DEMO ACCESS</p><div class="demo-grid"><div class="demo"><strong>Operations</strong><code>{{ config('demo.admin_email') }}</code><code>{{ config('demo.admin_password') }}</code></div><div class="demo"><strong>Customer</strong><code>{{ config('demo.customer_email') }}</code><code>{{ config('demo.customer_password') }}</code></div></div>@endif
        <p class="muted foot">New to LedgerFlow? <a href="{{ route('register') }}">Create an account</a></p>
    </form>
</div>
</body>
</html>
