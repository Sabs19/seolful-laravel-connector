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

    /**
     * Classify this page as 'content', 'legal', or 'utility' using the same
     * slug-pattern and word-count rules as the WordPress plugin.
     */
    public function getPageRole(): string
    {
        $slug = strtolower(trim($this->slug ?? '', '/'));
        $lastSegment = basename($slug);

        $legalExact = ['privacy', 'terms', 'tos', 'cookie', 'cookies', 'disclaimer', 'legal', 'refund', 'copyright', 'cancellation'];
        if (in_array($lastSegment, $legalExact, true)) {
            return 'legal';
        }

        $legalSubstring = [
            'privacy-policy', 'cookie-policy', 'terms-of-service', 'terms-and-conditions',
            'return-policy', 'refund-policy', 'cancellation-policy',
            'gdpr', 'dmca', 'accessibility',
        ];
        foreach ($legalSubstring as $pattern) {
            if (str_contains($lastSegment, $pattern)) {
                return 'legal';
            }
        }

        $utilityExact = ['contact', 'contact-us', 'about', 'about-us', 'faq', 'faqs', 'search', 'sitemap'];
        if (in_array($lastSegment, $utilityExact, true)) {
            return 'utility';
        }

        $utilitySubstring = [
            'cart', 'checkout', 'my-account', 'wishlist', 'order-received',
            'login', 'log-in', 'register', 'sign-in', 'sign-up', 'signup',
            'lost-password', 'reset-password', 'forgot-password',
            'thank-you', 'thankyou',
            'coming-soon', 'maintenance', '404',
        ];
        foreach ($utilitySubstring as $pattern) {
            if (str_contains($lastSegment, $pattern)) {
                return 'utility';
            }
        }

        if ((int) ($this->word_count ?? 0) < 50) {
            return 'utility';
        }

        return 'content';
    }
}
