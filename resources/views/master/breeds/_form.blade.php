<x-field name="name" label="Breed Name" :value="$breed->name ?? ''" />
<x-select name="pet_type_id" label="Pet Type" :options="$petTypes" :selected="$breed->pet_type_id ?? ''" placeholder="Select a pet type" />
<x-bool-select name="status" label="Status" :value="$breed->status ?? true" />
