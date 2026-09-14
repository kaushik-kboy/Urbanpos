<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use HasPerPage;

    public function index(Request $request)
    {
        $query = User::with(['roles', 'branch']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', function ($rq) use ($request) {
                $rq->where('name', $request->role);
            });
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $users = $query->orderBy('name')->paginate($this->perPage())->withQueryString();
        $roles = Role::orderBy('name')->pluck('name');
        $branches = Branch::orderBy('name')->get();

        return view('master.users.index', compact('users', 'roles', 'branches'));
    }

    public function create()
    {
        return view('master.users.create', $this->formOptions());
    }

    public function show(User $user)
    {
        // This is a management screen, not a public profile — "viewing" a user just
        // means editing them; no separate read-only detail page.
        return redirect()->route('master.users.edit', $user);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'], // 'hashed' cast on the model hashes this automatically
            'branch_id' => $data['branch_id'] ?? null,
        ]);
        $user->assignRole($data['role']);

        return redirect()->route('master.users.index')->with('status', 'User created successfully.');
    }

    public function edit(User $user)
    {
        return view('master.users.edit', array_merge(['user' => $user], $this->formOptions()));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validateData($request, $user);

        $update = [
            'name' => $data['name'],
            'email' => $data['email'],
            'branch_id' => $data['branch_id'] ?? null,
        ];
        // Blank password on edit means "keep current" — never overwrite with an empty hash.
        if (! empty($data['password'])) {
            $update['password'] = $data['password'];
        }

        $user->update($update);
        $user->syncRoles([$data['role']]);

        return redirect()->route('master.users.index')->with('status', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        if ($user->hasRole('Owner') && User::role('Owner')->count() <= 1) {
            throw ValidationException::withMessages([
                'user' => 'Cannot delete the last remaining Owner — the system would have no one left who can manage users.',
            ]);
        }

        $user->delete();

        return redirect()->route('master.users.index')->with('status', 'User deleted.');
    }

    private function formOptions(): array
    {
        return [
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'roles' => Role::orderBy('name')->pluck('name', 'name'),
        ];
    }

    private function validateData(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);
    }
}
