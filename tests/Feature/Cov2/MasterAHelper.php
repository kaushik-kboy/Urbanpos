<?php

namespace Tests\Feature\Cov2;

use App\Models\Branch;
use App\Models\User;

trait MasterAHelper
{
    protected function burnFirstUser(): void
    {
        User::factory()->create(); // id 1 is a Gate::before bypass; burn it
    }

    protected function asRole(string $role, ?int $branchId = null): User
    {
        $u = User::factory()->create(['branch_id' => $branchId]);
        $u->assignRole($role);
        $this->actingAs($u);

        return $u;
    }

    protected function makeBranch(array $over = []): Branch
    {
        static $n = 0;
        $n++;

        return Branch::create(array_merge([
            'name' => 'MA Branch '.$n.'-'.uniqid(),
            'language' => 'English',
            'business_type' => 'BRANCH',
            'webstore' => false,
            'country_code' => 'IN',
            'enable_thirdparty_loyalty' => false,
            'gst_type' => 'Un Register',
            'gst_filing' => 'Monthly',
            'status' => true,
        ], $over));
    }

    protected function branchPayload(array $over = []): array
    {
        return array_merge([
            'name' => 'Payload Branch '.uniqid(),
            'erp_code' => 'ERP'.uniqid(),
            'language' => 'English',
            'business_type' => 'COCO',
            'webstore' => 0,
            'country_code' => 'IN',
            'enable_thirdparty_loyalty' => 0,
            'gst_type' => 'Regular',
            'gst_filing' => 'Monthly',
            'status' => 1,
        ], $over);
    }
}
