import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import { mergeRegister } from '@lexical/utils';
import { $createTextNode, $isTextNode, TextNode } from 'lexical';
import { useEffect } from 'react';
import type { StockMatcher } from './catalog';
import { findStockMatches } from './catalog';
import {
    $createStockMentionNode,
    $isStockMentionNode,
    StockMentionNode,
} from './stock-mention-node';

function siblingText(node: TextNode, direction: 'previous' | 'next'): string {
    const sibling =
        direction === 'previous'
            ? node.getPreviousSibling()
            : node.getNextSibling();

    return $isTextNode(sibling) ? sibling.getTextContent() : '';
}

/**
 * Keeps stock-name highlighting in sync while typing. A TextNode transform
 * wraps newly recognized names in StockMentionNode; a StockMentionNode
 * transform reverts nodes whose text no longer is (or no longer is the whole
 * of) a catalog match.
 *
 * Both transforms match against the node's text combined with its adjacent
 * sibling text so that a longer catalog name spanning a node boundary (e.g.
 * a "Milk" mention followed by typed " Powder") wins: the shorter mention is
 * reverted, merged back into its plain-text neighbors, and the merged node
 * is rescanned as a whole.
 */
export default function StockHighlightPlugin({
    matcher,
}: {
    matcher: StockMatcher;
}) {
    const [editor] = useLexicalComposerContext();

    useEffect(() => {
        if (!editor.hasNodes([StockMentionNode])) {
            throw new Error(
                'StockHighlightPlugin: StockMentionNode not registered on the editor',
            );
        }

        return mergeRegister(
            editor.registerNodeTransform(TextNode, (node) => {
                if (!node.isSimpleText() || node.isComposing()) {
                    return;
                }

                // Editing next to a mention can extend it into a longer
                // catalog name or break its boundary, but only this node is
                // dirty — re-run the mention transform on the neighbors.
                for (const sibling of [
                    node.getPreviousSibling(),
                    node.getNextSibling(),
                ]) {
                    if ($isStockMentionNode(sibling)) {
                        sibling.markDirty();
                    }
                }

                const text = node.getTextContent();
                const before = siblingText(node, 'previous');
                const after = siblingText(node, 'next');
                // Only wrap matches that fall entirely inside this node; a
                // longer match reaching into a sibling reserves the range in
                // findStockMatches, preventing a shorter, wrong highlight.
                const match = findStockMatches(
                    before + text + after,
                    matcher,
                ).find(
                    (candidate) =>
                        candidate.start >= before.length &&
                        candidate.end <= before.length + text.length,
                );

                if (match === undefined) {
                    return;
                }

                const start = match.start - before.length;
                const end = match.end - before.length;
                let target = node;

                if (start > 0) {
                    [, target] = node.splitText(start);
                }

                if (end - start < target.getTextContent().length) {
                    [target] = target.splitText(end - start);
                }

                target.replace(
                    $createStockMentionNode(
                        target.getTextContent(),
                        match.stockId,
                    ),
                );
            }),
            editor.registerNodeTransform(StockMentionNode, (node) => {
                if (node.isComposing()) {
                    return;
                }

                const text = node.getTextContent();
                const before = siblingText(node, 'previous');
                const after = siblingText(node, 'next');
                const stillValid = findStockMatches(
                    before + text + after,
                    matcher,
                ).some(
                    (match) =>
                        match.start === before.length &&
                        match.end === before.length + text.length &&
                        match.stockId === node.getStockId(),
                );

                if (stillValid) {
                    return;
                }

                let plain = node.replace($createTextNode(text));
                const previousSibling = plain.getPreviousSibling();

                if (
                    $isTextNode(previousSibling) &&
                    previousSibling.isSimpleText()
                ) {
                    plain = previousSibling.mergeWithSibling(plain);
                }

                const nextSibling = plain.getNextSibling();

                if ($isTextNode(nextSibling) && nextSibling.isSimpleText()) {
                    plain.mergeWithSibling(nextSibling);
                }
            }),
        );
    }, [editor, matcher]);

    return null;
}
