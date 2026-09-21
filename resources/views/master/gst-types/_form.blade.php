<x-field name="name" label="GST Type Name" :value="$gstType->name ?? ''" required />
<x-field name="code" label="Short Code" :value="$gstType->code ?? ''" />
<x-field name="description" label="Description" :value="$gstType->description ?? ''" />
<x-bool-select name="status" label="Status" :value="$gstType->status ?? true" />
