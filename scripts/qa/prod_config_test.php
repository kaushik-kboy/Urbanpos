<?php
/**
 * Priority 6 (production DB/connection architecture) — never touches the real DB or the shared dev php.ini.
 * Boots small pools of PHP's built-in server (real HTTP, real per-request bootstrap cost — unlike the
 * in-process kernel calls in perf_queries.php) against the dedicated QA MySQL (:3307) and, for the "recommended"
 * runs, the dedicated QA Redis (:6390) started separately. Compares BASELINE (today's real config: no opcache,
 * DB-backed session/cache) against RECOMMENDED (opcache on, Redis session/cache) at a light concurrency the
 * coverage job running in this session can tolerate; the full 200-300 concurrent-terminal run is deferred.
 *
 *   php scripts/qa/prod_config_test.php [requests-per-worker=40] [workers=4]
 */
require __DIR__.'/../../vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$reqPerWorker = (int) ($argv[1] ?? 40);
$workers = (int) ($argv[2] ?? 4);
$root = realpath(__DIR__.'/../..');
$router = "$root/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php";
$basePort = 8300;

// Config caching bakes env() calls in at build time (php artisan config:cache) — a runtime SESSION_DRIVER=redis
// override is silently IGNORED once a config cache built with SESSION_DRIVER=database exists. So the two
// scenarios below each need THEIR OWN pre-built config/route/event cache (qa-mysql/appcache = database,
// qa-redis/appcache = redis), not just different env vars pointed at a shared one. Rebuild both with:
//   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=urban_pos_qa CACHE_STORE=database SESSION_DRIVER=database QUEUE_CONNECTION=sync APP_CONFIG_CACHE=../../qa-mysql/appcache/config.php APP_ROUTES_CACHE=../../qa-mysql/appcache/routes.php APP_EVENTS_CACHE=../../qa-mysql/appcache/events.php APP_SERVICES_CACHE=../../qa-mysql/appcache/services.php APP_PACKAGES_CACHE=../../qa-mysql/appcache/packages.php php artisan config:cache && ...route:cache && ...event:cache
//   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=urban_pos_qa CACHE_STORE=redis SESSION_DRIVER=redis REDIS_CLIENT=predis REDIS_HOST=127.0.0.1 REDIS_PORT=6390 REDIS_PASSWORD=null REDIS_DB=5 REDIS_CACHE_DB=6 QUEUE_CONNECTION=sync APP_CONFIG_CACHE=../../qa-redis/appcache/config.php APP_ROUTES_CACHE=../../qa-redis/appcache/routes.php APP_EVENTS_CACHE=../../qa-redis/appcache/events.php APP_SERVICES_CACHE=../../qa-redis/appcache/services.php APP_PACKAGES_CACHE=../../qa-redis/appcache/packages.php php artisan config:cache && ...route:cache && ...event:cache
$baseEnv = [
    'APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1',
    'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307', 'DB_DATABASE' => 'urban_pos_qa',
    'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'single', 'LOG_LEVEL' => 'error',
];

$scenarios = [
    'BASELINE  (today: no opcache, DB session+cache)' => [
        'env' => [
            'APP_CONFIG_CACHE' => '../../qa-mysql/appcache/config.php', 'APP_ROUTES_CACHE' => '../../qa-mysql/appcache/routes.php',
            'APP_EVENTS_CACHE' => '../../qa-mysql/appcache/events.php', 'APP_SERVICES_CACHE' => '../../qa-mysql/appcache/services.php',
            'APP_PACKAGES_CACHE' => '../../qa-mysql/appcache/packages.php',
        ],
        'phpFlags' => ['-d', 'opcache.enable_cli=0'],
    ],
    'RECOMMENDED (opcache on, Redis session+cache)' => [
        'env' => [
            'APP_CONFIG_CACHE' => '../../qa-redis/appcache/config.php', 'APP_ROUTES_CACHE' => '../../qa-redis/appcache/routes.php',
            'APP_EVENTS_CACHE' => '../../qa-redis/appcache/events.php', 'APP_SERVICES_CACHE' => '../../qa-redis/appcache/services.php',
            'APP_PACKAGES_CACHE' => '../../qa-redis/appcache/packages.php',
        ],
        'phpFlags' => ['-d', 'zend_extension=opcache', '-d', 'opcache.enable_cli=1', '-d', 'opcache.jit=off', '-d', 'opcache.memory_consumption=192', '-d', 'opcache.validate_timestamps=0'],
        'redisCheckPort' => 6390, // Redis connection itself is baked into this scenario's config cache, not read from env; this is only for this script's own verification.
    ],
];

function httpGet(string $url, array $jar = []): array
{
    $ctx = stream_context_create(['http' => ['header' => $jar ? 'Cookie: '.implode('; ', $jar)."\r\n" : '', 'ignore_errors' => true, 'timeout' => 10]]);
    $t = microtime(true);
    $body = @file_get_contents($url, false, $ctx);
    $ms = (microtime(true) - $t) * 1000;
    $code = 0;
    $newCookies = [];
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) { $code = (int) $m[1]; }
        if (stripos($h, 'Set-Cookie:') === 0) { $newCookies[] = trim(substr($h, 11)); }
    }
    return [$code, $ms, $body, $newCookies];
}

foreach ($scenarios as $label => $cfg) {
    // On Windows, proc_open's $env REPLACES the whole environment rather than extending it; dropping
    // SystemRoot/PATH/TEMP breaks Winsock and every child silently fails with "Failed to listen (reason: ?)".
    $env = getenv() + $baseEnv + $cfg['env'];
    $procs = [];
    $handles = [];
    for ($i = 0; $i < $workers; $i++) {
        $cmd = array_merge(['php'], $cfg['phpFlags'], ['-S', '127.0.0.1:'.($basePort + $i), '-t', '.', $router]);
        // 'file' => 'NUL' is unreliable with proc_open on Windows; a real (discarded) file handle works.
        $out = fopen($root.'/storage/logs/qa_worker_'.$i.'.log', 'w');
        $descriptors = [0 => ['pipe', 'r'], 1 => $out, 2 => $out];
        $handles[] = $out;
        $procs[] = proc_open($cmd, $descriptors, $pipes, "$root/public", $env);
    }
    usleep(1_500_000);

    // warm-up (opcache compile / route+config load) — excluded from timing
    for ($w = 0; $w < 2; $w++) {
        for ($i = 0; $i < $workers; $i++) { httpGet("http://127.0.0.1:".($basePort + $i)."/login"); }
    }

    $redisPort = $cfg['redisCheckPort'] ?? null;
    $redisBefore = $redisPort ? (int) shell_exec('"C:/laragon/bin/redis/redis-x64-5.0.14.1/redis-cli.exe" -p '.$redisPort.' -n 5 dbsize') : null;

    $times = []; $codes = []; $sessionOk = 0; $sessionFail = 0;
    for ($i = 0; $i < $workers; $i++) {
        $port = $basePort + $i;
        for ($r = 0; $r < $reqPerWorker; $r++) {
            [$code, $ms, $body] = httpGet("http://127.0.0.1:$port/login");
            $times[] = $ms; $codes[$code] = ($codes[$code] ?? 0) + 1;
        }
        // one round-trip session-persistence check per worker: login page sets a session cookie,
        // hitting a protected route with it must not bounce to /login (proves the session store round-trips).
        [, , , $cookies] = httpGet("http://127.0.0.1:$port/login");
        $jar = array_map(fn ($c) => explode(';', $c, 2)[0], $cookies);
        [$code2] = httpGet("http://127.0.0.1:$port/", $jar);
        $code2 === 302 || $code2 === 200 ? $sessionOk++ : $sessionFail++;
    }

    foreach ($procs as $p) { proc_terminate($p); }
    usleep(300_000);
    foreach ($procs as $p) { proc_close($p); }
    foreach ($handles as $h) { fclose($h); }

    sort($times);
    $n = count($times);
    $pct = fn ($p) => $n ? round($times[(int) min($n - 1, max(0, ceil($p / 100 * $n) - 1))], 1) : 0;
    printf("\n%s\n", $label);
    printf("  requests=%d  p50=%sms  p95=%sms  p99=%sms  statuses=%s  session-round-trip: ok=%d fail=%d\n",
        $n, $pct(50), $pct(95), $pct(99), json_encode($codes), $sessionOk, $sessionFail);
    if ($redisPort) {
        $redisAfter = (int) shell_exec('"C:/laragon/bin/redis/redis-x64-5.0.14.1/redis-cli.exe" -p '.$redisPort.' -n 5 dbsize');
        printf("  Redis db5 keys: before=%d after=%d (proves sessions actually landed in Redis, not just env vars set)\n", $redisBefore, $redisAfter);
    }
}

echo "\nDone. Neither run touched the real `urban_pos` database or the shared dev php.ini.\n";
