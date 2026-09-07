#!/usr/bin/env php
<?php
/**
 * Genera el grafo de dependencias del CRM a partir del código fuente.
 *
 * Analiza estáticamente (sin arrancar Laravel ni necesitar vendor/):
 *   - routes/web.php, routes/api.php       → grupos de rutas → controladores
 *   - app/Http/Controllers                  → modelos, servicios, mails, vistas
 *   - app/Services                          → servicios, modelos, APIs externas
 *   - app/Models                            → relaciones Eloquent
 *   - app/Console/Commands + routes/console → scheduler → comandos → deps
 *   - app/Providers/AppServiceProvider.php  → eventos de modelo (observers)
 *   - bootstrap/app.php                     → alias de middleware
 *
 * Salida (por defecto en docs/graph/):
 *   crm-graph.json     nodos + aristas tipadas (para herramientas / visualizadores)
 *   crm-graph.dot      Graphviz completo, agrupado por capa
 *   crm-overview.dot   Graphviz sin modelos: entradas → controladores → servicios → APIs
 *   crm-models.dot     Graphviz sólo modelos Eloquent y sus relaciones
 *   explorer.html      explorador interactivo (d3) generado desde scripts/graph-explorer.template.html
 *
 * Render:  dot -Tsvg docs/graph/crm-overview.dot -o docs/graph/crm-overview.svg
 *          (o `npx -p @viz-js/viz` si no hay Graphviz instalado)
 *
 * Uso:  php scripts/build-graph.php [--out=docs/graph] [--with-views]
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$opts = getopt('', ['out::', 'with-views']);
$outDir = $root . '/' . ($opts['out'] ?? 'docs/graph');
$withViews = array_key_exists('with-views', $opts);

// ─────────────────────────────────────────────────────────────────────────────
// Grafo
// ─────────────────────────────────────────────────────────────────────────────
final class Graph
{
    /** @var array<string, array{id:string,label:string,type:string,meta:array}> */
    public array $nodes = [];
    /** @var array<string, array{from:string,to:string,type:string,meta:array}> */
    public array $edges = [];

    public function node(string $id, string $label, string $type, array $meta = []): string
    {
        if (!isset($this->nodes[$id])) {
            $this->nodes[$id] = ['id' => $id, 'label' => $label, 'type' => $type, 'meta' => $meta];
        } elseif ($meta) {
            foreach ($meta as $k => $v) {
                $prev = $this->nodes[$id]['meta'][$k] ?? null;
                // listas (closures, etc.) se unen; escalares se sobrescriben
                $this->nodes[$id]['meta'][$k] = (is_array($v) && is_array($prev) && array_is_list($v))
                    ? array_values(array_unique(array_merge($prev, $v)))
                    : $v;
            }
        }
        return $id;
    }

    public function edge(string $from, string $to, string $type, array $meta = []): void
    {
        // Las relaciones Eloquent se distinguen por método (Task→User: assignee y creator)
        $key = "$from|$to|$type" . (isset($meta['method']) ? '|' . $meta['method'] : '');
        if (!isset($this->edges[$key])) {
            $this->edges[$key] = ['from' => $from, 'to' => $to, 'type' => $type, 'meta' => $meta];
        } elseif ($meta) {
            $this->edges[$key]['meta'] = array_merge_recursive($this->edges[$key]['meta'], $meta);
        }
    }
}

$g = new Graph();

// ─────────────────────────────────────────────────────────────────────────────
// Utilidades
// ─────────────────────────────────────────────────────────────────────────────
function phpFiles(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    $out = [];
    foreach ($it as $f) {
        if ($f->getExtension() === 'php') {
            $out[] = $f->getPathname();
        }
    }
    sort($out);
    return $out;
}

function classNameFromFile(string $root, string $file): string
{
    // app/Http/Controllers/Admin/LeadController.php → Http/Controllers/Admin/LeadController
    return str_replace(['\\'], '/', substr($file, strlen($root . '/app/'), -4));
}

/** Referencias a clases de App\{Namespace} dentro de un archivo (use, FQCN inline, new X(, app(X::class)). */
function appRefs(string $src, string $namespace, array $known): array
{
    $found = [];
    // use App\Models\Foo;  |  \App\Models\Foo::  |  App\Models\Foo
    if (preg_match_all('/\bApp\\\\' . $namespace . '\\\\([A-Za-z_]+)/', $src, $m)) {
        foreach ($m[1] as $c) {
            $found[$c] = true;
        }
    }
    // new FooService(  |  app(FooService::class)  |  FooService::method(
    foreach ($known as $c) {
        if (preg_match('/\b(?:new\s+' . $c . '\s*\(|app\(\s*' . $c . '::class|\b' . $c . '::[a-zA-Z_])/', $src)) {
            $found[$c] = true;
        }
    }
    ksort($found);
    return array_keys($found);
}

function shortLabel(string $class): string
{
    return basename($class);
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Modelos y relaciones
// ─────────────────────────────────────────────────────────────────────────────
$modelFiles = phpFiles("$root/app/Models");
$models = array_map(fn($f) => basename($f, '.php'), $modelFiles);

foreach ($modelFiles as $file) {
    $name = basename($file, '.php');
    $src = file_get_contents($file);
    $meta = [
        'file' => 'app/Models/' . basename($file),
        'soft_deletes' => (bool) preg_match('/\bSoftDeletes\b/', $src),
        'utility' => in_array($name, ['ActivityLog', 'Setting'], true), // transversales: se omiten en el DOT
    ];
    if (preg_match('/protected\s+\$table\s*=\s*[\'"]([a-z_]+)[\'"]/', $src, $t)) {
        $meta['table'] = $t[1];
    }
    $g->node("model:$name", $name, 'model', $meta);

    if (preg_match_all(
        '/function\s+(\w+)\s*\([^)]*\)[^{]*\{\s*return\s+\$this->(hasMany|belongsTo|belongsToMany|hasOne|hasManyThrough|hasOneThrough|morphTo|morphMany|morphOne|morphToMany)\(\s*(?:([A-Za-z\\\\]+)::class)?/s',
        $src,
        $rels,
        PREG_SET_ORDER
    )) {
        foreach ($rels as $r) {
            [$_, $method, $kind, $target] = $r + [null, null, null, null];
            $target = $target ? basename(str_replace('\\', '/', $target)) : null;
            if ($kind === 'morphTo') {
                $g->node('model:*polymorphic', '(polimórfico)', 'model', ['virtual' => true]);
                $g->edge("model:$name", 'model:*polymorphic', 'relation', ['kind' => $kind, 'method' => $method]);
                continue;
            }
            if ($target) {
                $g->node("model:$target", $target, 'model');
                $g->edge("model:$name", "model:$target", 'relation', ['kind' => $kind, 'method' => $method]);
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Servicios → servicios / modelos / mails / APIs externas
// ─────────────────────────────────────────────────────────────────────────────
$externalHosts = [
    'api.mercadopago.com'      => ['Mercado Pago API', 'pagos'],
    'api-m.paypal.com'         => ['PayPal API', 'pagos'],
    'api-m.sandbox.paypal.com' => ['PayPal API', 'pagos'],
    'www.facturapi.io'         => ['Facturapi (PAC)', 'facturación'],
    'api.20i.com'              => ['20i API (hosting/correo/DNS)', 'hosting'],
    'sandbox.cosmotown.com'    => ['Cosmotown API (dominios)', 'dominios'],
    'api.dmchamp.com'          => ['DM Champ API (WhatsApp)', 'mensajería'],
    'graph.facebook.com'       => ['Meta Conversions API', 'marketing'],
];

$serviceFiles = phpFiles("$root/app/Services");
$services = array_map(fn($f) => basename($f, '.php'), $serviceFiles);
$mailFiles = phpFiles("$root/app/Mail");
$mails = array_map(fn($f) => basename($f, '.php'), $mailFiles);

foreach ($mailFiles as $file) {
    $name = basename($file, '.php');
    $src = file_get_contents($file);
    $meta = ['file' => 'app/Mail/' . basename($file)];
    if (preg_match('/view:\s*[\'"]([a-z\.\-_]+)[\'"]|view\(\s*[\'"]([a-z\.\-_]+)[\'"]/', $src, $v)) {
        $meta['view'] = $v[1] ?: $v[2];
    }
    $g->node("mail:$name", $name, 'mail', $meta);
    if ($withViews && isset($meta['view'])) {
        $g->node('view:' . $meta['view'], $meta['view'], 'view');
        $g->edge("mail:$name", 'view:' . $meta['view'], 'renders');
    }
}

function attachCommonDeps(Graph $g, string $fromId, string $src, array $models, array $services, array $mails, bool $withViews): void
{
    foreach (appRefs($src, 'Services', $services) as $s) {
        if ("service:$s" !== $fromId) {
            $g->node("service:$s", $s, 'service');
            $g->edge($fromId, "service:$s", 'uses_service');
        }
    }
    foreach (appRefs($src, 'Models', $models) as $m) {
        $g->node("model:$m", $m, 'model');
        $g->edge($fromId, "model:$m", 'uses_model');
    }
    foreach (appRefs($src, 'Mail', $mails) as $m) {
        $g->node("mail:$m", $m, 'mail');
        $g->edge($fromId, "mail:$m", 'sends_mail');
    }
    if ($withViews && preg_match_all('/\bview\(\s*[\'"]([a-z0-9\.\-_]+)[\'"]/', $src, $vm)) {
        foreach (array_unique($vm[1]) as $v) {
            $g->node("view:$v", $v, 'view');
            $g->edge($fromId, "view:$v", 'renders');
        }
    }
}

foreach ($serviceFiles as $file) {
    $name = basename($file, '.php');
    $src = file_get_contents($file);
    $desc = '';
    if (preg_match('#/\*\*\s*\n\s*\*\s*(.+?)\n#', $src, $d)) {
        $desc = trim($d[1]);
    }
    $g->node("service:$name", $name, 'service', ['file' => 'app/Services/' . basename($file), 'description' => $desc]);
    attachCommonDeps($g, "service:$name", $src, $models, $services, $mails, $withViews);

    if (preg_match_all('#https?://([a-zA-Z0-9\.\-]+)#', $src, $hm)) {
        foreach (array_unique($hm[1]) as $host) {
            if (!isset($externalHosts[$host])) {
                continue;
            }
            [$label, $domain] = $externalHosts[$host];
            $id = 'external:' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($label));
            $g->node($id, $label, 'external', ['domain' => $domain]);
            $g->edge("service:$name", $id, 'calls_api', ['hosts' => [$host]]);
        }
    }
    // Finkok usa SDK SOAP (phpcfdi/finkok), no una URL literal
    if (preg_match('/PhpCfdi\\\\Finkok/', $src)) {
        $g->node('external:finkok_pac', 'Finkok (PAC, SOAP)', 'external', ['domain' => 'facturación']);
        $g->edge("service:$name", 'external:finkok_pac', 'calls_api', ['sdk' => 'phpcfdi/finkok']);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Controladores
// ─────────────────────────────────────────────────────────────────────────────
$controllerFiles = phpFiles("$root/app/Http/Controllers");
$controllerIds = []; // short class → node id
foreach ($controllerFiles as $file) {
    $rel = classNameFromFile($root, $file);               // Http/Controllers/Admin/LeadController
    $short = substr($rel, strlen('Http/Controllers/'));  // Admin/LeadController
    if ($short === 'Controller') {
        continue;
    }
    $src = file_get_contents($file);
    $area = str_contains($short, '/') ? explode('/', $short)[0] : 'Public';
    $id = "controller:$short";
    $controllerIds[basename($short)][] = $id;
    $g->node($id, basename($short), 'controller', ['area' => $area, 'file' => "app/$rel.php"]);
    attachCommonDeps($g, $id, $src, $models, $services, $mails, $withViews);
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. Rutas → controladores (scanner de líneas con pila de grupos)
//    Cada nodo "routes" representa una zona de acceso: prefijo + middleware del
//    grupo (p. ej. "/panel [auth, role:admin]"). El middleware inline de cada
//    ruta (throttle, etc.) va en la arista.
// ─────────────────────────────────────────────────────────────────────────────
function middlewareIn(string $line): array
{
    $out = [];
    if (preg_match_all('/middleware\(([^)]*)\)/', $line, $mm)) {
        foreach ($mm[1] as $inner) {
            if (preg_match_all("/'([^']+)'/", $inner, $q)) {
                $out = array_merge($out, $q[1]);
            }
            if (preg_match_all('/\\\\?(?:App\\\\Http\\\\Middleware\\\\)?(\w+)::class/', $inner, $c)) {
                $out = array_merge($out, $c[1]);
            }
        }
    }
    return array_values(array_unique($out));
}

function scanRoutes(Graph $g, string $file, string $kind, string $rootPrefix, array $controllerIds, array $commandBySignature): void
{
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    $uses = [];
    foreach ($lines as $l) {
        // Admite alias: use App\Http\Controllers\Portal\DnsController as PortalDnsController;
        if (preg_match('/^use\s+App\\\\Http\\\\Controllers\\\\([A-Za-z\\\\]+)(?:\s+as\s+(\w+))?;/', $l, $u)) {
            $path  = str_replace('\\', '/', $u[1]);
            $alias = $u[2] ?? basename($path);
            $uses[$alias] = $path;
        }
    }

    $stack = [];        // grupos abiertos: ['prefix', 'middleware', 'closure']
    $closureGroup = null; // id del grupo mientras se está dentro de una ruta-closure multilínea

    foreach ($lines as $l) {
        $trim = trim($l);
        $isRouteLine = str_starts_with($trim, 'Route::');

        // Cierre de grupo o de closure multilínea
        if (preg_match('/^\}\)/', $trim)) {
            $popped = array_pop($stack);
            if ($popped && $popped['closure']) {
                $closureGroup = null;
            }
            continue;
        }

        // Dentro de una closure multilínea: sólo nos interesa Artisan::call
        if ($closureGroup !== null && !$isRouteLine) {
            if (preg_match("/Artisan::call\('([a-z:\-]+)'\)/", $trim, $am) && isset($commandBySignature[$am[1]])) {
                $g->edge($closureGroup, $commandBySignature[$am[1]], 'invokes');
            }
            continue;
        }
        if (!$isRouteLine) {
            continue;
        }

        // Apertura de grupo
        if (preg_match('/->group\(function\s*\(\)\s*\{\s*$/', $trim)) {
            $prefix = preg_match("/prefix\('([^']*)'\)/", $trim, $p) ? trim($p[1], '/') : '';
            $stack[] = ['prefix' => $prefix, 'middleware' => middlewareIn($trim), 'closure' => false];
            continue;
        }

        // Contexto acumulado del grupo
        $groupPrefix = implode('/', array_filter(array_column($stack, 'prefix')));
        $groupMw = array_values(array_unique(array_merge([], ...array_map(fn($s) => $s['middleware'], $stack))));
        $inlineMw = array_values(array_diff(middlewareIn($trim), $groupMw));
        $base = trim($rootPrefix . '/' . $groupPrefix, '/');

        // Ruta (path + destino)
        $verb = $path = $ctrl = $method = $resource = null;
        if (preg_match("/Route::(?:middleware\([^)]*\)->)?resource\('([^']+)',\s*([A-Za-z]+)::class\)/", $trim, $r)) {
            [$_, $resource, $ctrl] = $r;
            $path = $resource;
        } elseif (preg_match("/Route::(get|post|put|patch|delete|any|match)\('([^']*)',\s*\[\\\\?(?:App\\\\Http\\\\Controllers\\\\)?([A-Za-z\\\\]+)::class,\s*'(\w+)'\]/", $trim, $m)) {
            [$_, $verb, $path, $ctrl, $method] = $m;
        } elseif (preg_match("/Route::(get|post|put|patch|delete|any|match)\('([^']*)',\s*(?:function|fn)/", $trim, $m)) {
            [$_, $verb, $path] = $m;
        } else {
            continue;
        }

        // Sin grupo: la "zona" es el primer segmento del path (/login, /auth, /api/webhooks …)
        $zone = $base;
        if ($groupPrefix === '') {
            $first = explode('/', trim($path, '/'))[0] ?? '';
            $first = preg_replace('/\{.*\}/', '', $first);
            $zone = trim($rootPrefix . '/' . $first, '/');
        }
        $groupId = 'routes:' . $kind . ':/' . $zone . ($groupMw ? '|' . implode(',', $groupMw) : '');
        $label = '/' . $zone . ($groupMw ? "\n[" . implode(', ', $groupMw) . ']' : '');
        $g->node($groupId, $label, 'routes', ['kind' => $kind, 'prefix' => '/' . $zone, 'middleware' => $groupMw]);

        $full = '/' . trim($base . '/' . trim((string) $path, '/'), '/');

        // Closure multilínea → recordar el grupo para capturar Artisan::call
        if ($ctrl === null) {
            $desc = strtoupper((string) $verb) . " $full (closure)";
            $g->node($groupId, $label, 'routes', ['closures' => [$desc]]);
            if (preg_match('/(?:function|fn)\s*\([^)]*\)\s*(?:=>\s*)?\{\s*$/', $trim)) {
                $stack[] = ['prefix' => '', 'middleware' => [], 'closure' => true];
                $closureGroup = $groupId;
            }
            continue;
        }

        // Resolver el controlador al nodo correcto (puede haber Admin/X y Api/X)
        $ctrlPath = str_contains($ctrl, '\\') ? str_replace('\\', '/', $ctrl) : ($uses[$ctrl] ?? $ctrl);
        $targets = [];
        foreach ($controllerIds[basename($ctrlPath)] ?? [] as $cid) {
            if (str_ends_with($cid, 'controller:' . $ctrlPath) || $cid === 'controller:' . $ctrlPath) {
                $targets[] = $cid;
            }
        }
        if (!$targets) {
            fwrite(STDERR, "  [aviso] controlador no resuelto en $file: $trim\n");
            continue;
        }

        if ($resource !== null) {
            $only = preg_match("/->only\(\[([^\]]+)\]\)/", $trim, $o) ? preg_replace('/[\'\s]/', '', $o[1]) : null;
            $except = preg_match("/->except\(\[?([^\]\)]+)\]?\)/", $trim, $e) ? preg_replace('/[\'\s]/', '', $e[1]) : null;
            $actions = $only ?: 'index,create,store,show,edit,update,destroy';
            if ($except) {
                $actions = implode(',', array_diff(explode(',', $actions), explode(',', $except)));
            }
            $desc = "RESOURCE $full [$actions]";
        } else {
            $desc = strtoupper($verb) . " $full → $method()";
        }
        if ($inlineMw) {
            $desc .= ' {' . implode(', ', $inlineMw) . '}';
        }
        foreach ($targets as $cid) {
            $g->edge($groupId, $cid, 'routes_to', ['routes' => [$desc], 'middleware' => $inlineMw]);
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. Comandos + scheduler (antes que rutas: /panel/backups invoca db:backup)
// ─────────────────────────────────────────────────────────────────────────────
$g->node('scheduler', 'Scheduler (schedule:run)', 'scheduler', ['file' => 'routes/console.php']);
$commandBySignature = [];
foreach (phpFiles("$root/app/Console/Commands") as $file) {
    $name = basename($file, '.php');
    $src = file_get_contents($file);
    $sig = preg_match('/\$signature\s*=\s*[\'"]([a-z:\-]+)/', $src, $s) ? $s[1] : $name;
    $id = "command:$name";
    $commandBySignature[$sig] = $id;
    $g->node($id, $sig, 'command', ['class' => $name, 'file' => 'app/Console/Commands/' . basename($file)]);
    attachCommonDeps($g, $id, $src, $models, $services, $mails, $withViews);
}
$console = file_get_contents("$root/routes/console.php");
if (preg_match_all("/Schedule::command\('([a-z:\-]+)[^']*'\)->(\w+)\(([^)]*)\)/", $console, $sm, PREG_SET_ORDER)) {
    foreach ($sm as $s) {
        if (isset($commandBySignature[$s[1]])) {
            $g->edge('scheduler', $commandBySignature[$s[1]], 'schedules', ['when' => $s[2] . '(' . trim($s[3], "'\"") . ')']);
        }
    }
}

scanRoutes($g, "$root/routes/web.php", 'web', '', $controllerIds, $commandBySignature);
scanRoutes($g, "$root/routes/api.php", 'api', 'api', $controllerIds, $commandBySignature);

// ─────────────────────────────────────────────────────────────────────────────
// 6. Observers de modelos (AppServiceProvider) y bindings
// ─────────────────────────────────────────────────────────────────────────────
$provider = file_get_contents("$root/app/Providers/AppServiceProvider.php");
if (preg_match_all('/(\w+)::(created|updated|deleted|saved|creating|updating)\(function\s*\((\w+)\s+\$\w+\)\s*use\s*\(\$(\w+)\)\s*\{(.*?)\n\s{8}\}\);/s', $provider, $om, PREG_SET_ORDER)) {
    foreach ($om as $o) {
        $eventId = "event:{$o[1]}.{$o[2]}";
        $g->node($eventId, "{$o[1]}::{$o[2]}", 'event', ['file' => 'app/Providers/AppServiceProvider.php']);
        $g->edge("model:{$o[1]}", $eventId, 'fires');
        if (preg_match_all('/->(\w+)\(/', $o[5], $calls)) {
            foreach ($services as $svc) {
                if (preg_match('/\$' . $o[4] . '\s*=\s*fn\s*\(\)\s*=>\s*app\(' . $svc . '::class\)/', $provider)) {
                    $g->edge($eventId, "service:$svc", 'uses_service', ['calls' => array_values(array_unique($calls[1]))]);
                }
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 7. Middleware (bootstrap/app.php)
// ─────────────────────────────────────────────────────────────────────────────
$bootstrap = file_get_contents("$root/bootstrap/app.php");
if (preg_match_all("/'([a-z\.]+)'\s*=>\s*\\\\App\\\\Http\\\\Middleware\\\\(\w+)::class/", $bootstrap, $mwm, PREG_SET_ORDER)) {
    foreach ($mwm as $m) {
        $g->node("middleware:{$m[2]}", "{$m[2]} ('{$m[1]}')", 'middleware', ['alias' => $m[1], 'file' => "app/Http/Middleware/{$m[2]}.php"]);
    }
}
foreach ($g->nodes as $n) {
    if ($n['type'] !== 'routes') {
        continue;
    }
    foreach ($n['meta']['middleware'] ?? [] as $alias) {
        if (str_ends_with($alias, 'Middleware') && is_file("$root/app/Http/Middleware/$alias.php")) {
            $g->node("middleware:$alias", "$alias (por clase)", 'middleware', ['alias' => null, 'file' => "app/Http/Middleware/$alias.php"]);
        }
        $alias = explode(':', $alias)[0];
        foreach ($g->nodes as $mw) {
            if ($mw['type'] !== 'middleware') {
                continue;
            }
            $class = explode(' ', $mw['label'])[0];
            if (($mw['meta']['alias'] ?? '') === $alias || $class === $alias) {
                $g->edge($n['id'], $mw['id'], 'guarded_by');
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 8. Salida
// ─────────────────────────────────────────────────────────────────────────────
@mkdir($outDir, 0777, true);

$byType = [];
foreach ($g->nodes as $n) {
    $byType[$n['type']] = ($byType[$n['type']] ?? 0) + 1;
}
$edgesByType = [];
foreach ($g->edges as $e) {
    $edgesByType[$e['type']] = ($edgesByType[$e['type']] ?? 0) + 1;
}
ksort($byType);
ksort($edgesByType);

$json = [
    'generated_at' => date('c'),
    'generator' => 'scripts/build-graph.php',
    'stats' => ['nodes' => count($g->nodes), 'edges' => count($g->edges), 'nodes_by_type' => $byType, 'edges_by_type' => $edgesByType],
    'nodes' => array_values($g->nodes),
    'edges' => array_values($g->edges),
];
file_put_contents("$outDir/crm-graph.json", json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

// DOT — tres vistas: completa, overview (sin modelos) y modelos (sólo relaciones)
$style = [
    'routes'     => ['shape' => 'cds',           'fillcolor' => '#dbeafe', 'cluster' => 'Entradas HTTP'],
    'scheduler'  => ['shape' => 'cds',           'fillcolor' => '#dbeafe', 'cluster' => 'Entradas HTTP'],
    'event'      => ['shape' => 'hexagon',       'fillcolor' => '#fef3c7', 'cluster' => 'Eventos de modelo'],
    'middleware' => ['shape' => 'note',          'fillcolor' => '#e5e7eb', 'cluster' => 'Middleware'],
    'controller' => ['shape' => 'box',           'fillcolor' => '#ede9fe', 'cluster' => 'Controladores'],
    'command'    => ['shape' => 'box',           'fillcolor' => '#ede9fe', 'cluster' => 'Comandos Artisan'],
    'service'    => ['shape' => 'component',     'fillcolor' => '#dcfce7', 'cluster' => 'Servicios'],
    'mail'       => ['shape' => 'tab',           'fillcolor' => '#fce7f3', 'cluster' => 'Mailables'],
    'model'      => ['shape' => 'ellipse',       'fillcolor' => '#fff7ed', 'cluster' => 'Modelos Eloquent'],
    'external'   => ['shape' => 'doubleoctagon', 'fillcolor' => '#fee2e2', 'cluster' => 'APIs externas'],
    'view'       => ['shape' => 'plaintext',     'fillcolor' => '#ffffff', 'cluster' => 'Vistas Blade'],
];
$edgeStyle = [
    'routes_to'    => 'color="#2563eb"',
    'guarded_by'   => 'color="#6b7280" style=dotted arrowhead=none',
    'uses_service' => 'color="#16a34a"',
    'uses_model'   => 'color="#f59e0b" style=dashed',
    'sends_mail'   => 'color="#db2777"',
    'calls_api'    => 'color="#dc2626" penwidth=2',
    'relation'     => 'color="#9a3412" fontsize=8',
    'schedules'    => 'color="#2563eb" fontsize=8',
    'invokes'      => 'color="#2563eb" style=dashed',
    'fires'        => 'color="#d97706" style=dashed',
    'renders'      => 'color="#9ca3af" style=dotted',
];

/**
 * @param callable(array):bool $nodeFilter  recibe un nodo
 * @param callable(array):bool $edgeFilter  recibe una arista
 */
function writeDot(Graph $g, string $path, string $title, string $rankdir, array $style, array $edgeStyle, callable $nodeFilter, callable $edgeFilter): void
{
    $nodes = array_filter($g->nodes, $nodeFilter);
    $edges = array_filter($g->edges, fn($e) => isset($nodes[$e['from']], $nodes[$e['to']]) && $edgeFilter($e));

    $dotId = fn(string $id) => '"' . str_replace('"', '\"', $id) . '"';
    $dot = "// Generado por scripts/build-graph.php — no editar a mano\n";
    $dot .= "digraph crm {\n  rankdir=$rankdir; splines=true; overlap=false; nodesep=0.3; ranksep=1.0;\n";
    $dot .= "  graph [fontname=\"Helvetica\" fontsize=11 labelloc=t label=\"$title\"];\n";
    $dot .= "  node  [fontname=\"Helvetica\" fontsize=10 style=filled];\n  edge  [fontname=\"Helvetica\" fontsize=9];\n\n";

    $clusters = [];
    foreach ($nodes as $n) {
        $clusters[$style[$n['type']]['cluster'] ?? 'Otros'][] = $n;
    }
    $ci = 0;
    foreach ($clusters as $ctitle => $cnodes) {
        $dot .= "  subgraph cluster_" . ($ci++) . " {\n    label=\"$ctitle\"; style=rounded; color=\"#9ca3af\";\n";
        foreach ($cnodes as $n) {
            $s = $style[$n['type']];
            $label = str_replace(['"', "\n"], ['\\"', '\\n'], $n['label']);
            $dot .= '    ' . $dotId($n['id']) . " [label=\"$label\" shape={$s['shape']} fillcolor=\"{$s['fillcolor']}\"];\n";
        }
        $dot .= "  }\n\n";
    }
    foreach ($edges as $e) {
        $attrs = $edgeStyle[$e['type']] ?? '';
        $lbl = match ($e['type']) {
            'relation'  => $e['meta']['kind'] . ($e['meta']['method'] !== lcfirst($nodes[$e['to']]['label']) ? " ({$e['meta']['method']})" : ''),
            'schedules' => $e['meta']['when'],
            default     => '',
        };
        if ($lbl !== '') {
            $attrs .= ' label="' . str_replace('"', '\"', $lbl) . '"';
        }
        $dot .= '  ' . $dotId($e['from']) . ' -> ' . $dotId($e['to']) . " [$attrs];\n";
    }
    $dot .= "}\n";
    file_put_contents($path, $dot);
}

$isUtility = fn(array $n) => (bool) ($n['meta']['utility'] ?? false);
$modelsWithEvents = [];
foreach ($g->edges as $e) {
    if ($e['type'] === 'fires') {
        $modelsWithEvents[$e['from']] = true;
    }
}

// 1) Completa: todo menos las aristas a modelos transversales (ActivityLog, Setting)
writeDot(
    $g, "$outDir/crm-graph.dot", 'CRM Mosley - grafo completo de dependencias', 'LR', $style, $edgeStyle,
    fn($n) => true,
    fn($e) => !($e['type'] === 'uses_model' && $isUtility($g->nodes[$e['to']]))
);

// 2) Overview: flujo entradas → controladores/comandos → servicios → APIs externas (+ eventos y mails)
writeDot(
    $g, "$outDir/crm-overview.dot", 'CRM Mosley - arquitectura: entradas > controladores > servicios > APIs externas', 'LR', $style, $edgeStyle,
    fn($n) => $n['type'] !== 'model' || isset($modelsWithEvents[$n['id']]),
    fn($e) => $e['type'] !== 'uses_model'
);

// 3) Modelos: sólo relaciones Eloquent
writeDot(
    $g, "$outDir/crm-models.dot", 'CRM Mosley - modelos Eloquent y relaciones', 'TB', $style, $edgeStyle,
    fn($n) => $n['type'] === 'model' && !$isUtility($n),
    fn($e) => $e['type'] === 'relation'
);

// Explorador interactivo (d3): plantilla + JSON embebido → un solo archivo HTML
$template = "$root/scripts/graph-explorer.template.html";
if (is_file($template)) {
    $embedded = str_replace('</', '<\/', json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    file_put_contents("$outDir/explorer.html", str_replace('__GRAPH_JSON__', $embedded, file_get_contents($template)));
}

fwrite(STDOUT, sprintf(
    "Grafo generado en %s\n  nodos: %d  aristas: %d\n  nodos por tipo: %s\n  aristas por tipo: %s\n",
    str_replace($root . '/', '', $outDir),
    count($g->nodes),
    count($g->edges),
    json_encode($byType, JSON_UNESCAPED_UNICODE),
    json_encode($edgesByType, JSON_UNESCAPED_UNICODE)
));
