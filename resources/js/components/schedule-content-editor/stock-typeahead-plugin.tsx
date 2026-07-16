import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import {
    LexicalTypeaheadMenuPlugin,
    MenuOption,
} from '@lexical/react/LexicalTypeaheadMenuPlugin';
import { $createTextNode } from 'lexical';
import type { TextNode } from 'lexical';
import { useCallback, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { formatStockQuantity } from '@/lib/stock';
import { cn } from '@/lib/utils';
import type { StockOption } from '@/types';
import { filterStockOptions, WORD_CHAR } from './catalog';
import { $createStockMentionNode } from './stock-mention-node';

/**
 * Matches a slash command anywhere before the caret, not just at a token
 * boundary — Japanese text has no spaces, so requiring one before "/" (as
 * Lexical's useBasicTypeaheadTriggerMatch does) made the picker unreachable
 * mid-sentence. A slash preceded by ":" or "/" is skipped so typing URLs
 * doesn't pop the menu on every slash. (Written without lookbehind: Safari
 * before 16.4 fails to parse it, which would break the whole bundle.)
 */
const SLASH_TRIGGER_REGEX = /(^|[^/:])(\/([^/\s]{0,75}))$/;

class StockMenuOption extends MenuOption {
    stock: StockOption;

    constructor(stock: StockOption) {
        super(String(stock.id));
        this.stock = stock;
    }
}

/**
 * Slash-command stock picker: typing "/" lists every active stock; further
 * characters narrow the list. Selection replaces the slash query with the
 * canonical stock name plus a trailing space (and a leading one when typed
 * against a word, so the backend parser still matches the name).
 */
export default function StockTypeaheadPlugin({
    stocks,
}: {
    stocks: StockOption[];
}) {
    const [editor] = useLexicalComposerContext();
    const [query, setQuery] = useState<string | null>(null);

    const checkForTriggerMatch = useCallback((text: string) => {
        const match = SLASH_TRIGGER_REGEX.exec(text);

        if (match === null) {
            return null;
        }

        return {
            leadOffset: match.index + match[1].length,
            matchingString: match[3],
            replaceableString: match[2],
        };
    }, []);

    const options = useMemo(
        () =>
            filterStockOptions(query ?? '', stocks).map(
                (stock) => new StockMenuOption(stock),
            ),
        [query, stocks],
    );

    const onSelectOption = useCallback(
        (
            option: StockMenuOption,
            nodeToReplace: TextNode | null,
            closeMenu: () => void,
        ) => {
            editor.update(() => {
                const mention = $createStockMentionNode(
                    option.stock.name,
                    option.stock.id,
                );
                const trailingSpace = $createTextNode(' ');

                if (nodeToReplace) {
                    nodeToReplace.replace(mention);
                } else {
                    return;
                }

                // The parser only counts a stock name preceded by a non-word
                // char, so separate the mention from any word it was typed
                // against (e.g. 朝食/たま → 朝食 たまご).
                const previousChar = mention
                    .getPreviousSibling()
                    ?.getTextContent()
                    .at(-1);

                if (
                    previousChar !== undefined &&
                    WORD_CHAR.test(previousChar)
                ) {
                    mention.insertBefore($createTextNode(' '));
                }

                mention.insertAfter(trailingSpace);
                trailingSpace.select(1, 1);
                closeMenu();
            });
        },
        [editor],
    );

    return (
        <LexicalTypeaheadMenuPlugin<StockMenuOption>
            onQueryChange={setQuery}
            onSelectOption={onSelectOption}
            triggerFn={checkForTriggerMatch}
            options={options}
            menuRenderFn={(
                anchorElementRef,
                { selectedIndex, selectOptionAndCleanUp, setHighlightedIndex },
            ) =>
                anchorElementRef.current
                    ? createPortal(
                          <ul
                              role="listbox"
                              aria-label="在庫を選択"
                              className="z-50 mt-1 max-h-60 w-64 overflow-y-auto rounded-md border bg-popover p-1 text-sm text-popover-foreground shadow-md"
                          >
                              {options.length === 0 && (
                                  <li className="px-2 py-1.5 text-muted-foreground">
                                      該当する在庫がありません
                                  </li>
                              )}
                              {options.map((option, index) => (
                                  <li
                                      key={option.key}
                                      role="option"
                                      aria-selected={selectedIndex === index}
                                      ref={(element) =>
                                          option.setRefElement(element)
                                      }
                                      className={cn(
                                          'flex cursor-pointer items-center justify-between gap-3 rounded-sm px-2 py-1.5',
                                          selectedIndex === index &&
                                              'bg-accent text-accent-foreground',
                                      )}
                                      onMouseEnter={() =>
                                          setHighlightedIndex(index)
                                      }
                                      onMouseDown={(event) =>
                                          event.preventDefault()
                                      }
                                      onClick={() => {
                                          setHighlightedIndex(index);
                                          selectOptionAndCleanUp(option);
                                      }}
                                  >
                                      <span className="truncate">
                                          {option.stock.name}
                                      </span>
                                      <span className="shrink-0 text-xs text-muted-foreground">
                                          残{' '}
                                          {formatStockQuantity(
                                              option.stock.available_quantity,
                                          )}
                                      </span>
                                  </li>
                              ))}
                          </ul>,
                          anchorElementRef.current,
                      )
                    : null
            }
        />
    );
}
