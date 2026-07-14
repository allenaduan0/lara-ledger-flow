@extends('admin.layout')
@section('title', 'Dashboard')
@section('heading', 'Operations overview')
@section('content')
<div class="grid">
    <div class="card"><div class="muted">Wallets</div><div class="metric">{{ number_format($wallets) }}</div></div>
    <div class="card"><div class="muted">Transactions today</div><div class="metric">{{ number_format($transactions_today) }}</div></div>
    <div class="card"><div class="muted">Processing</div><div class="metric">{{ number_format($processing_transactions) }}</div></div>
    <div class="card"><div class="muted">Reconciliation breaks</div><div class="metric">{{ number_format($open_reconciliation_breaks) }}</div></div>
</div>
<div class="split">
    <div class="table-card">
        <table>
            <thead><tr><th>Recent transaction</th><th>Type</th><th>Status</th><th>Amount</th></tr></thead>
            <tbody>
            @forelse($recent_transactions as $transaction)
                <tr>
                    <td><a href="{{ route('admin.transactions.show', $transaction) }}"><strong>{{ $transaction->reference }}</strong></a><div class="muted">{{ $transaction->initiator?->email }}</div></td>
                    <td>{{ $transaction->type->value }}</td>
                    <td><span class="badge {{ $transaction->status->value }}">{{ $transaction->status->value }}</span></td>
                    <td class="amount">{{ $transaction->currency->formatMinor($transaction->amount_minor) }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No transactions yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-card">
        <table>
            <thead><tr><th>Recent reconciliation</th></tr></thead>
            <tbody>
            @forelse($recent_reports as $report)
                <tr><td><a href="{{ route('admin.reconciliation.show', $report) }}"><strong>{{ $report->business_date->toDateString() }} · {{ $report->currency_code }}</strong></a><div><span class="badge {{ $report->status }}">{{ $report->status }}</span></div></td></tr>
            @empty
                <tr><td>No reports yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
