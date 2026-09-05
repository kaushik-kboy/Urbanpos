<x-field name="name" label="Name" :value="$area->name ?? ''" />
<x-select name="branch_id" label="Branch" :options="$branches" :selected="$area->branch_id ?? ''" placeholder="GLOBAL" />
