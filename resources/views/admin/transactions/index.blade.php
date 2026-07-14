@extends('admin.layout')
@section('title', 'Transactions')
@section('heading', 'Transaction monitoring')
@section('content')
<form class="card filters" method="get">
    <input class="input" name="q" value="{{ request('q') }}" placeholder="ID, reference, or email">
    <select name="status"><option value="">All statuses</option>@foreach(['created','pending','processing','completed','failed','reversed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
    <select name="type"><option value="">All types</option>@foreach(['transfer','deposit','withdrawal','refund'] as $type)<option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst($type) }}</option>@endforeach</select>
    <input class="input" name="currency" value="{{ request('currency') }}" maxlength="3" placeholder="Currency">
    <input class="input" type="date" name="date_from" value="{{ request('date_from') }}">
    <input class="input" type="date" name="date_to" value="{{ request('date_to') }}">
    <button class="button">Search</button>
</form>
<div class="table-card">
    <table>
        <thead><tr><th>Reference</th><th>Customer</th><th>Type</th><th>Status</th><th>Amount</th><th>Created</th></tr></thead>
        <tbody>
        @forelse($transactions as $transaction)
            <tr>
                <td><a href="{{ route('admin.transactions.show', $transaction) }}"><strong>{{ $transaction->reference }}</strong></a><div class="muted">{{ $transaction->id }}</div></td>
                <td>{{ $transaction->initiator?->email }}</td>
                <td>{{ $transaction->type->value }}</td>
                <td><span class="badge {{ $transaction->status->value }}">{{ $transaction->status->value }}</span></td>
                <td class="amount">{{ $transaction->currency->formatMinor($transaction->amount_minor) }}</td>
                <td>{{ $transaction->created_at->format('Y-m-d H:i:s') }}</td>
            </tr>
        @empty
            <tr><td colspan="6">No transactions match the filters.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div style="margin-top:16px">{{ $transactions->links() }}</div>
@endsection
