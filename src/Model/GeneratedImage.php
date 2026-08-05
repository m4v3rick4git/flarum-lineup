<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Model;

use Flarum\Database\AbstractModel;

/**
 * @property int $id
 * @property int $actor_id
 * @property int|null $post_id
 * @property string $filename
 * @property string $relative_path
 * @property string $status
 * @property int|null $size_bytes
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $claimed_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
final class GeneratedImage extends AbstractModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_CLAIMED = 'claimed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'wss_lineup_generated_images';

    protected $fillable = [
        'actor_id',
        'post_id',
        'filename',
        'relative_path',
        'status',
        'size_bytes',
        'expires_at',
        'claimed_at',
    ];

    protected $casts = [
        'actor_id' => 'integer',
        'post_id' => 'integer',
        'size_bytes' => 'integer',
        'expires_at' => 'datetime',
        'claimed_at' => 'datetime',
    ];
}
