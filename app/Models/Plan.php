<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Plan extends Model
{
    protected $fillable = [
        'key',
        'name',
        'price_monthly',
        'price_label',
        'period',
        'description',
        'cta',
        'featured',
        'is_active',
        'highlights',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'integer',
            'featured' => 'boolean',
            'is_active' => 'boolean',
            'highlights' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'feature_plan', 'plan_id', 'plan_feature_id')
            ->orderBy('plan_features.sort_order');
    }
}
