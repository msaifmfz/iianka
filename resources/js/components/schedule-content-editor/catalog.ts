import type { StockOption } from '@/types';

/**
 * Client-side mirror of the backend stock matching rules
 * (app/Services/Stock/StockNameNormalizer.php and
 * ScheduleContentStockParser.php). Keep both sides in sync: NFKC fold,
 * lowercase, whitespace collapse, longest-match-first, Unicode word
 * boundaries with the trailing-ASCII-digit exception.
 *
 * Highlighting here is a visual aid only — the backend parse on save is
 * authoritative for inventory.
 */

export type StockMatcherEntry = {
    stockId: number;
    stock: StockOption;
    /** Normalized text of the current name or an alias. */
    folded: string;
};

export type StockMatcher = {
    entries: StockMatcherEntry[];
};

export type StockTextMatch = {
    stockId: number;
    /** UTF-16 code-unit offsets into the scanned string. */
    start: number;
    end: number;
};

const WORD_CHAR = /[\p{L}\p{N}\p{M}]/u;
const WHITESPACE = /[\s\u00a0\u3000]/u;
/**
 * Halfwidth and combining (semi)voiced sound marks. NFKC only composes them
 * with the preceding kana when both are normalized together (\uff76 + \uff9e \u2192 \u30ac), so
 * the fold below handles such pairs as one unit \u2014 matching the backend
 * parser.
 */
const VOICED_SOUND_MARKS = new Set(['\uff9e', '\uff9f', '\u3099', '\u309a']);

function foldChar(char: string): string {
    return char.normalize('NFKC').toLowerCase();
}

export function normalizeStockText(value: string): string {
    return value
        .normalize('NFKC')
        .toLowerCase()
        .replace(/[\s\u00a0\u3000]+/gu, ' ')
        .trim();
}

export function buildStockMatcher(stocks: StockOption[]): StockMatcher {
    const entries: StockMatcherEntry[] = [];

    for (const stock of stocks) {
        for (const text of [stock.name, ...stock.aliases]) {
            const folded = normalizeStockText(text);

            if (folded !== '') {
                entries.push({ stockId: stock.id, stock, folded });
            }
        }
    }

    entries.sort(
        (a, b) =>
            b.folded.length - a.folded.length ||
            a.folded.localeCompare(b.folded),
    );

    return { entries };
}

type FoldedText = {
    /** One entry per folded code point. */
    chars: string[];
    /** Code-unit start offset of the original character for each folded char. */
    unitStarts: number[];
    /** Code-unit end offset (exclusive) of the original character. */
    unitEnds: number[];
};

function foldWithOffsets(text: string): FoldedText {
    const chars: string[] = [];
    const unitStarts: number[] = [];
    const unitEnds: number[] = [];
    const codePoints = Array.from(text);
    let unit = 0;
    let index = 0;

    while (index < codePoints.length) {
        const char = codePoints[index];

        if (WHITESPACE.test(char)) {
            const runStartUnit = unit;
            let runEndUnit = unit;
            let hasLineBreak = false;

            while (
                index < codePoints.length &&
                WHITESPACE.test(codePoints[index])
            ) {
                if (codePoints[index] === '\n' || codePoints[index] === '\r') {
                    hasLineBreak = true;
                }

                runEndUnit += codePoints[index].length;
                index += 1;
            }

            chars.push(hasLineBreak ? '\n' : ' ');
            unitStarts.push(runStartUnit);
            unitEnds.push(runEndUnit);
            unit = runEndUnit;
            continue;
        }

        let folded: string | null = null;
        let consumedUnits = char.length;

        if (
            index + 1 < codePoints.length &&
            VOICED_SOUND_MARKS.has(codePoints[index + 1])
        ) {
            const pair = foldChar(char + codePoints[index + 1]);

            if (Array.from(pair).length === 1) {
                folded = pair;
                consumedUnits = char.length + codePoints[index + 1].length;
                index += 1;
            }
        }

        for (const foldedChar of Array.from(folded ?? foldChar(char))) {
            chars.push(foldedChar);
            unitStarts.push(unit);
            unitEnds.push(unit + consumedUnits);
        }

        unit += consumedUnits;
        index += 1;
    }

    return { chars, unitStarts, unitEnds };
}

function isBoundarySafe(
    chars: string[],
    start: number,
    end: number,
    before: string,
    after: string,
): boolean {
    const charBefore = start > 0 ? chars[start - 1] : before.at(-1);
    const charAfter = end < chars.length ? chars[end] : after.at(0);

    if (charBefore !== undefined && WORD_CHAR.test(charBefore)) {
        return false;
    }

    if (
        charAfter !== undefined &&
        WORD_CHAR.test(charAfter) &&
        !/[0-9]/.test(charAfter)
    ) {
        return false;
    }

    return true;
}

/**
 * Find every stock mention in `text`, longest catalog name first, skipping
 * overlaps. `before`/`after` provide the adjacent characters from sibling
 * nodes so boundaries hold across node edges.
 */
export function findStockMatches(
    text: string,
    matcher: StockMatcher,
    before = '',
    after = '',
): StockTextMatch[] {
    if (text === '' || matcher.entries.length === 0) {
        return [];
    }

    const folded = foldWithOffsets(text);
    const haystack = folded.chars.join('');
    const reserved: Array<[number, number]> = [];
    const matches: StockTextMatch[] = [];

    for (const entry of matcher.entries) {
        let from = 0;

        for (;;) {
            const position = haystack.indexOf(entry.folded, from);

            if (position === -1) {
                break;
            }

            from = position + 1;

            // haystack indexes are code-unit offsets into the folded string;
            // map to folded-char indexes (each folded char may be >1 unit).
            const start = unitToCharIndex(folded.chars, position);
            const end = unitToCharIndex(
                folded.chars,
                position + entry.folded.length,
            );

            if (
                !isBoundarySafe(folded.chars, start, end, before, after) ||
                reserved.some(([s, e]) => start < e && end > s)
            ) {
                continue;
            }

            reserved.push([start, end]);
            matches.push({
                stockId: entry.stockId,
                start: folded.unitStarts[start],
                end: folded.unitEnds[end - 1],
            });
        }
    }

    matches.sort((a, b) => a.start - b.start);

    return matches;
}

function unitToCharIndex(chars: string[], unitOffset: number): number {
    let units = 0;
    let index = 0;

    while (units < unitOffset && index < chars.length) {
        units += chars[index].length;
        index += 1;
    }

    return index;
}

/**
 * Filter the slash-command dropdown: prefix matches before substring
 * matches, current names before aliases, then alphabetical.
 */
export function filterStockOptions(
    query: string,
    stocks: StockOption[],
): StockOption[] {
    const folded = normalizeStockText(query);

    if (folded === '') {
        return [...stocks].sort((a, b) => a.name.localeCompare(b.name, 'ja'));
    }

    const scored: Array<{ stock: StockOption; score: number }> = [];

    for (const stock of stocks) {
        const name = normalizeStockText(stock.name);
        const aliases = stock.aliases.map(normalizeStockText);
        let score: number | null = null;

        if (name.startsWith(folded)) {
            score = 0;
        } else if (aliases.some((alias) => alias.startsWith(folded))) {
            score = 1;
        } else if (name.includes(folded)) {
            score = 2;
        } else if (aliases.some((alias) => alias.includes(folded))) {
            score = 3;
        }

        if (score !== null) {
            scored.push({ stock, score });
        }
    }

    scored.sort(
        (a, b) =>
            a.score - b.score || a.stock.name.localeCompare(b.stock.name, 'ja'),
    );

    return scored.map((item) => item.stock);
}
