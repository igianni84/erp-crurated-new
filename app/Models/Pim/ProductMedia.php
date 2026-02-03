<?php

namespace App\Models\Pim;

use App\Enums\DataSource;
use App\Traits\Auditable;
use App\Traits\AuditLoggable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * @property DataSource $source
 */
class ProductMedia extends Model
{
    use Auditable;
    use AuditLoggable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'product_media';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'wine_variant_id',
        'type',
        'source',
        'file_path',
        'external_url',
        'original_filename',
        'mime_type',
        'file_size',
        'alt_text',
        'caption',
        'is_primary',
        'sort_order',
        'is_locked',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
            'is_locked' => 'boolean',
            'source' => DataSource::class,
        ];
    }

    /**
     * Get the wine variant that owns this media.
     *
     * @return BelongsTo<WineVariant, $this>
     */
    public function wineVariant(): BelongsTo
    {
        return $this->belongsTo(WineVariant::class);
    }

    /**
     * Get the URL for this media.
     */
    public function getUrl(): ?string
    {
        if ($this->external_url !== null) {
            return $this->external_url;
        }

        if ($this->file_path !== null) {
            return Storage::disk('public')->url($this->file_path);
        }

        return null;
    }

    /**
     * Check if this is a Liv-ex media item.
     */
    public function isFromLivEx(): bool
    {
        return $this->source === DataSource::LivEx;
    }

    /**
     * Check if this is an image.
     */
    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    /**
     * Check if this is a document.
     */
    public function isDocument(): bool
    {
        return $this->type === 'document';
    }

    /**
     * Check if this can be edited.
     */
    public function isEditable(): bool
    {
        return ! $this->is_locked && $this->source !== DataSource::LivEx;
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedFileSize(): string
    {
        if ($this->file_size === null) {
            return 'Unknown';
        }

        $size = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2).' '.$units[$i];
    }

    /**
     * Scope to get primary images.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ProductMedia>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ProductMedia>
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to get images only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ProductMedia>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ProductMedia>
     */
    public function scopeImages($query)
    {
        return $query->where('type', 'image');
    }

    /**
     * Scope to get documents only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ProductMedia>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ProductMedia>
     */
    public function scopeDocuments($query)
    {
        return $query->where('type', 'document');
    }

    /**
     * Scope to get Liv-ex media.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ProductMedia>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ProductMedia>
     */
    public function scopeLivEx($query)
    {
        return $query->where('source', 'liv_ex');
    }

    /**
     * Scope to get manual uploads.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ProductMedia>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ProductMedia>
     */
    public function scopeManual($query)
    {
        return $query->where('source', 'manual');
    }

    /**
     * Scope to order by sort order.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ProductMedia>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ProductMedia>
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // When setting a new primary image, unset others
        static::saving(function (ProductMedia $media): void {
            if ($media->is_primary && $media->isDirty('is_primary')) {
                // Unset other primary images for this wine variant
                ProductMedia::where('wine_variant_id', $media->wine_variant_id)
                    ->where('type', 'image')
                    ->where('id', '!=', $media->id ?? '')
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }
        });

        // When deleting, remove the file from storage
        static::deleting(function (ProductMedia $media): void {
            if ($media->file_path !== null && $media->source !== DataSource::LivEx) {
                Storage::disk('public')->delete($media->file_path);
            }
        });
    }
}
