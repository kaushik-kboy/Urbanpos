<x-field name="name" label="Name" :value="$petType->name ?? ''" />
<x-bool-select name="status" label="Status" :value="$petType->status ?? true" />
