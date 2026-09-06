<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    use HasPerPage, Importable;


    public function index()
    {
        $branches = Branch::orderBy('name')->paginate($this->perPage());

        return view('master.branches.index', compact('branches'));
    }

    public function create()
    {
        return view('master.branches.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        Branch::create($data);

        return redirect()->route('master.branches.index')->with('status', 'Branch created successfully.');
    }

    public function edit(Branch $branch)
    {
        return view('master.branches.edit', compact('branch'));
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $this->validateData($request);
        $branch->update($data);

        return redirect()->route('master.branches.index')->with('status', 'Branch updated successfully.');
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();

        return redirect()->route('master.branches.index')->with('status', 'Branch deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'language' => ['required', 'string', 'max:50'],
            'area_code' => ['nullable', 'string', 'max:50'],
            'circle_code' => ['nullable', 'string', 'max:50'],
            'business_type' => ['required', 'in:COCO,FRANCHISE,BRANCH,DISTRIBUTION CENTER,SERVICE UNIT,FOFO,ASP'],
            'webstore' => ['required', 'boolean'],
            'erp_code' => ['nullable', 'string', 'max:50'],
            'country_code' => ['required', 'string', 'max:10'],
            'license_id' => ['nullable', 'string', 'max:100'],
            'cst' => ['nullable', 'string', 'max:100'],
            'website_link' => ['nullable', 'string', 'max:255'],
            'social_media_link' => ['nullable', 'string', 'max:255'],
            'enable_thirdparty_loyalty' => ['required', 'boolean'],
            'gst_no' => ['nullable', 'string', 'max:20'],
            'pan_no' => ['nullable', 'string', 'max:20'],
            'gst_type' => ['required', 'in:Regular,Composite,Un Register'],
            'gst_filing' => ['required', 'in:Monthly,Quarterly'],
            'status' => ['required', 'boolean'],
        ]);
    }

    protected function importModel(): string
    {
        return Branch::class;
    }

    protected function importColumns(): array
    {
        return [
            'Name' => ['column' => 'name', 'required' => true],
            'Address Line1' => ['column' => 'address_line1'],
            'Address Line2' => ['column' => 'address_line2'],
            'City' => ['column' => 'city'],
            'Postal Code' => ['column' => 'postal_code'],
            'State' => ['column' => 'state'],
            'Country' => ['column' => 'country'],
            'Contact Person' => ['column' => 'contact_person'],
            'Phone' => ['column' => 'phone'],
            'Email' => ['column' => 'email'],
            'Mobile' => ['column' => 'mobile'],
            'Language' => ['column' => 'language'],
            'Area Code' => ['column' => 'area_code'],
            'Circle Code' => ['column' => 'circle_code'],
            'Business Type' => ['column' => 'business_type'],
            'Webstore' => ['column' => 'webstore', 'cast' => fn ($v) => $this->importBool($v)],
            'ERP Code' => ['column' => 'erp_code'],
            'Country Code' => ['column' => 'country_code'],
            'License Id' => ['column' => 'license_id'],
            'CST' => ['column' => 'cst'],
            'Website Link' => ['column' => 'website_link'],
            'Social Media Link' => ['column' => 'social_media_link'],
            'Enable Thirdparty Loyalty' => ['column' => 'enable_thirdparty_loyalty', 'cast' => fn ($v) => $this->importBool($v)],
            'GST No' => ['column' => 'gst_no'],
            'PAN No' => ['column' => 'pan_no'],
            'GST Type' => ['column' => 'gst_type'],
            'GST Filing' => ['column' => 'gst_filing'],
            'Status' => ['column' => 'status', 'cast' => fn ($v) => $this->importBool($v)],
        ];
    }

    protected function importUniqueBy(): array
    {
        return ['name'];
    }
}
