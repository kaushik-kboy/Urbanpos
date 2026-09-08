<x-field name="name" label="Item Category Name" :value="$itemCategory->name ?? ''" placeholder="Item Category Name" />
<x-bool-select name="is_mandatory" label="Is Mandatory" :value="$itemCategory->is_mandatory ?? false" true-label="Yes" false-label="No" />
<x-bool-select name="status" label="Status" :value="$itemCategory->status ?? true" />
