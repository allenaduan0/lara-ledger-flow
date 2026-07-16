@extends('customer.layout')
@section('title','Dashboard')
@section('content')
<section class="finance-hero">
    <div class="hero-copy">
        <span class="welcome-kicker">Money, clearly managed</span>
        <h1>Welcome back, {{ explode(' ', auth()->user()->name)[0] }}.</h1>
        <p>Your complete financial picture, updated in real time.</p>
        <div class="hero-actions">
            <a class="button light-button" href="{{ route('customer.transactions.create','transfer') }}">Send money <span>&rarr;</span></a>
            @if(config('demo.enabled'))<a class="round-action" href="{{ route('customer.transactions.create','deposit') }}" title="Add funds">+</a>@endif
        </div>
    </div>
    <div class="hero-visual" aria-hidden="true">
        <div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
        <div class="glass-card"><span>LEDGERFLOW</span><b>•••• 2048</b><small>VIRTUAL ACCOUNT</small></div>
        <div class="float-chip chip-one"><i></i>Ledger verified</div>
        <div class="float-chip chip-two">24/7 protected</div>
    </div>
</section>

<div class="section-title">
    <div><span class="eyebrow">Your portfolio</span><h2>Wallets & balances</h2></div>
    <div class="actions"><a class="button" href="{{ route('customer.transactions.create','transfer') }}">Send</a>@if(config('demo.enabled'))<a class="button secondary" href="{{ route('customer.transactions.create','deposit') }}">Add funds</a>@endif<a class="button secondary" href="{{ route('customer.transactions.create','withdrawal') }}">Withdraw</a></div>
</div>

<div class="wallet-grid">
@foreach($wallets as $wallet)
    <a class="wallet-premium" href="{{ route('customer.wallets.show',$wallet) }}">
        <div class="wallet-top"><span class="currency-orb">{{ substr($wallet->currency_code,0,1) }}</span><span class="badge {{ $wallet->status->value }}">{{ $wallet->status->value }}</span></div>
        <span class="wallet-label">{{ $wallet->name ?: $wallet->currency_code.' wallet' }}</span>
        <strong class="wallet-balance">{{ $wallet->currency_code }} {{ number_format(($wallet->ledger_balance->availableMinor ?? 0) / (10 ** $wallet->currency->minor_unit), $wallet->currency->minor_unit) }}</strong>
        <div class="wallet-foot"><span>Available balance</span><span>View wallet &rarr;</span></div>
    </a>
@endforeach
    <div class="create-wallet">
        <div class="create-head"><span>+</span><div><strong>New wallet</strong><small>Open another currency account</small></div></div>
        <form method="post" action="{{ route('customer.wallets.store') }}">@csrf
            <div class="inline-fields"><select name="currency">@foreach($currencies as $currency)<option>{{ $currency->code }}</option>@endforeach</select><input name="name" placeholder="Wallet name"></div>
            <button class="button">Create wallet</button>
        </form>
    </div>
</div>

<div class="insight-grid">
    <section class="insight-card">
        <div><span class="eyebrow">Weekly flow</span><h2>Money activity</h2></div>
        <div class="mini-chart"><i style="--h:32%"></i><i style="--h:55%"></i><i style="--h:42%"></i><i style="--h:78%"></i><i style="--h:62%"></i><i style="--h:90%"></i><i style="--h:68%"></i></div>
        <div class="chart-caption"><span>MON</span><span>TUE</span><span>WED</span><span>THU</span><span>FRI</span><span>SAT</span><span>SUN</span></div>
    </section>
    <section class="security-card"><span class="shield">✓</span><div><span class="eyebrow">Security</span><h2>Your account is protected</h2><p>Immutable ledger records and monitored money movement keep every transaction traceable.</p></div></section>
</div>

<div class="section-title recent-title"><div><span class="eyebrow">Latest movement</span><h2>Recent transactions</h2></div><a class="text-link" href="{{ route('customer.transactions.index') }}">See all activity</a></div>
<table class="table"><thead><tr><th>Reference</th><th>Type</th><th>Status</th><th>Amount</th></tr></thead><tbody>
@forelse($recentTransactions as $transaction)
<tr><td><a href="{{ route('customer.transactions.show',$transaction) }}"><strong>{{ $transaction->reference }}</strong></a></td><td>{{ $transaction->type->value }}</td><td><span class="badge {{ $transaction->status->value }}">{{ $transaction->status->value }}</span></td><td class="amount-cell">{{ $transaction->currency_code }} {{ number_format($transaction->amount_minor / (10 ** $transaction->currency->minor_unit), $transaction->currency->minor_unit) }}</td></tr>
@empty<tr><td colspan="4">No transactions yet.</td></tr>@endforelse
</tbody></table>
@endsection
