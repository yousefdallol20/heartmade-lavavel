<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class Partner extends Model
{
    protected $fillable = [
        'name',
        'url',
        'image',
    ];

    public function getImageAttribute(string $value): ?string
    {
        if (! $value) {
            return null;
        }

        // إرجاع رابط الصورة الخارجي كما هو
        if (preg_match('~^https?://~i', $value)) {
            return $value;
        }

        // توضيح نوع قرص التخزين للمحرر
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($value);
    }
}
