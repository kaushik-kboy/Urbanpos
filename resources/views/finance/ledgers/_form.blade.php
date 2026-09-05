<x-field name="name" label="Ledger Name" :value="$ledger->name ?? ''" />
<x-select name="ledger_group" label="Ledger Group" :options="$groups" :selected="$ledger->ledger_group ?? 'Indirect Expense'" />
<x-field name="opening_balance" label="Opening Balance" type="number" step="0.01" :value="$ledger->opening_balance ?? 0" />
<x-select name="opening_balance_type" label="Opening Balance Type" :options="['Debit' => 'Debit', 'Credit' => 'Credit']" :selected="$ledger->opening_balance_type ?? 'Debit'" />
<x-bool-select name="status" label="Status" :value="$ledger->status ?? true" />
