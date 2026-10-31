export type ConditionKey =
    'vacuum' | 'angle' | 'deck' | 'high' | 'upward' | 'pit' | 'clean';
export type Conditions = Record<
    ConditionKey,
    { label: string; rate: number; provisional: boolean }
>;
export type CatalogItem = {
    id: string;
    source_row: number;
    category: string;
    name: string;
    specification: string;
    note: string;
    price: string;
    unit: string;
    construction: boolean;
    conditions: boolean;
    processing: boolean;
    processing_fee: number;
    stock_length: number | null;
    included_conditions: ConditionKey[];
};
export type QuotationItem = {
    mode: 'master' | 'free';
    catalog_id: string;
    quantity: string;
    free_name: string;
    free_specification: string;
    free_unit: string;
    free_price: string;
    adjustment: 'standard' | 'add' | 'multiply' | 'direct';
    adjustment_value: string;
    conditions: Record<ConditionKey, boolean>;
    rates: Record<ConditionKey, string>;
    other_rate: string;
    length: string;
    processing_mode: 'auto' | 'none' | 'manual';
    processing_fee: string;
    note: string;
};
export type QuotationInput = {
    version: number;
    client_name: string;
    project_name: string;
    delivery_location: string;
    person_in_charge: string;
    issued_on: string;
    category: string;
    expenses: string;
    small_job: boolean;
    minimum_charge: string;
    holiday: boolean;
    net_enabled: boolean;
    net_multiplier: string;
    display_mode: 'detail' | 'lump_sum';
    notes: string;
    items: QuotationItem[];
};
export type QuotationLine = {
    position: number;
    name: string;
    specification: string;
    quantity: string;
    unit: string;
    base_price: string;
    surcharge_rate: string;
    processing_fee: string;
    needs_processing: boolean;
    unit_price: string;
    amount: string;
    construction: boolean;
    note: string;
};
export type QuotationCalculation = {
    rounding_policy?: 'line_half_up_yen';
    lines: QuotationLine[];
    subtotal: string;
    construction_subtotal: string;
    small_job_charge: string;
    holiday_charge: string;
    expenses: string;
    total: string;
    net: string | null;
    display_mode: 'detail' | 'lump_sum';
    warnings: string[];
};
export type Quotation = {
    id: number;
    number: string;
    version: number;
    client_name: string;
    project_name: string;
    issued_on: string;
    input: QuotationInput;
    calculation: QuotationCalculation;
    issuer: Record<string, string>;
};
