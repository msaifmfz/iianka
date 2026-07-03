import {
    closestCenter,
    DndContext,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type {
    Announcements,
    DragEndEvent,
    UniqueIdentifier,
} from '@dnd-kit/core';
import {
    restrictToParentElement,
    restrictToVerticalAxis,
} from '@dnd-kit/modifiers';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical } from 'lucide-react';
import type { CSSProperties, ReactNode } from 'react';
import { useMemo } from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

export type SortableOrderRenderContext = {
    dragHandle: ReactNode;
    index: number;
    isDragging: boolean;
};

type ReorderMetadata = {
    activeId: UniqueIdentifier;
    overId: UniqueIdentifier;
    oldIndex: number;
    newIndex: number;
};

type Props<TItem> = {
    items: TItem[];
    getId: (item: TItem) => UniqueIdentifier;
    getLabel: (item: TItem) => string;
    renderItem: (item: TItem, context: SortableOrderRenderContext) => ReactNode;
    onReorder: (items: TItem[], metadata: ReorderMetadata) => void;
    className?: string;
    disabled?: boolean;
    emptyState?: ReactNode;
    screenReaderInstructions?: string;
};

type SortableOrderListItemProps<TItem> = {
    id: UniqueIdentifier;
    index: number;
    item: TItem;
    label: string;
    disabled: boolean;
    renderItem: (item: TItem, context: SortableOrderRenderContext) => ReactNode;
};

const DEFAULT_SCREEN_READER_INSTRUCTIONS =
    'スペースキーまたはEnterキーで項目を持ち上げ、上下矢印キーで移動し、もう一度スペースキーまたはEnterキーで配置します。Escキーでキャンセルできます。';

export default function SortableOrderList<TItem>({
    items,
    getId,
    getLabel,
    renderItem,
    onReorder,
    className,
    disabled = false,
    emptyState = null,
    screenReaderInstructions = DEFAULT_SCREEN_READER_INSTRUCTIONS,
}: Props<TItem>) {
    const itemIds = useMemo(
        () => items.map((item) => getId(item)),
        [getId, items],
    );
    const itemDetails = useMemo(
        () =>
            new Map(
                items.map((item, index) => [
                    getId(item),
                    {
                        label: getLabel(item),
                        position: index + 1,
                    },
                ]),
            ),
        [getId, getLabel, items],
    );
    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: {
                distance: 4,
            },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );
    const announcements = useMemo<Announcements>(() => {
        const labelFor = (id: UniqueIdentifier): string =>
            itemDetails.get(id)?.label ?? String(id);
        const positionFor = (id: UniqueIdentifier): number | null =>
            itemDetails.get(id)?.position ?? null;

        return {
            onDragStart({ active }) {
                const position = positionFor(active.id);

                return position === null
                    ? `${labelFor(active.id)}を並べ替えています。`
                    : `${labelFor(active.id)}を並べ替えています。現在${position}番目です。`;
            },
            onDragOver({ active, over }) {
                if (!over) {
                    return `${labelFor(active.id)}の移動先がありません。`;
                }

                const position = positionFor(over.id);

                return position === null
                    ? `${labelFor(active.id)}を移動しています。`
                    : `${labelFor(active.id)}を${position}番目へ移動しています。`;
            },
            onDragEnd({ active, over }) {
                if (!over) {
                    return `${labelFor(active.id)}の並べ替えを終了しました。`;
                }

                const position = positionFor(over.id);

                return position === null
                    ? `${labelFor(active.id)}を移動しました。`
                    : `${labelFor(active.id)}を${position}番目に移動しました。`;
            },
            onDragCancel({ active }) {
                return `${labelFor(active.id)}の並べ替えをキャンセルしました。`;
            },
        };
    }, [itemDetails]);

    function handleDragEnd({ active, over }: DragEndEvent) {
        if (!over || active.id === over.id) {
            return;
        }

        const oldIndex = itemIds.indexOf(active.id);
        const newIndex = itemIds.indexOf(over.id);

        if (oldIndex === -1 || newIndex === -1) {
            return;
        }

        onReorder(arrayMove(items, oldIndex, newIndex), {
            activeId: active.id,
            overId: over.id,
            oldIndex,
            newIndex,
        });
    }

    if (items.length === 0) {
        return <>{emptyState}</>;
    }

    return (
        <DndContext
            accessibility={{
                announcements,
                screenReaderInstructions: {
                    draggable: screenReaderInstructions,
                },
            }}
            collisionDetection={closestCenter}
            modifiers={[restrictToVerticalAxis, restrictToParentElement]}
            onDragEnd={handleDragEnd}
            sensors={sensors}
        >
            <SortableContext
                disabled={disabled}
                items={itemIds}
                strategy={verticalListSortingStrategy}
            >
                <div className={cn('space-y-3', className)}>
                    {items.map((item, index) => {
                        const id = getId(item);

                        return (
                            <SortableOrderListItem
                                key={id}
                                disabled={disabled}
                                id={id}
                                index={index}
                                item={item}
                                label={getLabel(item)}
                                renderItem={renderItem}
                            />
                        );
                    })}
                </div>
            </SortableContext>
        </DndContext>
    );
}

function SortableOrderListItem<TItem>({
    id,
    index,
    item,
    label,
    disabled,
    renderItem,
}: SortableOrderListItemProps<TItem>) {
    const {
        attributes,
        isDragging,
        listeners,
        setActivatorNodeRef,
        setNodeRef,
        transform,
        transition,
    } = useSortable({
        disabled,
        id,
    });
    const style: CSSProperties = {
        opacity: isDragging ? 0.7 : undefined,
        position: isDragging ? 'relative' : undefined,
        transform: CSS.Transform.toString(transform),
        transition,
        zIndex: isDragging ? 1 : undefined,
    };
    const dragHandle = (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    {...attributes}
                    {...listeners}
                    ref={setActivatorNodeRef}
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="cursor-grab touch-none text-muted-foreground active:cursor-grabbing"
                    disabled={disabled}
                    aria-label={`${label}の表示順を変更`}
                >
                    <GripVertical className="size-4" />
                    <span className="sr-only">並べ替え</span>
                </Button>
            </TooltipTrigger>
            <TooltipContent>並べ替え</TooltipContent>
        </Tooltip>
    );

    return (
        <div ref={setNodeRef} style={style}>
            {renderItem(item, {
                dragHandle,
                index,
                isDragging,
            })}
        </div>
    );
}
