<x-field name="name" label="Name" :value="$uom->name ?? ''" />
<x-field name="alias" label="Alias" :value="$uom->alias ?? ''" />
<x-bool-select name="status" label="Status" :value="$uom->status ?? true" />
