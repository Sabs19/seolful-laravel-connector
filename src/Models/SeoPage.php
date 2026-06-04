<?php

namespace Seolful\Connector\Models;

use Illuminate\Database\Eloquent\Model;

class SeoPage extends Model
{
    protected $table = 'seolful_seo_pages';

    protected $fillable = [
        'url',
        'slug',
        'title',
        'meta_description',
        'h1',
        'h1_count',
        'h1_secondary',
        'demote_h1',
        'word_count',
        'image_alts',
        'internal_link_count',
        'structured_data',
        'noindex',
        'canonical_url',
        'crawled_at',
    ];

    protected $casts = [
        'image_alts'      => 'array',
        'structured_data' => 'array',
        'noindex'         => 'boolean',
        'demote_h1'       => 'boolean',
        'crawled_at'      => 'datetime',
    ];
}
