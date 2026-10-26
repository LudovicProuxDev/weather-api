<?php

namespace App\Models;

use Database\Factories\SavedLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $city_id
 * @property string $name
 * @property string|null $region
 * @property string|null $department
 * @property string|null $country
 * @property float $latitude
 * @property float $longitude
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['city_id', 'name', 'region', 'department', 'country', 'latitude', 'longitude'])]
class SavedLocation extends Model
{
    /** @use HasFactory<SavedLocationFactory> */
    use HasFactory;

    /**
     * The maximum number of locations a user can save.
     */
    public const int MAX_PER_USER = 20;

    /**
     * Get the user who saved the location.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'city_id' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }
}
