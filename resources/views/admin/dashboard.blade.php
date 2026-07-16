@extends('admin.layout')
@section('title', 'Dashboard')
@section('heading', 'Operations overview')
@section('content')
<section class="ops-hero">
    <div>
        <span class="ops-label">Live financial infrastructure</span>
        <h2>Everything is flowing normally.</h2>
        <p>Monitor money movement, ledger integrity, and settlement health from one workspace.</p>
    </div>
    <div class="ops-score">
        <div class="score-ring"><span>99.9<small>%</small></span></div>
        <div><strong>System health</strong><span>All services operational</span></div>
    </div>
</section>

<div class="grid">
    <div class="card metric-card"><div class="metric-icon">W</div><div class="muted">Total wallets</div><div class="metric">{{ number_format($wallets) }}</div><span class="trend">LIVE</span></div>
    <div class="card metric-card"><div class="metric-icon">T</div><div class="muted">Transactions today</div><div class="metric">{{ number_format($transactions_today) }}</div><span class="trend">TODAY</span></div>
    <div class="card metric-card"><div class="metric-icon">P</div><div class="muted">Processing queue</div><div class="metric">{{ number_format($processing_transactions) }}</div><span class="trend">REAL TIME</span></div>
    <div class="card metric-card"><div class="metric-icon">R</div><div class="muted">Reconciliation breaks</div><div class="metric">{{ number_format($open_reconciliation_breaks) }}</div><span class="trend">REVIEW</span></div>
</div>

<div class="analytics-grid">
    <section class="card flow-card">
        <div class="section-head"><div><span class="panel-label">Processing pulse</span><h2>Transaction volume</h2></div><span class="chart-period">Last 7 days</span></div>
        <div class="chart-total">{{ number_format($transactions_today) }} <small>today</small></div>
        <div class="bar-chart" aria-hidden="true"><i style="--h:38%"></i><i style="--h:58%"></i><i style="--h:46%"></i><i style="--h:74%"></i><i style="--h:65%"></i><i style="--h:88%"></i><i class="current" style="--h:72%"></i></div>
        <div class="chart-days"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>
    </section>
    <section class="card integrity-card">
        <div class="section-head"><div><span class="panel-label">Ledger integrity</span><h2>Control center</h2></div><span class="pulse-dot"></span></div>
        <div class="integrity-row"><span class="control-icon">01</span><div><strong>Double-entry ledger</strong><small>Journal verification active</small></div><b>Healthy</b></div>
        <div class="integrity-row"><span class="control-icon">02</span><div><strong>Reconciliation</strong><small>{{ number_format($open_reconciliation_breaks) }} open breaks</small></div><b>Running</b></div>
        <div class="integrity-row"><span class="control-icon">03</span><div><strong>Processing queue</strong><small>{{ number_format($processing_transactions) }} transactions pending</small></div><b>Online</b></div>
    </section>
</div>

<div class="split">
    <div class="table-card">
        <div class="section-head"><div><span class="panel-label">Money movement</span><h2>Transaction activity</h2></div><a class="section-link" href="{{ route('admin.transactions.index') }}">View all</a></div>
        <table>
            <thead><tr><th>Recent transaction</th><th>Type</th><th>Status</th><th>Amount</th></tr></thead>
            <tbody>
            @forelse($recent_transactions as $transaction)
                <tr><td><a href="{{ route('admin.transactions.show', $transaction) }}"><strong>{{ $transaction->reference }}</strong></a><div class="muted">{{ $transaction->initiator?->email }}</div></td><td>{{ $transaction->type->value }}</td><td><span class="badge {{ $transaction->status->value }}">{{ $transaction->status->value }}</span></td><td class="amount">{{ $transaction->currency->formatMinor($transaction->amount_minor) }}</td></tr>
            @empty
                <tr><td colspan="4">No transactions yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-card">
        <div class="section-head"><div><span class="panel-label">Settlement</span><h2>Reconciliation health</h2></div><a class="section-link" href="{{ route('admin.reconciliation.index') }}">Reports</a></div>
        <table><thead><tr><th>Recent reconciliation</th></tr></thead><tbody>
        @forelse($recent_reports as $report)
            <tr><td><a href="{{ route('admin.reconciliation.show', $report) }}"><strong>{{ $report->business_date->toDateString() }} / {{ $report->currency_code }}</strong></a><div style="margin-top:7px"><span class="badge {{ $report->status }}">{{ $report->status }}</span></div></td></tr>
        @empty
            <tr><td>No reports yet.</td></tr>
        @endforelse
        </tbody></table>
    </div>
</div>
@endsection
