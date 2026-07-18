<?php

declare(strict_types=1);

namespace App\Domain\Stock\Parsing;

use App\Domain\Stock\Enums\StockMentionStatus;
use App\Domain\Stock\ValueObjects\StockQuantity;
use Normalizer;

/**
 * Deterministic stock-mention parser for free-text schedule content.
 *
 * Matching is exact (no fuzzy matching) against normalized current names and
 * active aliases, longest candidate first, on Unicode-aware token boundaries.
 * A quantity must follow the mention within a small separator window;
 * mentions without a quantity token are not reported.
 *
 * All offsets are Unicode code-point offsets into the original content.
 *
 * Known limitation: an ASCII "x" glued directly to a name (ネジx2) reads as a
 * following word and blocks the match — "×", a space, or a spaced "x" work.
 */
final class ScheduleContentStockParser
{
    public const string VERSION = '1.0.0';

    private const int MAX_SEPARATOR_CHARS = 5;

    private const array QUANTITY_SEPARATORS = [' ', ':', '=', 'x', '×'];

    /**
     * Halfwidth and combining (semi)voiced sound marks that NFKC only
     * composes with the preceding kana when normalized together with it.
     */
    private const array VOICED_SOUND_MARKS = ["\u{FF9E}", "\u{FF9F}", "\u{3099}", "\u{309A}"];

    /**
     * @param  list<StockCatalogEntry>  $entries
     */
    public function parse(string $content, array $entries): StockParseResult
    {
        if (trim($content) === '' || $entries === []) {
            return new StockParseResult([]);
        }

        [$chars, $offsetStarts, $offsetEnds] = $this->foldWithOffsetMap($content);
        $text = implode('', $chars);
        $length = count($chars);

        $candidates = $entries;
        usort($candidates, fn (StockCatalogEntry $a, StockCatalogEntry $b): int => mb_strlen($b->normalizedMatchText) <=> mb_strlen($a->normalizedMatchText)
            ?: $a->normalizedMatchText <=> $b->normalizedMatchText);

        /** @var list<array{0: int, 1: int}> $reserved */
        $reserved = [];
        /** @var list<array{entry: StockCatalogEntry, start: int, end: int}> $matches */
        $matches = [];

        foreach ($candidates as $entry) {
            $needle = $entry->normalizedMatchText;

            if ($needle === '') {
                continue;
            }

            $needleLength = mb_strlen($needle);
            $searchFrom = 0;

            while (($position = mb_strpos($text, $needle, $searchFrom)) !== false) {
                $searchFrom = $position + 1;
                $end = $position + $needleLength;

                if (! $this->isBoundarySafe($chars, $position, $end, $length)) {
                    continue;
                }

                if ($this->intersectsReserved($reserved, $position, $end)) {
                    continue;
                }

                $reserved[] = [$position, $end];
                $matches[] = ['entry' => $entry, 'start' => $position, 'end' => $end];
            }
        }

        usort($matches, fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        $mentions = [];

        foreach ($matches as $match) {
            $entry = $match['entry'];
            $numberToken = $this->scanQuantityToken($chars, $length, $match['end'], $reserved);

            if ($numberToken === null) {
                continue;
            }

            $reserved[] = [$numberToken['start'], $numberToken['end']];

            [$status, $quantity] = $this->evaluateQuantity($numberToken['raw'], $entry);

            if ($status === StockMentionStatus::Recognized && ! $entry->isActive) {
                $status = StockMentionStatus::InactiveStock;
            }

            $startOffset = $offsetStarts[$match['start']];
            $endOffset = $offsetEnds[$match['end'] - 1];

            $mentions[] = new ParsedStockMention(
                stockId: $entry->stockId,
                stockNameSnapshot: $entry->stockName,
                matchedText: mb_substr($content, $startOffset, $endOffset - $startOffset),
                quantity: $quantity,
                startOffset: $startOffset,
                endOffset: $endOffset,
                quantityStartOffset: $offsetStarts[$numberToken['start']],
                quantityEndOffset: $offsetEnds[$numberToken['end'] - 1],
                identificationMethod: $entry->identificationMethod,
                status: $status,
            );
        }

        return new StockParseResult($mentions);
    }

    /**
     * Fold the content the same way StockNameNormalizer folds catalog names
     * (NFKC, lowercase, whitespace runs collapsed) while keeping maps from
     * each folded code-point index back to the original code-point range it
     * covers (start inclusive, end exclusive).
     *
     * Whitespace runs collapse to a single "\n" when they contain a line
     * break, otherwise to a single " ": names never span line breaks, and the
     * quantity scan must stop at them.
     *
     * A kana followed by a (semi)voiced sound mark is folded as a pair:
     * per-code-point NFKC leaves ｶ + ﾞ as カ + a combining mark, which would
     * never match a catalog name normalized as a whole string (ガ).
     *
     * @return array{0: list<string>, 1: list<int>, 2: list<int>}
     */
    private function foldWithOffsetMap(string $content): array
    {
        $original = mb_str_split($content);
        $count = count($original);
        $chars = [];
        $offsetStarts = [];
        $offsetEnds = [];
        $index = 0;

        while ($index < $count) {
            if ($this->isWhitespace($original[$index])) {
                $runStart = $index;
                $hasLineBreak = false;

                while ($index < $count && $this->isWhitespace($original[$index])) {
                    if ($original[$index] === "\n" || $original[$index] === "\r") {
                        $hasLineBreak = true;
                    }

                    $index++;
                }

                $chars[] = $hasLineBreak ? "\n" : ' ';
                $offsetStarts[] = $runStart;
                $offsetEnds[] = $index;

                continue;
            }

            $consumed = 1;
            $folded = null;

            if ($index + 1 < $count && in_array($original[$index + 1], self::VOICED_SOUND_MARKS, true)) {
                $pair = Normalizer::normalize($original[$index].$original[$index + 1], Normalizer::FORM_KC);

                if (is_string($pair) && mb_strlen($pair) === 1) {
                    $folded = $pair;
                    $consumed = 2;
                }
            }

            if ($folded === null) {
                $folded = Normalizer::normalize($original[$index], Normalizer::FORM_KC);

                if ($folded === false || $folded === '') {
                    $folded = $original[$index];
                }
            }

            foreach (mb_str_split(mb_strtolower($folded)) as $foldedChar) {
                $chars[] = $foldedChar;
                $offsetStarts[] = $index;
                $offsetEnds[] = $index + $consumed;
            }

            $index += $consumed;
        }

        return [$chars, $offsetStarts, $offsetEnds];
    }

    private function isWhitespace(string $char): bool
    {
        return preg_match('/[\s\x{00A0}\x{3000}]/u', $char) === 1;
    }

    private function isWordChar(string $char): bool
    {
        return preg_match('/[\p{L}\p{N}\p{M}]/u', $char) === 1;
    }

    private function isAsciiDigit(string $char): bool
    {
        return $char >= '0' && $char <= '9' && strlen($char) === 1;
    }

    /**
     * A match must sit on token boundaries. The character after the match may
     * be an ASCII digit because quantities may directly follow a name in
     * Japanese text (e.g. 牛乳2).
     *
     * @param  list<string>  $chars
     */
    private function isBoundarySafe(array $chars, int $start, int $end, int $length): bool
    {
        if ($start > 0 && $this->isWordChar($chars[$start - 1])) {
            return false;
        }

        if ($end < $length) {
            $after = $chars[$end];

            if ($this->isWordChar($after) && ! $this->isAsciiDigit($after)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $reserved
     */
    private function intersectsReserved(array $reserved, int $start, int $end): bool
    {
        foreach ($reserved as [$reservedStart, $reservedEnd]) {
            if ($start < $reservedEnd && $end > $reservedStart) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $reserved
     */
    private function positionReserved(array $reserved, int $position): bool
    {
        return $this->intersectsReserved($reserved, $position, $position + 1);
    }

    /**
     * Read the quantity token following a mention: up to five separator
     * characters, then an optionally negative decimal number. Line breaks and
     * sentence terminators are not separators, so scanning never crosses
     * them. Returns null when no acceptable number follows.
     *
     * @param  list<string>  $chars
     * @param  list<array{0: int, 1: int}>  $reserved
     * @return array{start: int, end: int, raw: string}|null
     */
    private function scanQuantityToken(array $chars, int $length, int $from, array $reserved): ?array
    {
        $position = $from;
        $skipped = 0;

        while (
            $position < $length
            && $skipped < self::MAX_SEPARATOR_CHARS
            && in_array($chars[$position], self::QUANTITY_SEPARATORS, true)
            && ! $this->positionReserved($reserved, $position)
        ) {
            $position++;
            $skipped++;
        }

        $start = $position;
        $raw = '';

        if ($position < $length && $chars[$position] === '-') {
            $raw = '-';
            $position++;
        }

        $integerDigits = 0;

        while ($position < $length && $this->isAsciiDigit($chars[$position])) {
            $raw .= $chars[$position];
            $position++;
            $integerDigits++;
        }

        if ($integerDigits === 0) {
            return null;
        }

        if (
            $position + 1 < $length
            && $chars[$position] === '.'
            && $this->isAsciiDigit($chars[$position + 1])
        ) {
            $raw .= '.';
            $position++;

            while ($position < $length && $this->isAsciiDigit($chars[$position])) {
                $raw .= $chars[$position];
                $position++;
            }
        }

        if ($position < $length && $this->isWordChar($chars[$position])) {
            return null;
        }

        if ($this->intersectsReserved($reserved, $start, $position)) {
            return null;
        }

        return ['start' => $start, 'end' => $position, 'raw' => $raw];
    }

    /**
     * @return array{0: StockMentionStatus, 1: StockQuantity|null}
     */
    private function evaluateQuantity(string $raw, StockCatalogEntry $entry): array
    {
        $quantity = StockQuantity::tryFromDecimal($raw);

        if (! $quantity instanceof StockQuantity) {
            return [StockMentionStatus::InvalidQuantity, null];
        }

        if (! $quantity->isPositive()) {
            return [StockMentionStatus::InvalidQuantity, $quantity];
        }

        if (! $entry->allowsFractionalQuantity && ! $quantity->isWhole()) {
            return [StockMentionStatus::InvalidQuantity, $quantity];
        }

        return [StockMentionStatus::Recognized, $quantity];
    }
}
