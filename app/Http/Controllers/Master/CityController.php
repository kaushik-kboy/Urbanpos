<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CityController extends Controller
{
    use HasPerPage, Importable;

    public function index(Request $request)
    {
        $search = $request->input('search');
        $stateId = $request->input('state_id');

        $cities = City::with('state')
            ->when($stateId, fn ($q) => $q->where('state_id', $stateId))
            ->when($search, function ($q, $s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhereHas('state', fn ($sq) => $sq->where('name', 'like', "%{$s}%"));
            })
            ->orderBy('name')
            ->paginate($this->perPage());

        $states = State::where('status', true)->orderBy('name')->pluck('name', 'id');

        return view('master.cities.index', compact('cities', 'states', 'search', 'stateId'));
    }

    public function create()
    {
        $states = State::where('status', true)->orderBy('name')->pluck('name', 'id');
        $defaultStateId = State::where('name', 'Gujarat')->value('id') ?? $states->keys()->first();

        return view('master.cities.create', compact('states', 'defaultStateId'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        City::create($data);

        return redirect()->route('master.cities.index')->with('status', 'City created successfully.');
    }

    public function edit(City $city)
    {
        $states = State::where('status', true)->orderBy('name')->pluck('name', 'id');

        return view('master.cities.edit', compact('city', 'states'));
    }

    public function update(Request $request, City $city)
    {
        $data = $this->validateData($request, $city);
        $city->update($data);

        return redirect()->route('master.cities.index')->with('status', 'City updated successfully.');
    }

    public function destroy(City $city)
    {
        return redirect()->route('master.cities.index')->with('error', 'Master records cannot be deleted. You can set status to Inactive instead.');
    }

    public function apiList(Request $request): JsonResponse
    {
        $stateId = $request->input('state_id');
        $stateName = $request->input('state_name');

        $query = City::where('status', true);

        if ($stateId) {
            $query->where('state_id', $stateId);
        } elseif ($stateName) {
            $query->whereHas('state', fn ($q) => $q->where('name', $stateName));
        }

        $cities = $query->with('state:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'state_id'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'state_id' => $c->state_id,
                'state_name' => $c->state?->name ?? 'Gujarat',
            ]);

        return response()->json(['success' => true, 'cities' => $cities]);
    }

    private function validateData(Request $request, ?City $city = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cities', 'name')
                    ->where(fn ($q) => $q->where('state_id', $request->input('state_id')))
                    ->ignore($city?->id),
            ],
            'state_id' => ['required', 'exists:states,id'],
            'status' => ['required', 'boolean'],
        ]);
    }

    protected function importModel(): string
    {
        return City::class;
    }

    protected function importColumns(): array
    {
        return [
            'Name' => ['column' => 'name', 'required' => true],
        ];
    }

    protected function importUniqueBy(): array
    {
        return ['name', 'state_id'];
    }

    protected function importRelations(): array
    {
        return [
            'State' => fn (string $v) => $v === '' ? ['state_id' => null]
                : ['state_id' => State::firstOrCreate(['name' => $v], ['code' => null, 'country' => 'India', 'status' => true])->id],
        ];
    }
}
