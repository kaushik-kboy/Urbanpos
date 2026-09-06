@props(['importRoute', 'sampleRoute', 'title'])

@php $modalId = 'import-modal-'.\Illuminate\Support\Str::slug($title); @endphp

<button type="button" class="btn btn-outline-primary btn-sm float-right mr-2" data-toggle="modal" data-target="#{{ $modalId }}">
    <i class="fas fa-file-import"></i> Import
</button>

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ $importRoute }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import {{ $title }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">
                        Upload a CSV or Excel (.xls/.xlsx) file. The first row must contain the column
                        headers. <a href="{{ $sampleRoute }}">Download a sample template</a> to see the
                        expected columns.
                    </p>
                    <div class="form-group">
                        <input type="file" name="file" class="form-control-file" accept=".csv,.txt,.xls,.xlsx" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </div>
        </form>
    </div>
</div>
