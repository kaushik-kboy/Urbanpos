<x-field name="name" label="Name" :value="$area->name ?? ''" />
<x-select name="branch_id" label="Branch" :options="$branches" :selected="$area->branch_id ?? ''" placeholder="All Branches" />
<x-bool-select name="status" label="Status" :value="$area->status ?? true" />
