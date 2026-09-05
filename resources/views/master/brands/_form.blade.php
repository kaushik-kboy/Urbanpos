<x-field name="name" label="Name" :value="$brand->name ?? ''" />
<x-field name="prefix" label="Prefix" :value="$brand->prefix ?? ''" />
<x-field name="alias_code" label="Alias Code" :value="$brand->alias_code ?? ''" />
<x-bool-select name="status" label="Status" :value="$brand->status ?? true" />
