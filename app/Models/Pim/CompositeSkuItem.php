<?php

namespace App\Models\Pim;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompositeSkuItem extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'composite_sku_id',
        'sellable_sku_id',
        'quantity',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * Get the composite SKU (the bundle/parent).
     *
     * @return BelongsTo<SellableSku, $this>
     */
    public function compositeSku(): BelongsTo
    {
        return $this->belongsTo(SellableSku::class, 'composite_sku_id');
    }

    /**
     * Get the component SKU (the item in the bundle).
     *
     * @return BelongsTo<SellableSku, $this>
     */
    public function sellableSku(): BelongsTo
    {
        return $this->belongsTo(SellableSku::class, 'sellable_sku_id');
    }

    /**
     * Alias for sellableSku() for clearer naming.
     *
     * @return BelongsTo<SellableSku, $this>
     */
    public function componentSku(): BelongsTo
    {
        return $this->sellableSku();
    }
}
