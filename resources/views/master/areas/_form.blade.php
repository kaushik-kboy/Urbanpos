<x-field name="name" label="Name" :value="$area->name ?? ''" required />
<x-select name="city_id" label="City" :options="$cities" :selected="$area->city_id ?? ($defaultCityId ?? '')" placeholder="Select City" required />
<x-bool-select name="status" label="Status" :value="$area->status ?? true" />
