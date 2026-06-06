<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    use Auditable;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body',
        'cover_image_url',
        'is_published',
        'published_at',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function scopePublished($query)
    {
        return $query
            ->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }
}
