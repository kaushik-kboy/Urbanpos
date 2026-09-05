<x-select name="item_category_id" label="Category Head" :options="$itemCategories" :selected="$itemCategoryValue->item_category_id ?? ''" placeholder="Select a category head" />
<x-field name="name" label="Category Name" :value="$itemCategoryValue->name ?? ''" />
<x-bool-select name="show_in_webstore" label="Show in Webstore" :value="$itemCategoryValue->show_in_webstore ?? false" true-label="Yes" false-label="No" />
<x-bool-select name="sellquick_applicable" label="SellQuick Applicable" :value="$itemCategoryValue->sellquick_applicable ?? false" true-label="Yes" false-label="No" />
<x-field name="allowed_qty_ml" label="Allowed Qty (in ml)" type="number" :value="$itemCategoryValue->allowed_qty_ml ?? ''" />
<x-bool-select name="status" label="Status" :value="$itemCategoryValue->status ?? true" true-label="Yes" false-label="No" />
