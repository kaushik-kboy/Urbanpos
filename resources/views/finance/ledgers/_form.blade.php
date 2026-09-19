<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="font-weight-bold text-muted text-uppercase small mb-0"><i class="fas fa-book mr-1 text-primary"></i> Ledger Account Details</h6>
    <x-form-layout-customizer
        form-key="finance_ledgers.general"
        container-id="ledger-fields-grid"
        title="Customize Ledger Form Layout"
    />
</div>

<div class="row g-2 form-fields-grid" id="ledger-fields-grid">
    <div class="field-wrapper col-md-6" data-field="name" data-label="Ledger Name" data-default-order="1" data-core="1">
        <x-field name="name" label="Ledger Name" :value="$ledger->name ?? ''" required />
    </div>
    <div class="field-wrapper col-md-6" data-field="ledger_group" data-label="Ledger Group" data-default-order="2" data-core="1">
        <x-select name="ledger_group" label="Ledger Group" :options="$groups" :selected="$ledger->ledger_group ?? 'Indirect Expense'" />
    </div>
    <div class="field-wrapper col-md-6" data-field="opening_balance" data-label="Opening Balance" data-default-order="3">
        <x-field name="opening_balance" label="Opening Balance" type="number" step="0.01" :value="$ledger->opening_balance ?? 0" />
    </div>
    <div class="field-wrapper col-md-6" data-field="opening_balance_type" data-label="Opening Balance Type" data-default-order="4">
        <x-select name="opening_balance_type" label="Opening Balance Type" :options="['Debit' => 'Debit', 'Credit' => 'Credit']" :selected="$ledger->opening_balance_type ?? 'Debit'" />
    </div>
    <div class="field-wrapper col-md-6" data-field="status" data-label="Status" data-default-order="5">
        <x-bool-select name="status" label="Status" :value="$ledger->status ?? true" />
    </div>
</div>
