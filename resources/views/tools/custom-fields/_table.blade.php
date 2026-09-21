@if($fields->isEmpty())
    <div class="p-4 text-center text-muted">
        <i class="fas fa-sliders-h fa-2x mb-2 text-secondary"></i>
        <p class="mb-1 font-weight-bold">No custom fields defined for {{ $moduleLabel ?? $module }} yet.</p>
        <p class="small text-muted mb-3">Add any extra fields needed without modifying database tables or running migrations.</p>
        <button type="button" class="btn btn-outline-primary btn-sm px-3 shadow-sm" onclick="openCreateModal('{{ $module }}')">
            <i class="fas fa-plus mr-1"></i> Add {{ $moduleLabel ?? $module }} Field
        </button>
    </div>
@else
    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead class="bg-light">
                <tr class="text-nowrap" style="font-size: 0.85rem;">
                    <th style="width: 60px;">Order</th>
                    <th>Field Label</th>
                    <th>Field Key (Code)</th>
                    <th>Type</th>
                    <th>Required?</th>
                    <th>Options / Details</th>
                    <th>Status</th>
                    <th class="text-right" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($fields as $f)
                    <tr class="align-middle">
                        <td class="text-center font-weight-bold text-muted">{{ $f->sort_order }}</td>
                        <td>
                            <strong class="text-dark">{{ $f->field_name }}</strong>
                            @if($f->default_value)
                                <div class="small text-muted">Default: <em>{{ $f->default_value }}</em></div>
                            @endif
                        </td>
                        <td>
                            <code class="text-primary font-weight-bold bg-light px-1 py-0 rounded">{{ $f->field_key }}</code>
                        </td>
                        <td>
                            @switch($f->field_type)
                                @case('date')
                                    <span class="badge badge-info"><i class="fas fa-calendar-alt mr-1"></i> Date</span>
                                    @break
                                @case('number')
                                    <span class="badge badge-secondary"><i class="fas fa-hashtag mr-1"></i> Number</span>
                                    @break
                                @case('select')
                                    <span class="badge badge-purple" style="background-color: #6f42c1; color: white;"><i class="fas fa-list-ul mr-1"></i> Dropdown</span>
                                    @break
                                @case('textarea')
                                    <span class="badge badge-warning"><i class="fas fa-align-left mr-1"></i> Textarea</span>
                                    @break
                                @case('checkbox')
                                    <span class="badge badge-success"><i class="fas fa-check-square mr-1"></i> Checkbox</span>
                                    @break
                                @default
                                    <span class="badge badge-light border"><i class="fas fa-font mr-1"></i> Text</span>
                            @endswitch
                        </td>
                        <td>
                            @if($f->is_required)
                                <span class="badge badge-danger">Required (*)</span>
                            @else
                                <span class="badge badge-light border text-muted">Optional</span>
                            @endif
                        </td>
                        <td>
                            @if($f->field_type === 'select' && !empty($f->options))
                                <div class="d-flex flex-wrap" style="gap: 3px;">
                                    @foreach(array_slice($f->options, 0, 4) as $opt)
                                        <span class="badge badge-light border text-dark" style="font-size: 0.72rem;">{{ $opt }}</span>
                                    @endforeach
                                    @if(count($f->options) > 4)
                                        <span class="badge badge-light text-muted" style="font-size: 0.72rem;">+{{ count($f->options) - 4 }} more</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-muted small">&mdash;</span>
                            @endif
                        </td>
                        <td>
                            <form action="{{ route('tools.custom-fields.toggle', $f) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-xs font-weight-bold {{ $f->status ? 'btn-success' : 'btn-outline-secondary' }}" title="Click to toggle status">
                                    <i class="fas {{ $f->status ? 'fa-check-circle' : 'fa-ban' }} mr-1"></i>
                                    {{ $f->status ? 'Active' : 'Disabled' }}
                                </button>
                            </form>
                        </td>
                        <td class="text-right text-nowrap">
                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick='openEditModal(@json($f))' title="Edit Field">
                                <i class="fas fa-pen"></i>
                            </button>
                            <form action="{{ route('tools.custom-fields.destroy', $f) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete custom field &quot;{{ $f->field_name }}&quot;? All historical values for this field will be permanently deleted.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-outline-danger" title="Delete Field">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
