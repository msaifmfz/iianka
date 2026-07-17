/**
 * Trim trailing zeros from a decimal(12,3) quantity string for display
 * ("20.000" → "20", "12.500" → "12.5").
 */
export function formatStockQuantity(quantity: string): string {
    if (!quantity.includes('.')) {
        return quantity;
    }

    return quantity.replace(/0+$/, '').replace(/\.$/, '');
}

export function signedStockQuantity(value: string): string {
    return value.startsWith('-')
        ? formatStockQuantity(value)
        : `+${formatStockQuantity(value)}`;
}

export function isZeroStockQuantity(value: string): boolean {
    return /^-?0(\.0+)?$/.test(value);
}
