<?php

namespace Tests\Support;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimal tenant-scoped model used only to exercise App\Models\Concerns\BelongsToStore
 * in tests, before any real tenant-scoped business table exists (see Milestone 2).
 */
class TestWidget extends Model
{
    use BelongsToStore;

    protected $table = 'test_widgets';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'name', 'store_id'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (! $model->id) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }
}
