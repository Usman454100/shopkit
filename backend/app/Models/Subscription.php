<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasUuid;

    protected $fillable = [
        'organization_id',
        'plan_id',
        'status',
        'started_at',
        'current_period_end',
        'next_billing_date',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'current_period_end' => 'datetime',
            'next_billing_date' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
}
