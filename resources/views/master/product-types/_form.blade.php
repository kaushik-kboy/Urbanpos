<x-field name="name" label="Product Type Name" :value="$productType->name ?? ''" required />
<x-field name="code" label="Short Code" :value="$productType->code ?? ''" />
<x-field name="description" label="Description" :value="$productType->description ?? ''" />
<x-bool-select name="status" label="Status" :value="$productType->status ?? true" />
