@extends('admin.layout')
@section('title', $transaction->reference)
@section('heading', 'Transaction details')
@section('content')
<div class="split">
    <section class="card">
        <h2>{{ $transaction->reference }}</h2>
        <div class="kv">
            <span class="muted">Transaction ID</span><code>{{ $transaction->id }}</code>
            <span class="muted">Customer</span><span>{{ $transaction->initiator?->email }}</span>
            <span class="muted">Type</span><span>{{ $transaction->type->value }}</span>
            <span class="muted">Status</span><span><span class="badge {{ $transaction->status->value }}">{{ $transaction->status->value }}</span></span>
            <span class="muted">Amount</span><span class="amount">{{ $transaction->currency->formatMinor($transaction->amount_minor) }}</span>
            <span class="muted">Source wallet</span><code>{{ $transaction->source_wallet_id ?? '—' }}</code>
            <span class="muted">Destination wallet</span><code>{{ $transaction->destination_wallet_id ?? '—' }}</code>
            <span class="muted">Created</span><span>{{ $transaction->created_at }}</span>
        </div>
        @if($transaction->ledgerTransaction)
            <p style="margin-top:20px"><a class="button" style="display:inline-flex;align-items:center" href="{{ route('admin.ledger.show', $transaction->ledgerTransaction) }}">Open ledger journal</a></p>
        @endif
    </section>
    <section class="card">
        <h2>Lifecycle</h2>
        @foreach($transaction->statusHistory as $history)
            <div style="padding:10px 0;border-bottom:1px solid #e5e7eb"><span class="badge {{ $history->to_status->value }}">{{ $history->to_status->value }}</span><div class="muted">{{ $history->created_at }} {{ $history->reason }}</div></div>
        @endforeach
    </section>
</div>
@endsection
