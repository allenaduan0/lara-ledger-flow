<?php

namespace App\Modules\Wallet\Presentation\Http\Resources;

use App\Modules\Wallet\Domain\Contracts\WalletBalanceReader;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $balance = app(WalletBalanceReader::class)->forWallet($this->id, $this->currency_code);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'currency' => ['code' => $this->currency_code, 'name' => $this->currency?->name, 'minor_unit' => $this->currency?->minor_unit],
            'status' => $this->status->value,
            'balance' => ['posted_minor' => $balance->postedMinor, 'available_minor' => $balance->availableMinor, 'currency' => $balance->currency, 'source' => $balance->source],
            'limits' => $this->whenLoaded('limit', fn () => [
                'per_transaction_minor' => $this->limit?->per_transaction_minor,
                'daily_outgoing_minor' => $this->limit?->daily_outgoing_minor,
                'monthly_outgoing_minor' => $this->limit?->monthly_outgoing_minor,
            ]),
            'frozen_at' => $this->frozen_at,
            'freeze_reason' => $this->freeze_reason,
            'created_at' => $this->created_at,
        ];
    }
}
