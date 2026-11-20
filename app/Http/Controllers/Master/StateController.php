<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StateController extends Controller
{
    use HasPerPage, Importable;

    public function index(Request $request)
    {
        $search = $request->input('search');
        $states = State::when($search, function ($q, $s) {
            $q->where('name', 'like', "%{$s}%")
                ->orWhere('code', 'like', "%{$s}%");
        })->orderBy('name')->paginate($this->perPage());

        return view('master.states.index', compact('states', 'search'));
    }

    public function create()
    {
        return view('master.states.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        State::create($data);

        return redirect()->route('master.states.index')->with('status', 'State created successfully.');
    }

    public function edit(State $state)
    {
        return view('master.states.edit', compact('state'));
    }

    public function update(Request $request, State $state)
    {
        $data = $this->validateData($request, $state);
        $state->update($data);

        return redirect()->route('master.states.index')->with('status', 'State updated successfully.');
    }

    public function destroy(State $state)
    {
        return redirect()->route('master.states.index')->with('error', 'Master records cannot be deleted. You can set status to Inactive instead.');
    }

    public function apiList(): JsonResponse
    {
        $states = State::where('status', true)->orderBy('name')->get(['id', 'name', 'code']);

        return response()->json(['success' => true, 'states' => $states]);
    }

    private function validateData(Request $request, ?State $state = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('states', 'name')->ignore($state?->id),
            ],
            'code' => ['nullable', 'string', 'max:10'],
            'country' => ['required', 'string', 'max:100'],
            'status' => ['required', 'boolean'],
        ]);
    }

    protected function importModel(): string
    {
        return State::class;
    }

    protected function importColumns(): array
    {
        return [
            'Name' => ['column' => 'name', 'required' => true],
            'Code' => ['column' => 'code'],
            'Country' => ['column' => 'country'],
        ];
    }

    protected function importUniqueBy(): array
    {
        return ['name'];
    }
}
