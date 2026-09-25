<x-field name="name" label="Customer Type Name" :value="$customerType->name ?? ''" required placeholder="e.g. RETAIL INVOICE, TAX INVOICE" />
<x-field name="code" label="Short Code" :value="$customerType->code ?? ''" placeholder="e.g. RETAIL, TAX" />
<x-field name="description" label="Description" :value="$customerType->description ?? ''" placeholder="Optional description..." />
<x-bool-select name="status" label="Status" :value="$customerType->status ?? true" />
