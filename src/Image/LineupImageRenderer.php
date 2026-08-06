<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

use GdImage;
use InvalidArgumentException;
use RuntimeException;
use Wss\FlarumLineup\Lineup\FormationCatalog;

final class LineupImageRenderer
{
    private const WIDTH = 1200;

    private const HEIGHT = 1600;

    private const HEADER_HEIGHT = 150;

    private const PITCH_LEFT = 60;

    private const PITCH_TOP = 150;

    private const PITCH_RIGHT = 1140;

    private const PITCH_BOTTOM = 1550;

    private const PLAYER_PHOTO_SIZE = 88;

    private const FONT_REGULAR =
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

    private const FONT_BOLD =
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    private FormationCatalog $formationCatalog;

    private CachedImageAccess $cachedImageAccess;

    public function __construct(
        FormationCatalog $formationCatalog,
        CachedImageAccess $cachedImageAccess
    ) {
        $this->formationCatalog = $formationCatalog;
        $this->cachedImageAccess = $cachedImageAccess;
    }

    /**
     * @param array<int, array{
     *     name: string,
     *     shirtNumber: int|null,
     *     photoUrl: string|null
     * }> $players
     */
    public function render(
        string $outputPath,
        string $teamName,
        ?string $teamLogoUrl,
        string $formation,
        array $players
    ): void {
        if (count($players) !== 11) {
            throw new InvalidArgumentException(
                'Exactly eleven players are required.'
            );
        }

        $slots = $this->formationCatalog->slots(
            $formation
        );

        $this->ensureOutputDirectory($outputPath);
        $this->ensureFontsExist();

        $image = imagecreatetruecolor(
            self::WIDTH,
            self::HEIGHT
        );

        if (!$image instanceof GdImage) {
            throw new RuntimeException(
                'The image resource could not be created.'
            );
        }

        try {
            $colors = $this->allocateColors($image);

            $this->drawBackground(
                $image,
                $colors
            );

            $this->drawHeader(
                $image,
                $teamName,
                $teamLogoUrl,
                $formation,
                $colors
            );

            $this->drawPitch(
                $image,
                $colors
            );

            foreach ($players as $index => $player) {
                $slot = $slots[$index] ?? null;

                if ($slot === null) {
                    throw new RuntimeException(
                        'The formation contains too few slots.'
                    );
                }

                $this->drawPlayer(
                    $image,
                    $player,
                    $slot['x'],
                    $slot['y'],
                    $colors
                );
            }

            if (!imagepng($image, $outputPath, 6)) {
                throw new RuntimeException(
                    'The PNG file could not be written.'
                );
            }
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * @return array<string, int>
     */
    private function allocateColors(
        GdImage $image
    ): array {
        return [
            'background' => imagecolorallocate(
                $image,
                245,
                247,
                250
            ),
            'header' => imagecolorallocate(
                $image,
                231,
                236,
                243
            ),
            'text' => imagecolorallocate(
                $image,
                22,
                28,
                36
            ),
            'muted' => imagecolorallocate(
                $image,
                83,
                96,
                112
            ),
            'pitch' => imagecolorallocate(
                $image,
                48,
                139,
                65
            ),
            'pitchDark' => imagecolorallocate(
                $image,
                39,
                123,
                56
            ),
            'white' => imagecolorallocate(
                $image,
                255,
                255,
                255
            ),
            'label' => imagecolorallocatealpha(
                $image,
                6,
                18,
                10,
                18
            ),
            'fallback' => imagecolorallocate(
                $image,
                42,
                75,
                50
            ),
            'shadow' => imagecolorallocatealpha(
                $image,
                0,
                0,
                0,
                75
            ),
        ];
    }

    /**
     * @param array<string, int> $colors
     */
    private function drawBackground(
        GdImage $image,
        array $colors
    ): void {
        imagefilledrectangle(
            $image,
            0,
            0,
            self::WIDTH,
            self::HEIGHT,
            $colors['background']
        );
    }

    /**
     * @param array<string, int> $colors
     */
    private function drawHeader(
        GdImage $image,
        string $teamName,
        ?string $teamLogoUrl,
        string $formation,
        array $colors
    ): void {
        imagefilledrectangle(
            $image,
            0,
            0,
            self::WIDTH,
            self::HEADER_HEIGHT,
            $colors['header']
        );

        $textX = 55;

        $logo = $this->cachedImageAccess->load(
            $teamLogoUrl
        );

        if ($logo instanceof GdImage) {
            $this->copyImageContained(
                $image,
                $logo,
                40,
                25,
                95,
                95
            );

            imagedestroy($logo);

            $textX = 155;
        }

        $fittedTeamName = $this->fitText(
            $teamName,
            31,
            self::FONT_BOLD,
            700
        );

        imagettftext(
            $image,
            31,
            0,
            $textX,
            75,
            $colors['text'],
            self::FONT_BOLD,
            $fittedTeamName
        );

        imagettftext(
            $image,
            17,
            0,
            $textX,
            111,
            $colors['muted'],
            self::FONT_REGULAR,
            'Mannschaftsaufstellung'
        );

        imagefilledrectangle(
            $image,
            980,
            47,
            1140,
            103,
            $colors['white']
        );

        $this->drawCenteredText(
            $image,
            $formation,
            20,
            self::FONT_BOLD,
            1060,
            83,
            $colors['text']
        );
    }

    /**
     * @param array<string, int> $colors
     */
    private function drawPitch(
        GdImage $image,
        array $colors
    ): void {
        $left = self::PITCH_LEFT;
        $top = self::PITCH_TOP;
        $right = self::PITCH_RIGHT;
        $bottom = self::PITCH_BOTTOM;

        $width = $right - $left;
        $height = $bottom - $top;
        $stripeWidth = (int) ($width / 10);

        imagefilledrectangle(
            $image,
            $left,
            $top,
            $right,
            $bottom,
            $colors['pitch']
        );

        for ($index = 0; $index < 10; ++$index) {
            if ($index % 2 === 0) {
                continue;
            }

            imagefilledrectangle(
                $image,
                $left + ($index * $stripeWidth),
                $top,
                min(
                    $right,
                    $left + (($index + 1) * $stripeWidth)
                ),
                $bottom,
                $colors['pitchDark']
            );
        }

        imagesetthickness($image, 3);

        imagerectangle(
            $image,
            $left,
            $top,
            $right,
            $bottom,
            $colors['white']
        );

        $centreY = $top + (int) ($height / 2);

        imageline(
            $image,
            $left,
            $centreY,
            $right,
            $centreY,
            $colors['white']
        );

        imageellipse(
            $image,
            $left + (int) ($width / 2),
            $centreY,
            190,
            190,
            $colors['white']
        );

        imagefilledellipse(
            $image,
            $left + (int) ($width / 2),
            $centreY,
            9,
            9,
            $colors['white']
        );

        $penaltyWidth = 650;
        $penaltyHeight = 245;
        $goalWidth = 330;
        $goalHeight = 105;
        $centreX = $left + (int) ($width / 2);

        imagerectangle(
            $image,
            $centreX - (int) ($penaltyWidth / 2),
            $top,
            $centreX + (int) ($penaltyWidth / 2),
            $top + $penaltyHeight,
            $colors['white']
        );

        imagerectangle(
            $image,
            $centreX - (int) ($goalWidth / 2),
            $top,
            $centreX + (int) ($goalWidth / 2),
            $top + $goalHeight,
            $colors['white']
        );

        imagerectangle(
            $image,
            $centreX - (int) ($penaltyWidth / 2),
            $bottom - $penaltyHeight,
            $centreX + (int) ($penaltyWidth / 2),
            $bottom,
            $colors['white']
        );

        imagerectangle(
            $image,
            $centreX - (int) ($goalWidth / 2),
            $bottom - $goalHeight,
            $centreX + (int) ($goalWidth / 2),
            $bottom,
            $colors['white']
        );

        imagesetthickness($image, 1);
    }

    /**
     * @param array{
     *     name: string,
     *     shirtNumber: int|null,
     *     photoUrl: string|null
     * } $player
     * @param array<string, int> $colors
     */
    private function drawPlayer(
        GdImage $image,
        array $player,
        int $slotX,
        int $slotY,
        array $colors
    ): void {
        $pitchWidth =
            self::PITCH_RIGHT - self::PITCH_LEFT;

        $pitchHeight =
            self::PITCH_BOTTOM - self::PITCH_TOP;

        $x = self::PITCH_LEFT
            + (int) round(
                $pitchWidth * ($slotX / 100)
            );

        $y = self::PITCH_TOP
            + (int) round(
                $pitchHeight * ($slotY / 100)
            );

        imagefilledellipse(
            $image,
            $x + 4,
            $y + 5,
            self::PLAYER_PHOTO_SIZE + 8,
            self::PLAYER_PHOTO_SIZE + 8,
            $colors['shadow']
        );

        imagefilledellipse(
            $image,
            $x,
            $y,
            self::PLAYER_PHOTO_SIZE + 8,
            self::PLAYER_PHOTO_SIZE + 8,
            $colors['white']
        );

        $photo = $this->cachedImageAccess->load(
            $player['photoUrl']
        );

        if ($photo instanceof GdImage) {
            $circle = $this->createCircularThumbnail(
                $photo,
                self::PLAYER_PHOTO_SIZE
            );

            imagedestroy($photo);

            imagecopy(
                $image,
                $circle,
                $x - (int) (self::PLAYER_PHOTO_SIZE / 2),
                $y - (int) (self::PLAYER_PHOTO_SIZE / 2),
                0,
                0,
                self::PLAYER_PHOTO_SIZE,
                self::PLAYER_PHOTO_SIZE
            );

            imagedestroy($circle);
        } else {
            imagefilledellipse(
                $image,
                $x,
                $y,
                self::PLAYER_PHOTO_SIZE,
                self::PLAYER_PHOTO_SIZE,
                $colors['fallback']
            );

            $number = $player['shirtNumber'] !== null
                ? (string) $player['shirtNumber']
                : '–';

            $this->drawCenteredText(
                $image,
                $number,
                22,
                self::FONT_BOLD,
                $x,
                $y + 8,
                $colors['white']
            );
        }

        $name = $this->fitText(
            $player['name'],
            15,
            self::FONT_BOLD,
            155
        );

        $nameWidth = $this->textWidth(
            $name,
            15,
            self::FONT_BOLD
        );

        $labelLeft = $x - (int) ($nameWidth / 2) - 9;
        $labelRight = $x + (int) ($nameWidth / 2) + 9;
        $labelTop = $y + 50;
        $labelBottom = $y + 78;

        imagefilledrectangle(
            $image,
            $labelLeft,
            $labelTop,
            $labelRight,
            $labelBottom,
            $colors['label']
        );

        $this->drawCenteredText(
            $image,
            $name,
            15,
            self::FONT_BOLD,
            $x,
            $y + 70,
            $colors['white']
        );

        $shirtNumber = $player['shirtNumber'] !== null
            ? '#'.$player['shirtNumber']
            : '#–';

        $this->drawCenteredText(
            $image,
            $shirtNumber,
            12,
            self::FONT_BOLD,
            $x,
            $y + 97,
            $colors['white']
        );
    }

    private function createCircularThumbnail(
        GdImage $source,
        int $size
    ): GdImage {
        $thumbnail = imagecreatetruecolor(
            $size,
            $size
        );

        if (!$thumbnail instanceof GdImage) {
            throw new RuntimeException(
                'The player thumbnail could not be created.'
            );
        }

        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);

        $transparent = imagecolorallocatealpha(
            $thumbnail,
            0,
            0,
            0,
            127
        );

        imagefill(
            $thumbnail,
            0,
            0,
            $transparent
        );

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $sourceSize = min(
            $sourceWidth,
            $sourceHeight
        );

        $sourceX = (int) (
            ($sourceWidth - $sourceSize) / 2
        );

        $sourceY = (int) (
            ($sourceHeight - $sourceSize) / 2
        );

        imagecopyresampled(
            $thumbnail,
            $source,
            0,
            0,
            $sourceX,
            $sourceY,
            $size,
            $size,
            $sourceSize,
            $sourceSize
        );

        $radius = $size / 2;
        $centre = ($size - 1) / 2;

        for ($y = 0; $y < $size; ++$y) {
            for ($x = 0; $x < $size; ++$x) {
                $distance = sqrt(
                    (($x - $centre) ** 2)
                    + (($y - $centre) ** 2)
                );

                if ($distance > $radius) {
                    imagesetpixel(
                        $thumbnail,
                        $x,
                        $y,
                        $transparent
                    );
                }
            }
        }

        imagealphablending($thumbnail, true);

        return $thumbnail;
    }

    private function copyImageContained(
        GdImage $destination,
        GdImage $source,
        int $x,
        int $y,
        int $width,
        int $height
    ): void {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        $scale = min(
            $width / $sourceWidth,
            $height / $sourceHeight
        );

        $targetWidth = max(
            1,
            (int) round($sourceWidth * $scale)
        );

        $targetHeight = max(
            1,
            (int) round($sourceHeight * $scale)
        );

        $targetX = $x
            + (int) (($width - $targetWidth) / 2);

        $targetY = $y
            + (int) (($height - $targetHeight) / 2);

        imagecopyresampled(
            $destination,
            $source,
            $targetX,
            $targetY,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );
    }

    private function drawCenteredText(
        GdImage $image,
        string $text,
        int $size,
        string $font,
        int $centreX,
        int $baselineY,
        int $color
    ): void {
        $width = $this->textWidth(
            $text,
            $size,
            $font
        );

        imagettftext(
            $image,
            $size,
            0,
            $centreX - (int) ($width / 2),
            $baselineY,
            $color,
            $font,
            $text
        );
    }

    private function fitText(
        string $text,
        int $size,
        string $font,
        int $maximumWidth
    ): string {
        if (
            $this->textWidth(
                $text,
                $size,
                $font
            ) <= $maximumWidth
        ) {
            return $text;
        }

        $length = mb_strlen($text);

        while ($length > 1) {
            --$length;

            $candidate = mb_substr(
                $text,
                0,
                $length
            ).'…';

            if (
                $this->textWidth(
                    $candidate,
                    $size,
                    $font
                ) <= $maximumWidth
            ) {
                return $candidate;
            }
        }

        return '…';
    }

    private function textWidth(
        string $text,
        int $size,
        string $font
    ): int {
        $box = imagettfbbox(
            $size,
            0,
            $font,
            $text
        );

        if ($box === false) {
            return 0;
        }

        return abs($box[2] - $box[0]);
    }

    private function ensureOutputDirectory(
        string $outputPath
    ): void {
        $directory = dirname($outputPath);

        if (is_dir($directory)) {
            return;
        }

        if (
            !mkdir($directory, 0775, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'The output directory could not be created.'
            );
        }
    }

    private function ensureFontsExist(): void
    {
        if (
            !is_file(self::FONT_REGULAR)
            || !is_file(self::FONT_BOLD)
        ) {
            throw new RuntimeException(
                'Required font files are missing.'
            );
        }
    }
}
