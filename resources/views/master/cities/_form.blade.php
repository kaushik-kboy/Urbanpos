<x-field name="name" label="City Name" :value="$city->name ?? ''" required placeholder="e.g. Ahmedabad" />
<x-select name="state_id" label="State" :options="$states" :selected="$city->state_id ?? ($defaultStateId ?? '')" placeholder="Select State" required />
<x-bool-select name="status" label="Status" :value="$city->status ?? true" />
