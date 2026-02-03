<?php

namespace App\Models\Pim;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseConfiguration extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'format_id',
        'bottles_per_case',
        'case_type',
        'is_original_from_producer',
        'is_breakable',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bottles_per_case' => 'integer',
            'is_original_from_producer' => 'boolean',
            'is_breakable' => 'boolean',
        ];
    }

    /**
     * Get the format that this case configuration belongs to.
     */
    public function format(): BelongsTo
    {
        return $this->belongsTo(Format::class);
    }
}
