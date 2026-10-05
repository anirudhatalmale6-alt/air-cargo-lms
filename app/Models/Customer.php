<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code', 'name', 'contact_person', 'email', 'phone',
    'address_line1', 'address_line2', 'city', 'state', 'postcode', 'country',
    'tax_reference', 'credit_days', 'credit_limit', 'default_currency', 'is_active', 'notes',
])]
class Customer extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $like = '%'.trim($term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)
                ->orWhere('code', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('city', 'like', $like);
        });
    }

    /** Next code in the CUS-0001 series, used when the form leaves it blank. */
    public static function nextCode(): string
    {
        $last = static::withTrashed()
            ->where('code', 'like', 'CUS-%')
            ->orderByDesc('id')
            ->value('code');

        $n = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'CUS-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public function addressLines(): array
    {
        return array_values(array_filter([
            $this->address_line1,
            $this->address_line2,
            trim(implode(' ', array_filter([$this->city, $this->state, $this->postcode]))),
            $this->country,
        ]));
    }
}
