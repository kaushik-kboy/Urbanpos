<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AreaController extends Controller
{
    use HasPerPage, Importable;

    public function index(Request $request)
    {
        $search = $request->input('search');
        $cityId = $request->input('city_id');

        $areas = Area::with('city')
            ->when($cityId, fn ($q) => $q->where('city_id', $cityId))
            ->when($search, function ($q, $s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhereHas('city', fn ($cq) => $cq->where('name', 'like', "%{$s}%"));
            })
            ->orderBy('name')
            ->paginate($this->perPage());

        $cities = City::where('status', true)->orderBy('name')->pluck('name', 'id');

        return view('master.areas.index', compact('areas', 'cities', 'search', 'cityId'));
    }

    public function create()
    {
        $cities = City::where('status', true)->orderBy('name')->pluck('name', 'id');
        $defaultCityId = City::where('name', 'Ahmedabad')->value('id') ?? $cities->keys()->first();

        return view('master.areas.create', compact('cities', 'defaultCityId'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        if (empty($data['city_id'])) {
            $data['city_id'] = City::where('name', 'Ahmedabad')->value('id');
        }
        Area::create($data);

        return redirect()->route('master.areas.index')->with('status', 'Area created successfully.');
    }

    public function edit(Area $area)
    {
        $cities = City::where('status', true)->orderBy('name')->pluck('name', 'id');
        $defaultCityId = $area->city_id ?? (City::where('name', 'Ahmedabad')->value('id') ?? $cities->keys()->first());

        return view('master.areas.edit', compact('area', 'cities', 'defaultCityId'));
    }

    public function update(Request $request, Area $area)
    {
        $data = $this->validateData($request, $area);
        if (empty($data['city_id'])) {
            $data['city_id'] = City::where('name', 'Ahmedabad')->value('id');
        }
        $area->update($data);

        return redirect()->route('master.areas.index')->with('status', 'Area updated successfully.');
    }

    public function destroy(Area $area)
    {
        return redirect()->route('master.areas.index')->with('error', 'Master records cannot be deleted. You can set status to Inactive instead.');
    }

    public function apiList(Request $request): JsonResponse
    {
        $cityId = $request->input('city_id');
        $cityName = $request->input('city_name');

        $query = Area::where('status', true);

        if ($cityId) {
            $query->where('city_id', $cityId);
        } elseif ($cityName) {
            $query->whereHas('city', fn ($q) => $q->where('name', $cityName));
        }

        $areas = $query->orderBy('name')->get(['id', 'name', 'city_id']);

        return response()->json(['success' => true, 'areas' => $areas]);
    }

    private function validateData(Request $request, ?Area $area = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('areas', 'name')
                    ->where(fn ($q) => $q->where('city_id', $request->input('city_id')))
                    ->ignore($area?->id),
            ],
            'city_id' => ['nullable', 'exists:cities,id'],
            'status' => ['required', 'boolean'],
        ]);
    }

    protected function importModel(): string
    {
        return Area::class;
    }

    protected function importColumns(): array
    {
        return [
            'Name' => ['column' => 'name', 'required' => true],
        ];
    }

    protected function importUniqueBy(): array
    {
        return ['name', 'city_id'];
    }

    protected function importRelations(): array
    {
        return [
            'City' => fn (string $v) => $v === '' ? ['city_id' => null]
                : ['city_id' => City::firstOrCreate(['name' => $v], ['state_id' => null, 'status' => true])->id],
        ];
    }
}
