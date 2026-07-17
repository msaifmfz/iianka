/**
 * Laravel array validation produces dot-keyed errors ("aliases.2",
 * "ordered_ids.0") that useForm's error bag cannot express in its key type.
 * These helpers centralize the widening cast needed to read them.
 */
export function fieldError(errors: object, key: string): string | undefined {
    return (errors as Record<string, string | undefined>)[key];
}

/** The field's own error, or the first error of one of its array elements. */
export function fieldOrElementError(
    errors: object,
    field: string,
): string | undefined {
    const direct = fieldError(errors, field);

    if (direct !== undefined) {
        return direct;
    }

    const elementError = Object.entries(errors).find(([key]) =>
        key.startsWith(`${field}.`),
    ) as [string, string] | undefined;

    return elementError?.[1];
}
