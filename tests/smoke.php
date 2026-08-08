#!/usr/bin/env php
<?php
/**
 * Lumen smoke tests — static analysis for the module package.
 *
 * Run: php tests/smoke.php
 */

$root = dirname(__DIR__);
$errors = 0;
$warnings = 0;

function module_file($name) { return "$name.module.php"; }

function pass($msg)  { echo "  \033[32m✓\033[0m $msg\n"; }
function warn($msg)  { global $warnings; $warnings++; echo "  \033[33m⚠\033[0m $msg\n"; }
function fail($msg)  { global $errors; $errors++; echo "  \033[31m✗\033[0m $msg\n"; }
function section($msg) { echo "\n\033[1m$msg\033[0m\n"; }
function files_content(array $files) {
    $content = '';
    foreach($files as $file) {
        if(file_exists($file)) $content .= "\n" . file_get_contents($file);
    }
    return $content;
}

// ---------------------------------------------------------------------------
// 1. File structure
// ---------------------------------------------------------------------------
section('1. File structure');

$expected = [
    module_file('Lumen'),
    module_file('FieldtypeLumen'),
    module_file('InputfieldLumen'),
    module_file('ProcessLumen'),
    module_file('TextformatterLumen'),
    'src/Core/LifecycleTrait.php',
    'src/Core/UploadTrait.php',
    'src/Core/DiagnosticsTrait.php',
    'src/Core/StreamApiTrait.php',
    'src/Core/ConfigUiTrait.php',
    'src/Fieldtype/HooksTrait.php',
    'src/Fieldtype/SchemaTrait.php',
    'src/Fieldtype/PlaybackTrait.php',
    'src/Inputfield/BootstrapTrait.php',
    'src/Inputfield/HooksTrait.php',
    'src/Inputfield/UploadTrait.php',
    'src/Inputfield/RenderTrait.php',
    'src/Admin/DashboardTrait.php',
    'src/Admin/SettingsTrait.php',
    'src/Admin/FiltersTrait.php',
    'src/Admin/UploadTrait.php',
    'src/Admin/VideoTrait.php',
    'src/Admin/AssetsTrait.php',
    'src/Admin/BootstrapTrait.php',
    'src/Admin/ActionsTrait.php',
    'src/Admin/ExecuteTrait.php',
    'src/Support/FieldsTrait.php',
    'assets/css/lumen-admin.css',
    'assets/js/lumen-admin.js',
    'README.md',
    'DOCUMENTATION.md',
    'CHANGELOG.md',
    'LICENSE',
    '.gitignore',
];

foreach($expected as $file) {
    if(file_exists("$root/$file")) {
        pass($file);
    } else {
        fail("Missing: $file");
    }
}

$unexpected = glob("$root/*.module");
foreach($unexpected as $f) {
    warn("Old extension — should be .module.php: " . basename($f));
}

foreach(['info.json', 'info.php'] as $suffix) {
    $found = glob("$root/*.$suffix");
    foreach($found as $f) {
        warn("Should not exist: " . basename($f));
    }
}

// ---------------------------------------------------------------------------
// 2. PHP syntax
// ---------------------------------------------------------------------------
section('2. PHP syntax');

$moduleFiles = glob("$root/*.module.php");
$srcPhpFiles = glob("$root/src/*/*.php") ?: [];
foreach(array_merge($moduleFiles, $srcPhpFiles) as $file) {
    $name = basename($file);
    $output = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    if($code === 0) {
        pass("$name — syntax OK");
    } else {
        fail("$name — " . implode("\n", $output));
    }
}

// ---------------------------------------------------------------------------
// 3. Class structure
// ---------------------------------------------------------------------------
section('3. Class structure');

$modules = [
    module_file('Lumen') => ['class' => 'Lumen', 'extends' => 'WireData', 'implements' => ['Module', 'ConfigurableModule'], 'version' => 119],
    module_file('FieldtypeLumen') => ['class' => 'FieldtypeLumen', 'extends' => 'FieldtypeFile', 'implements' => ['Module'], 'version' => 119],
    module_file('InputfieldLumen') => ['class' => 'InputfieldLumen', 'extends' => 'InputfieldFile', 'implements' => ['Module'], 'version' => 119],
    module_file('ProcessLumen') => ['class' => 'ProcessLumen', 'extends' => 'Process', 'implements' => ['Module'], 'version' => 119],
    module_file('TextformatterLumen') => ['class' => 'TextformatterLumen', 'extends' => 'Textformatter', 'implements' => ['Module', 'ConfigurableModule'], 'version' => 119],
];

foreach($modules as $file => $spec) {
    $name = $spec['class'];
    $content = file_get_contents("$root/$file");
    if(preg_match("/class\s+{$spec['class']}\s+extends\s+{$spec['extends']}/", $content)) {
        pass("$name extends {$spec['extends']}");
    } else {
        fail("$name — missing or wrong extends {$spec['extends']}");
    }

    foreach($spec['implements'] as $iface) {
        if(preg_match("/implements.*{$iface}/", $content)) {
            pass("$name implements $iface");
        } else {
            fail("$name — missing implements $iface");
        }
    }

    if(preg_match("/'version'\s*=>\s*{$spec['version']}/", $content)) {
        pass("$name version = {$spec['version']}");
    } else {
        warn("$name — version mismatch, expected {$spec['version']}");
    }
}

// ---------------------------------------------------------------------------
// 4. Dependencies consistency
// ---------------------------------------------------------------------------
section('4. Dependencies consistency');

$lumen = files_content([
    "$root/" . module_file('Lumen'),
    ...(glob("$root/src/Core/*.php") ?: []),
]);
$ft = files_content([
    "$root/" . module_file('FieldtypeLumen'),
    ...(glob("$root/src/Fieldtype/*.php") ?: []),
]);
$if = files_content([
    "$root/" . module_file('InputfieldLumen'),
    ...(glob("$root/src/Inputfield/*.php") ?: []),
]);
$pl = files_content([
    "$root/" . module_file('ProcessLumen'),
    ...(glob("$root/src/Admin/*.php") ?: []),
    ...(glob("$root/src/Support/*.php") ?: []),
]);
$processSources = files_content(array_merge(
    ["$root/" . module_file('ProcessLumen')],
    glob("$root/src/Admin/*.php") ?: [],
    glob("$root/src/Support/*.php") ?: []
));

foreach(['FieldtypeLumen', 'InputfieldLumen', 'ProcessLumen'] as $m) {
    if(strpos($lumen, "'$m'") !== false) pass("Lumen installs $m");
    else fail("Lumen missing installs: $m");
}

if(strpos($ft, "'Lumen'") !== false) pass("FieldtypeLumen requires Lumen");
else fail("FieldtypeLumen missing requires: Lumen");

if(strpos($ft, "'InputfieldLumen'") !== false) pass("FieldtypeLumen installs InputfieldLumen");
else fail("FieldtypeLumen missing installs: InputfieldLumen");

if(strpos($if, "'FieldtypeLumen'") !== false) pass("InputfieldLumen requires FieldtypeLumen");
else fail("InputfieldLumen missing requires: FieldtypeLumen");

foreach(['FieldtypeLumen', 'Lumen'] as $m) {
    if(strpos($pl, "'$m'") !== false) pass("ProcessLumen requires $m");
    else fail("ProcessLumen missing requires: $m");
}

$runtimeSources = files_content(array_merge($moduleFiles, $srcPhpFiles ?: []));
if(stripos($runtimeSources, 'Channels') === false) {
    pass('Lumen runtime has no Channels dependency');
} else {
    fail('Lumen runtime must remain independent from Channels');
}

// ---------------------------------------------------------------------------
// 5. Hook registration
// ---------------------------------------------------------------------------
section('5. Hook registration');

if(preg_match('/Pagefile::stream/', $ft)) pass("FieldtypeLumen registers frontend Pagefile hooks");
else fail("FieldtypeLumen missing frontend Pagefile hooks");

foreach(['streamUrl', 'streamEmbed', 'streamThumbnail', 'streamPreview', 'streamReady'] as $h) {
    if(strpos($ft, "Pagefile::{$h}") !== false) pass("FieldtypeLumen registers Pagefile::$h");
    else fail("FieldtypeLumen missing hook: Pagefile::$h");
}

if(strpos($ft, "Pages::saved") !== false) pass("FieldtypeLumen registers Pages::saved");
else fail("FieldtypeLumen missing hook: Pages::saved");

if(strpos($ft, "CloudCache::isCacheable") !== false
    && strpos($ft, "isSharedPageCacheSafe") !== false
    && strpos($lumen, "function isSharedPageCacheSafe") !== false) {
    pass("Lumen fails closed for expiring signed playback URLs in CloudCache");
} else {
    fail("Lumen does not protect signed playback URLs from shared page caching");
}

if(strpos($lumen, "function invalidatePageCache") !== false
    && strpos($if, "invalidatePageCache") !== false
    && strpos($if, "\$before !== \$after") !== false) {
    pass("Stream status changes invalidate affected CloudCache pages");
} else {
    fail("Stream metadata changes do not invalidate affected CloudCache pages");
}

// ---------------------------------------------------------------------------
// 6. SQL safety
// ---------------------------------------------------------------------------
section('6. SQL safety');

foreach($moduleFiles as $file) {
    $name = basename($file);
    $content = file_get_contents($file);

    if(preg_match('/^ +\t/m', $content))
        fail("$name — mixed leading spaces and tabs");
    else
        pass("$name — leading indentation is consistent");

    if(preg_match('/->exec\(\s*["\'].*\$/s', $content))
        warn("$name — potential raw exec() with variable interpolation");

    if(strpos($content, 'escapeStr') !== false)
        warn("$name — uses escapeStr() instead of prepared statements");

    if(strpos($content, 'prepare(') !== false)
        pass("$name — uses prepared statements");
}

// ---------------------------------------------------------------------------
// 7. Konkat design system
// ---------------------------------------------------------------------------
section('7. Konkat design system');

if(strpos($processSources, 'pw-wrap') !== false) pass("ProcessLumen uses pw-wrap");
else warn("ProcessLumen missing pw-wrap panel wrapper");

if(strpos($processSources, 'uk-subnav uk-subnav-pill') !== false
    && strpos($processSources, 'lumen-admin-nav') !== false) {
    pass("ProcessLumen uses native pill workspace navigation");
} else {
    fail("ProcessLumen missing native workspace navigation");
}

if(strpos($processSources, 'lumen-usage-panel') !== false
    && strpos($processSources, '<details') !== false) {
    pass("ProcessLumen keeps secondary usage estimates in a disclosure");
} else {
    fail("ProcessLumen usage estimates dominate the primary workspace");
}

if(strpos($processSources, 'lumen-sort-control') !== false
    && strpos($processSources, '<select class="uk-select uk-form-small"') !== false) {
    pass("ProcessLumen uses a compact native sort control");
} else {
    fail("ProcessLumen is missing the compact sort control");
}

if(strpos($processSources, "\$key === 'total' ? \$baseUrl . '#library'") !== false
    && strpos($processSources, "(string) \$activeStatus === ''") !== false) {
    pass("ProcessLumen total status returns to the complete library");
} else {
    fail("ProcessLumen total status may apply an invalid status filter");
}

if(preg_match('/uk-table[^"]*uk-table-divider/', $processSources)) pass("ProcessLumen uses uk-table uk-table-divider");
else warn("ProcessLumen — consider using uk-table classes");

if(strpos($if, 'uk-label') !== false) pass("InputfieldLumen uses uk-label");
else warn("InputfieldLumen — use uk-label");

if(strpos($if, 'badge badge-') === false) pass("InputfieldLumen has no Bootstrap badge classes");
else fail("InputfieldLumen still uses Bootstrap badge classes");

if(strpos($processSources, '<style>') === false && strpos($processSources, 'renderUiAssets') === false) pass("ProcessLumen has no injected custom CSS");
else fail("ProcessLumen still injects custom CSS");

$adminCss = file_get_contents("$root/assets/css/lumen-admin.css");
if(strpos($adminCss, '.ProcessLumen') !== false
    && strpos($adminCss, 'var(--pw-') !== false
    && strpos($adminCss, 'linear-gradient') === false) {
    pass("ProcessLumen CSS is scoped and uses current --pw-* tokens");
} else {
    fail("ProcessLumen CSS does not follow pw-design-system boundaries");
}

if(strpos($if, '<style>') === false && strpos($if, 'LumenFileItem') === false) pass("InputfieldLumen uses native InputfieldFile markup");
else fail("InputfieldLumen still uses custom file item styling");

$settingsSource = file_get_contents("$root/src/Admin/SettingsTrait.php");
if(strpos($settingsSource, 'value="" autocomplete="new-password"') !== false
    && strpos($settingsSource, "if(\$data['cfApiToken'] !== '')") !== false) {
    pass("ProcessLumen never renders the stored Cloudflare token into the DOM");
} else {
    fail("ProcessLumen settings may expose or erase the stored Cloudflare token");
}

$configUiSource = file_get_contents("$root/src/Core/ConfigUiTrait.php");
if(strpos($configUiSource, "get('InputfieldText')") !== false
    && strpos($configUiSource, "name = 'cfAccountId'") !== false
    && strpos($configUiSource, "attr('type', 'password')") !== false
    && strpos($configUiSource, "attr('value', '')") !== false
    && strpos($configUiSource, "addHookAfter('processInput'") !== false
    && strpos($configUiSource, "\$f->value = \$data['cfApiToken']") === false) {
    pass("Module config renders Account ID as text and never renders the stored API token");
} else {
    fail("Module config credential fields are unsafe or use the wrong controls");
}

// ---------------------------------------------------------------------------
// 8. XSS protection
// ---------------------------------------------------------------------------
section('8. XSS protection');

foreach([module_file('ProcessLumen'), module_file('InputfieldLumen')] as $file) {
    $content = $file === module_file('ProcessLumen') ? $processSources : $if;
    $count = substr_count($content, 'sanitizer->entities');
    if($count > 0) pass(basename($file) . " — sanitizer->entities() x$count");
    else warn(basename($file) . " — no sanitizer->entities()");
}

// ---------------------------------------------------------------------------
// 9. API surface
// ---------------------------------------------------------------------------
section('9. API surface');

foreach(['getStreamUrl', 'getStreamEmbed', 'getStreamThumbnail', 'getStreamPreview', 'isStreamReady'] as $m) {
    if(preg_match("/public\s+function\s+$m/", $ft)) pass("FieldtypeLumen::$m is public");
    else fail("FieldtypeLumen::$m missing or not public");
}

foreach(['getStreamUrl', 'getStreamEmbed', 'getStreamThumbnail', 'getStreamPreview', 'isStreamReady'] as $m) {
    if(strpos($if, "fieldtype()->$m") !== false) pass("InputfieldLumen delegates $m");
    else warn("InputfieldLumen may not delegate $m");
}

foreach(['streamOrientation', 'isShortFormVideo', 'attachLocalVideo', 'attachUploadedVideo'] as $m) {
    if(preg_match("/public\s+function\s+$m/", $lumen)) pass("Lumen::$m is public");
    else fail("Lumen::$m missing or not public");
}

if(strpos($lumen, "\$duration <= \$maximum") !== false
    && strpos($lumen, "=== 'portrait'") !== false
    && strpos($lumen, 'stream_ready') !== false) {
    pass('Short-form eligibility is bounded by duration, portrait orientation, and ready state');
} else {
    fail('Short-form eligibility contract is incomplete');
}

// ---------------------------------------------------------------------------
// 10. HTTP client consistency
// ---------------------------------------------------------------------------
section('10. HTTP client');

if(strpos($lumen, 'function uploadMultipartFile') !== false && strpos($lumen, 'curl_file_create') !== false) {
    pass("Lumen — central multipart upload transport");
} else {
    fail("Lumen — missing central multipart upload transport");
}

if(strpos($pl, 'curl_') === false) pass("ProcessLumen — no HTTP client (correct)");
else fail("ProcessLumen uses direct curl_*");

if(strpos($ft, 'curl_') === false) pass("FieldtypeLumen — no HTTP client (correct)");
else fail("FieldtypeLumen uses direct curl_*");

if(strpos($ft, "LazyCron::everyMinute") !== false
    && strpos($ft, 'hookRefreshPendingStreams') !== false
    && strpos($ft, '$remaining = 10') !== false) {
    pass('Pending Stream metadata refresh is automatic and bounded');
} else {
    fail('Pending Stream metadata refresh is missing or unbounded');
}

// InputfieldLumen — curl only in uploadTUS
$tusPos = strpos($if, 'function uploadTUS');
$curlOutside = false;
preg_match_all('/curl_/', $if, $m, PREG_OFFSET_CAPTURE);
foreach($m[0] as $match) {
    if($tusPos === false || $match[1] < $tusPos) $curlOutside = true;
}
if(!$curlOutside) pass("InputfieldLumen — curl only in uploadTUS (OK)");
else fail("InputfieldLumen — curl used outside uploadTUS");

// ---------------------------------------------------------------------------
// 11. Administrative request security
// ---------------------------------------------------------------------------
section('11. Administrative request security');

$postFormCount = substr_count($processSources, '<form method="post"');
$csrfInputCount = substr_count($processSources, '$this->csrfInput()');
if($postFormCount > 0 && $csrfInputCount >= $postFormCount) {
    pass("Every dashboard POST form renders a CSRF token ($postFormCount/$csrfInputCount)");
} else {
    fail("Dashboard POST forms are missing CSRF tokens ($postFormCount forms, $csrfInputCount token calls)");
}

if(strpos($pl, 'if($mutation) $this->validateCsrf();') !== false) {
    pass('Dashboard mutations validate the ProcessWire CSRF token');
} else {
    fail('Dashboard mutations do not validate the ProcessWire CSRF token');
}

if(strpos($pl, '___executeAddField') !== false
    && strpos($pl, "\$this->input->post('add_field')") !== false
    && strpos($processSources, 'name="add_field"') !== false) {
    pass('Field creation uses a protected POST action');
} else {
    fail('Field creation is not protected by a POST action');
}

if(strpos($lumen, 'public function deleteStreamVideo') !== false
    && strpos($processSources, '$lumen->deleteStreamVideo') !== false
    && strpos($if, '$this->lumen()->deleteStreamVideo') !== false) {
    pass('Page-field and bulk deletion share the central Stream deletion API');
} else {
    fail('Stream deletion is not centralized across all admin workflows');
}

if(strpos($lumen, "->delete(\$url") !== false
    && strpos($lumen, "->patch(\$url") !== false
    && strpos($lumen, '->setMethod(') === false) {
    pass('DELETE and PATCH use the supported ProcessWire WireHttp API');
} else {
    fail('DELETE or PATCH uses an unsupported WireHttp transport');
}

// ===========================================================================
section('RESULT');
if($errors === 0 && $warnings === 0) {
    echo "\n  \033[32mAll checks passed. 11/11\033[0m\n\n";
} elseif($errors === 0) {
    echo "\n  \033[33m$warnings warning(s), 0 errors.\033[0m\n\n";
} else {
    echo "\n  \033[31m$errors error(s), $warnings warning(s).\033[0m\n\n";
}
exit($errors > 0 ? 1 : 0);
