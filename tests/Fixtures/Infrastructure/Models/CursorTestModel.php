<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Primary key `account_id` on purpose: the read model exposes it as `id`, which
 * is exactly the mismatch the cursor paginator exists to bridge.
 */
final class CursorTestModel extends Model
{
    protected $table = 'cursor_test_items';

    protected $primaryKey = 'account_id';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return BelongsTo<CursorTestOwnerModel, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(CursorTestOwnerModel::class, 'owner_id');
    }
}
