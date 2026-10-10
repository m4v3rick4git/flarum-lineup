<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Console;

use Flarum\Console\AbstractCommand;
use Psr\Log\LoggerInterface;
use Wss\FlarumLineup\Image\RemoteImageCacheService;
use Wss\FlarumLineup\Model\Player;

final class RefreshBundesligaPlayerImagesCommand extends AbstractCommand
{
    private const PROVIDER = 'bundesliga-at';

    private RemoteImageCacheService $imageCache;

    private LoggerInterface $logger;

    public function __construct(
        RemoteImageCacheService $imageCache,
        LoggerInterface $logger
    ) {
        $this->imageCache = $imageCache;
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName(
                'wss-lineup:refresh-bundesliga-player-images'
            )
            ->setDescription(
                'Refresh and normalize all active Bundesliga.at player images.'
            );
    }

    protected function fire()
    {
        $players = Player::query()
            ->where('provider', self::PROVIDER)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $total = $players->count();

        $refreshed = 0;
        $remotePhotos = 0;
        $localPlaceholders = 0;
        $cleanedPlaceholderUrls = 0;
        $failed = 0;

        foreach ($players as $index => $player) {
            $photoUrl = is_string($player->photo_url)
                ? trim($player->photo_url)
                : null;

            if ($photoUrl === '') {
                $photoUrl = null;
            }

            if (
                $this->isOfficialBundesligaPlaceholder(
                    $photoUrl
                )
            ) {
                $photoUrl = null;

                $player->photo_url = null;
                $player->save();

                ++$cleanedPlaceholderUrls;
            }

            $usesLocalPlaceholder =
                $photoUrl === null;

            $result = $this->imageCache
                ->cachePlayerPhoto(
                    self::PROVIDER,
                    (string) $player->provider_player_id,
                    $photoUrl,
                    true
                );

            if ($result === null) {
                ++$failed;

                $message = sprintf(
                    '[%d/%d] Image refresh failed for %s.',
                    $index + 1,
                    $total,
                    $player->name
                );

                $this->logger->warning(
                    $message,
                    [
                        'provider' => self::PROVIDER,
                        'providerPlayerId' => (
                            (string) $player->provider_player_id
                        ),
                        'photoUrl' => $photoUrl,
                        'usesLocalPlaceholder' => (
                            $usesLocalPlaceholder
                        ),
                    ]
                );

                $this->error($message);

                continue;
            }

            ++$refreshed;

            if ($usesLocalPlaceholder) {
                ++$localPlaceholders;

                $this->info(
                    sprintf(
                        '[%d/%d] Local placeholder created for %s.',
                        $index + 1,
                        $total,
                        $player->name
                    )
                );
            } else {
                ++$remotePhotos;
            }
        }

        $summary = sprintf(
            (
                'Bundesliga.at player-image refresh completed. '
                .'Players: %d; refreshed: %d; failed: %d.'
            ),
            $total,
            $refreshed,
            $failed
        );

        $this->logger->info($summary);
        $this->info($summary);

        return $failed > 0
            ? 1
            : 0;
    }

    private function isOfficialBundesligaPlaceholder(
        ?string $photoUrl
    ): bool {
        if ($photoUrl === null) {
            return false;
        }

        $decoded = trim($photoUrl);

        /*
         * Bundesliga.at can wrap the placeholder in a Next.js
         * image URL and URL-encode it more than once.
         */
        for ($iteration = 0; $iteration < 5; ++$iteration) {
            $next = html_entity_decode(
                $decoded,
                ENT_QUOTES | ENT_HTML5
            );

            $next = rawurldecode($next);

            if ($next === $decoded) {
                break;
            }

            $decoded = $next;
        }

        return stripos(
            $decoded,
            'portrait_placeholder.png'
        ) !== false;
    }
}
