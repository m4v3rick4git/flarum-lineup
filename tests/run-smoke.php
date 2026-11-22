<?php

declare(strict_types=1);

require __DIR__.'/../src/Lineup/DisplayName.php';
require __DIR__.'/../src/Image/GeneratedImageReferenceExtractor.php';
require __DIR__.'/../src/Lineup/FormationCatalog.php';
require __DIR__.'/../src/Sync/SyncSafetyGuard.php';

use Wss\FlarumLineup\Lineup\DisplayName;
use Wss\FlarumLineup\Image\GeneratedImageReferenceExtractor;

$cases = [
    'Haris Tabakovic' => 'Tabakovic',
    'Lucas Gourna-Douath' => 'Gourna-Douath',
    'Ignace Van der Brempt' => 'Brempt',
    'Fernando' => 'Fernando',
    '  Maurits   Kjaergaard  ' => 'Kjaergaard',
    'Jean-Philippe Krasso' => 'Krasso',
];
foreach ($cases as $input => $expected) {
    if (DisplayName::fromFullName($input) !== $expected) {
        fwrite(STDERR, "DisplayName failed: {$input}\n");
        exit(1);
    }
}
$old = str_repeat('a', 40).'.png';
$new = str_repeat('b', 40).'.png';
$references = (new GeneratedImageReferenceExtractor())->extract(
    "![old](/assets/wss-lineup/{$old}) ![new](/assets/wss-lineup-2/{$new})"
);
if ($references !== [$old, $new]) {
    fwrite(STDERR, "Image reference extraction failed\n");
    exit(1);
}
$catalog = new \Wss\FlarumLineup\Lineup\FormationCatalog();
$formations = ['4-2-3-1','4-3-3','4-4-2','3-5-2','3-4-3','5-3-2'];
foreach ($formations as $formation) {
    if (!$catalog->exists($formation) || count($catalog->slots($formation)) !== 11) {
        fwrite(STDERR, "Invalid formation: {$formation}\n"); exit(1);
    }
}
$guard = new \Wss\FlarumLineup\Sync\SyncSafetyGuard();
try {
    $guard->assertSquadResponseIsComplete(3, 20);
    fwrite(STDERR, "Safety guard incorrectly accepted a 3-player squad\n"); exit(1);
} catch (\RuntimeException) {
    // expected
}
echo "PASS: ".count($cases)." names, 2 image link patterns, ".count($formations)." formations, sync guard\n";
