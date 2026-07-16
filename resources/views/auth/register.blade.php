<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Create account · LedgerFlow</title>
    <link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    <style>*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 15% 15%,#dff5eb 0,transparent 30%),#f1f6f3;color:#10231d;font:14px/1.5 "Instrument Sans",system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;padding:28px}.panel{width:min(500px,100%);background:#fff;border:1px solid #dfe9e5;border-radius:20px;padding:38px;box-shadow:0 25px 70px #153d3117}.brand{display:flex;align-items:center;gap:10px;font-size:18px;font-weight:700;margin-bottom:32px}.mark{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#0b2820;color:#baf5d7}h1{font-size:29px;letter-spacing:-.04em;margin:0 0 4px}.muted{color:#6b7c76}label{display:block;font-weight:600;margin:15px 0 6px}input{width:100%;height:45px;border:1px solid #cad9d3;border-radius:10px;padding:0 13px;outline:none}input:focus{border-color:#3fb88d;box-shadow:0 0 0 3px #31a87d17}button{width:100%;height:46px;margin-top:22px;border:0;border-radius:10px;background:#087f5b;color:#fff;font-weight:700;cursor:pointer;box-shadow:0 5px 14px #087f5b2b}.error{color:#c2413b;margin-top:5px}.panel a{color:#087f5b;text-decoration:none;font-weight:600}.foot{text-align:center;margin:21px 0 0}@media(max-width:520px){.panel{padding:30px 23px}}</style>
</head>
<body>
<form class="panel" method="post" action="{{ route('register.store') }}">@csrf
    <div class="brand"><span class="mark">L</span>LedgerFlow</div>
    <h1>Create your account</h1><p class="muted">Set up your secure customer workspace.</p>
    <label>Full name</label><input name="name" value="{{ old('name') }}" placeholder="Your full name" required>@error('name')<div class="error">{{ $message }}</div>@enderror
    <label>Email address</label><input name="email" type="email" value="{{ old('email') }}" placeholder="you@company.com" required>@error('email')<div class="error">{{ $message }}</div>@enderror
    <label>Password</label><input name="password" type="password" placeholder="Create a strong password" required>
    <label>Confirm password</label><input name="password_confirmation" type="password" placeholder="Repeat your password" required>@error('password')<div class="error">{{ $message }}</div>@enderror
    <button>Create secure account</button><p class="muted foot">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
</form>
</body>
</html>
