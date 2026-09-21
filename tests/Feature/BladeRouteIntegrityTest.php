<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BladeRouteIntegrityTest extends TestCase
{
    /**
     * Scan all Blade views and ensure all route(...) calls exist and are valid.
     */
    public function test_all_blade_templates_have_valid_route_definitions(): void
    {
        $viewPath = resource_path('views');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewPath));
        $missingRoutes = [];
        $checkedRoutes = [];

        foreach ($files as $file) {
            if ($file->isDir() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            // Match route('name', ...) or route("name", ...)
            if (preg_match_all("/route\(\s*['\"]([a-zA-Z0-9\._\-]+)['\"]/", $content, $matches)) {
                foreach ($matches[1] as $routeName) {
                    if (isset($checkedRoutes[$routeName])) {
                        continue;
                    }

                    $checkedRoutes[$routeName] = true;

                    if (! Route::has($routeName)) {
                        $missingRoutes[] = [
                            'route' => $routeName,
                            'file'  => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()),
                        ];
                    }
                }
            }
        }

        $errorMsg = '';
        if (! empty($missingRoutes)) {
            $errorMsg = "Found " . count($missingRoutes) . " invalid route() calls in Blade templates:\n";
            foreach ($missingRoutes as $item) {
                $errorMsg .= "  - Route '{$item['route']}' in {$item['file']}\n";
            }
        }

        $this->assertEmpty($missingRoutes, $errorMsg);
    }
}
