<x-field name="name" label="State Name" :value="$state->name ?? ''" required placeholder="e.g. Gujarat" />
<x-field name="code" label="State Code" :value="$state->code ?? ''" placeholder="e.g. GJ" maxlength="10" />
<x-field name="country" label="Country" :value="$state->country ?? 'India'" required />
<x-bool-select name="status" label="Status" :value="$state->status ?? true" />
