<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'name',
        'price',
        'stock',
        'subtitle',
        'category',
        'description',
        'dimensions',
        'whats_included',
        'image_url',
        'images',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'images' => 'array',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    private function publicImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        // Preserve external image URLs.
        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        // Convert a local image path to a public URL.
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($path);
    }

    public function getImageUrlAttribute(?string $value): ?string
    {
        return $this->publicImageUrl($value);
    }

    public function getImagesAttribute($value): array
    {
        // Normalize image values before generating public URLs.
        $paths = is_array($value)
            ? $value
            : json_decode($value ?? '[]', true);

        if (! is_array($paths)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($path) => is_string($path)
                ? $this->publicImageUrl($path)
                : null,
            $paths
        )));
    }
}
