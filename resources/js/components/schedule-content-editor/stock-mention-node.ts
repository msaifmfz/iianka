import type {
    EditorConfig,
    LexicalNode,
    NodeKey,
    SerializedTextNode,
    Spread,
} from 'lexical';
import { $applyNodeReplacement, TextNode } from 'lexical';

export type SerializedStockMentionNode = Spread<
    { stockId: number },
    SerializedTextNode
>;

/**
 * A recognized stock name inside the schedule content editor. The node's
 * text stays fully editable plain text — getTextContent() is unchanged, so
 * serialization to the persisted content field needs no special handling.
 * The distinct node type prevents Lexical from merging it with neighboring
 * plain TextNodes.
 */
export class StockMentionNode extends TextNode {
    __stockId: number;

    static getType(): string {
        return 'stock-mention';
    }

    static clone(node: StockMentionNode): StockMentionNode {
        return new StockMentionNode(node.__text, node.__stockId, node.__key);
    }

    constructor(text: string, stockId: number, key?: NodeKey) {
        super(text, key);
        this.__stockId = stockId;
    }

    getStockId(): number {
        return this.getLatest().__stockId;
    }

    createDOM(config: EditorConfig): HTMLElement {
        const dom = super.createDOM(config);
        dom.classList.add('stock-mention');
        dom.dataset.stockId = String(this.__stockId);

        return dom;
    }

    updateDOM(prevNode: this, dom: HTMLElement, config: EditorConfig): boolean {
        const isUpdated = super.updateDOM(prevNode, dom, config);

        if (prevNode.__stockId !== this.__stockId) {
            dom.dataset.stockId = String(this.__stockId);
        }

        return isUpdated;
    }

    static importJSON(
        serializedNode: SerializedStockMentionNode,
    ): StockMentionNode {
        return $createStockMentionNode(
            serializedNode.text,
            serializedNode.stockId,
        ).updateFromJSON(serializedNode);
    }

    exportJSON(): SerializedStockMentionNode {
        return {
            ...super.exportJSON(),
            type: StockMentionNode.getType(),
            stockId: this.getStockId(),
        };
    }
}

export function $createStockMentionNode(
    text: string,
    stockId: number,
): StockMentionNode {
    return $applyNodeReplacement(new StockMentionNode(text, stockId));
}

export function $isStockMentionNode(
    node: LexicalNode | null | undefined,
): node is StockMentionNode {
    return node instanceof StockMentionNode;
}
