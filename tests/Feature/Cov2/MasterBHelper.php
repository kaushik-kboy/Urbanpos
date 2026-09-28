<?php

namespace Tests\Feature\Cov2;

use App\Models\User;
use Illuminate\Support\Facades\File;

trait MasterBHelper
{
    private array $tmpDirs = [];

    protected function burnSuperUser(): void
    {
        User::factory()->create(); // id 1 is the hard-coded gate bypass
    }

    protected function actAs(string $role, array $attrs = []): User
    {
        $u = User::factory()->create($attrs);
        $u->assignRole($role);
        $this->actingAs($u);

        return $u;
    }

    /** Point storage_path() at a throwaway dir (logs, backups, framework/views). */
    protected function useTempStorage(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'upos_b_'.uniqid();
        foreach (['framework/views', 'framework/cache', 'logs', 'app/backups'] as $sub) {
            File::makeDirectory($dir.'/'.$sub, 0777, true, true);
        }
        $this->app->useStoragePath($dir);
        $this->tmpDirs[] = $dir;

        return $dir;
    }

    protected function cleanTempDirs(): void
    {
        foreach ($this->tmpDirs as $d) {
            File::deleteDirectory($d);
        }
        $this->tmpDirs = [];
    }
}
