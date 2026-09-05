<tr>
    <td>
        <select name="lines[{{ $index }}][ledger_id]" class="form-control form-control-sm">
            <option value="">Select ledger</option>
            @foreach ($ledgers as $id => $name)
                <option value="{{ $id }}" @selected(($line->ledger_id ?? null) == $id)>{{ $name }}</option>
            @endforeach
        </select>
    </td>
    <td><input type="number" step="0.01" name="lines[{{ $index }}][debit]" value="{{ $line->debit ?? '' }}" class="form-control form-control-sm"></td>
    <td><input type="number" step="0.01" name="lines[{{ $index }}][credit]" value="{{ $line->credit ?? '' }}" class="form-control form-control-sm"></td>
    <td class="text-center align-middle">
        <button type="button" class="btn btn-xs btn-outline-danger row-remove"><i class="fas fa-times"></i></button>
    </td>
</tr>
