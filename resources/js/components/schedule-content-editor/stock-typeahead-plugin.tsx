import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import {
    LexicalTypeaheadMenuPlugin,
    MenuOption,
    useBasicTypeaheadTriggerMatch,
} from '@lexical/react/LexicalTypeaheadMenuPlugin';
import { $createTextNode } from 'lexical';
import type { TextNode } from 'lexical';
import { useCallback, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { formatStockQuantity } from '@/lib/stock';
import { cn } from '@/lib/utils';
import type { StockOption } from '@/types';
import { filterStockOptions } from './catalog';
import { $createStockMentionNode } from './stock-mention-node';

class StockMenuOption extends MenuOption {
    stock: StockOption;

    constructor(stock: StockOption) {
        super(String(stock.id));
        this.stock = stock;
    }
}

/**
 * Slash-command stock picker: typing "/" at a token boundary lists every
 * active stock; further characters narrow the list. Selection replaces the
 * slash query with the canonical stock name plus a trailing space.
 */
export default function StockTypeaheadPlugin({
    stocks,
}: {
    stocks: StockOption[];
}) {
    const [editor] = useLexicalComposerContext();
    const [query, setQuery] = useState<string | null>(null);

    const checkForTriggerMatch = useBasicTypeaheadTriggerMatch('/', {
        minLength: 0,
    });

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
