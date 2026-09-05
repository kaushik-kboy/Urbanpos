<x-select name="tender_type_id" label="Tender Name" :options="$tenderTypes" :selected="$tenderTypeValue->tender_type_id ?? ''" placeholder="Select a tender type" />
<x-field name="name" label="Name" :value="$tenderTypeValue->name ?? ''" />
<x-field name="group_ledger" label="Group Ledger" :value="$tenderTypeValue->group_ledger ?? ''" />
<x-select name="branch_id" label="Branch" :options="$branches" :selected="$tenderTypeValue->branch_id ?? ''" placeholder="GLOBAL" />
<x-bool-select name="status" label="Status" :value="$tenderTypeValue->status ?? true" />
