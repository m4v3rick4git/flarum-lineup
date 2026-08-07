<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wss\FlarumLineup\Image\GeneratedImageReferenceExtractor;

final class GeneratedImageReferenceExtractorTest extends TestCase
{
    private GeneratedImageReferenceExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor =
            new GeneratedImageReferenceExtractor();
    }

    public function testItExtractsAnAbsoluteImageUrl(): void
    {
        $filename = str_repeat('a', 40).'.png';

        $content = sprintf(
            '![Lineup](https://forum.example.test/assets/wss-lineup/%s)',
            $filename
        );

        $this->assertSame(
            [$filename],
            $this->extractor->extract($content)
        );
    }

    public function testItExtractsARelativeImageUrl(): void
    {
        $filename = str_repeat('b', 40).'.png';

        $content = sprintf(
            '![Lineup](/assets/wss-lineup/%s)',
            $filename
        );

        $this->assertSame(
            [$filename],
            $this->extractor->extract($content)
        );
    }

    public function testItRemovesDuplicateReferences(): void
    {
        $filename = str_repeat('c', 40).'.png';

        $content = sprintf(
            '%1$s /assets/wss-lineup/%1$s',
            $filename
        );

        $this->assertSame(
            [$filename],
            $this->extractor->extract($content)
        );
    }

    public function testItSupportsQueryStringsAndFragments(): void
    {
        $filename = str_repeat('d', 40).'.png';

        $content = sprintf(
            '/assets/wss-lineup/%s?v=1#preview',
            $filename
        );

        $this->assertSame(
            [$filename],
            $this->extractor->extract($content)
        );
    }

    public function testItNormalizesUppercaseHexadecimalCharacters(): void
    {
        $uppercase = str_repeat('A', 40).'.png';
        $expected = strtolower($uppercase);

        $this->assertSame(
            [$expected],
            $this->extractor->extract(
                '/assets/wss-lineup/'.$uppercase
            )
        );
    }

    /**
     * @dataProvider invalidReferenceProvider
     */
    public function testItRejectsUnrelatedOrInvalidReferences(
        string $content
    ): void {
        $this->assertSame(
            [],
            $this->extractor->extract($content)
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidReferenceProvider(): array
    {
        return [
            'different directory' => [
                '/assets/uploads/'.str_repeat('a', 40).'.png',
            ],
            'filename too short' => [
                '/assets/wss-lineup/'.str_repeat('a', 39).'.png',
            ],
            'filename too long' => [
                '/assets/wss-lineup/'.str_repeat('a', 41).'.png',
            ],
            'invalid hexadecimal character' => [
                '/assets/wss-lineup/'.str_repeat('g', 40).'.png',
            ],
            'wrong extension' => [
                '/assets/wss-lineup/'.str_repeat('a', 40).'.jpg',
            ],
            'path traversal' => [
                '/assets/wss-lineup/../'.str_repeat('a', 40).'.png',
            ],
            'external unrelated image' => [
                'https://example.test/image.png',
            ],
        ];
    }
}
