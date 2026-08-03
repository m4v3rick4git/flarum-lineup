<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $api_team_id
 * @property string $name
 * @property string|null $code
 * @property string|null $country
 * @property int|null $founded
 * @property bool $is_national
 * @property string|null $logo_url
 * @property bool $is_active
 * @property \Carbon\Carbon|null $last_synced_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
final class Team extends AbstractModel
{
    protected $table = 'wss_lineup_teams';

    protected $fillable = [
        'api_team_id',
        'name',
        'code',
        'country',
        'founded',
        'is_national',
        'logo_url',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'api_team_id' => 'integer',
        'founded' => 'integer',
        'is_national' => 'boolean',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function players(): HasMany
    {
        return $this->hasMany(
            Player::class,
            'team_id'
        );
    }
}
