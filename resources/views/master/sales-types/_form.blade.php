<x-field name="name" label="Sales Type Name" :value="$salesType->name ?? ''" required placeholder="e.g. Local, Interstate" />
<x-field name="code" label="Short Code" :value="$salesType->code ?? ''" placeholder="e.g. LOC, INT" />
<x-field name="description" label="Description" :value="$salesType->description ?? ''" placeholder="Optional description..." />
<x-bool-select name="status" label="Status" :value="$salesType->status ?? true" />
