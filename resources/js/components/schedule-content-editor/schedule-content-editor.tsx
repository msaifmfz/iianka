import { LexicalComposer } from '@lexical/react/LexicalComposer';
import { ContentEditable } from '@lexical/react/LexicalContentEditable';
import { LexicalErrorBoundary } from '@lexical/react/LexicalErrorBoundary';
import { HistoryPlugin } from '@lexical/react/LexicalHistoryPlugin';
import { OnChangePlugin } from '@lexical/react/LexicalOnChangePlugin';
import { PlainTextPlugin } from '@lexical/react/LexicalPlainTextPlugin';
import {
    $createLineBreakNode,
    $createParagraphNode,
    $createTextNode,
    $getRoot,
} from 'lexical';
import { useMemo } from 'react';
import { cn } from '@/lib/utils';
import type { StockOption } from '@/types';
import { buildStockMatcher } from './catalog';
import StockHighlightPlugin from './stock-highlight-plugin';
import { StockMentionNode } from './stock-mention-node';
import StockTypeaheadPlugin from './stock-typeahead-plugin';

/**
 * Plain-text schedule content editor. Looks and serializes like a textarea
 * (line breaks become "\n"; getTextContent() is the submitted value) but
 * additionally highlights recognized stock names and offers slash-command
 * stock insertion. The persisted content is always plain text — highlighting
 * lives only in the editor DOM.
 */
export default function ScheduleContentEditor({
    defaultValue,
    onChange,
    stocks,
    ariaLabelledBy,
    className,
}: {
    defaultValue: string;
    onChange: (content: string) => void;
    stocks: StockOption[];
    ariaLabelledBy?: string;
    className?: string;
}) {
    const matcher = useMemo(() => buildStockMatcher(stocks), [stocks]);

    const initialConfig = useMemo(
        () => ({
            namespace: 'schedule-content-editor',
            nodes: [StockMentionNode],
            onError: (error: Error) => {
                throw error;
            },
            editorState: () => {
                const paragraph = $createParagraphNode();

                defaultValue.split('\n').forEach((line, index) => {
                    if (index > 0) {
                        paragraph.append($createLineBreakNode());
                    }

                    if (line !== '') {
                        paragraph.append($createTextNode(line));
                    }
                });

                $getRoot().append(paragraph);
            },
        }),
        // The initial state is only read on mount; the editor manages its
        // own state afterwards.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [],
    );

    return (
        <LexicalComposer initialConfig={initialConfig}>
            <div className="relative">
                <PlainTextPlugin
                    contentEditable={
                        <ContentEditable
                            aria-labelledby={ariaLabelledBy}
                            className={cn(
                                'min-h-32 rounded-md border bg-transparent px-3 py-2 text-sm whitespace-pre-wrap outline-none focus-visible:ring-1 focus-visible:ring-ring',
                                className,
                            )}
                        />
                    }
                    placeholder={null}
                    ErrorBoundary={LexicalErrorBoundary}
                />
                <HistoryPlugin />
                <OnChangePlugin
                    ignoreSelectionChange
                    onChange={(editorState) => {
                        editorState.read(() => {
                            onChange($getRoot().getTextContent());
                        });
                    }}
                />
                <StockHighlightPlugin matcher={matcher} />
                <StockTypeaheadPlugin stocks={stocks} />
            </div>
        </LexicalComposer>
    );
}
