<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property string $provider
 * @property string $provider_player_id
 * @property string $name
 * @property int|null $age
 * @property int|null $shirt_number
 * @property string|null $position
 * @property string|null $photo_url
 * @property bool $is_active
 * @property \Carbon\Carbon|null $last_synced_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property Team $team
 */
final class Player extends AbstractModel
{
    protected $table = 'wss_lineup_players';

    protected $fillable = [
        'team_id',
        'provider',
        'provider_player_id',
        'name',
        'age',
        'shirt_number',
        'position',
        'photo_url',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'team_id' => 'integer',
        'age' => 'integer',
        'shirt_number' => 'integer',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(
            Team::class,
            'team_id'
        );
    }
}
