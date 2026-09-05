<x-field name="description" label="Description" :value="$gstTax->description ?? ''" />
<x-field name="percentage" label="Percentage" type="number" step="0.01" :value="$gstTax->percentage ?? ''" />
<x-bool-select name="status" label="Status" :value="$gstTax->status ?? true" />
