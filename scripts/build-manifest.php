<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/*
 * Writes the "robots" section of manifest.json from the ai.robots.txt list and our
 * own bot descriptions. Other sections of the manifest are left as they are.
 *
 * Usage: php scripts/build-manifest.php <upstream robots.json> <data/bots.json> <manifest.json> [<new-bots.md>]
 *
 * - A bot described in data/bots.json is published exactly as described there.
 * - A bot that is only in ai.robots.txt is published with its operator, description
 *   and robots.txt behaviour taken from there, and with safe defaults for our
 *   judgement: category "other" and recommendation "allow", so nothing gets blocked
 *   that nobody has looked at. It is listed in <new-bots.md> so someone can
 *   describe it in data/bots.json.
 * - A bot that is only in data/bots.json (for example search engines, which
 *   ai.robots.txt does not list) is published as it is.
 * - Some names are skipped on purpose, see skip().
 */

[$script, $upstreamFile, $dataFile, $outFile, $newBotsFile] = array_pad($argv, 5, null);

if ($upstreamFile === null || $dataFile === null || $outFile === null) {
    fwrite(STDERR, "Usage: php build-manifest.php <robots.json> <bots.json> <manifest.json> [<new-bots.md>]\n");
    exit(1);
}

$upstream = json_decode((string) file_get_contents($upstreamFile), true);
$ours = json_decode((string) file_get_contents($dataFile), true);

if (!is_array($upstream) || !is_array($ours)) {
    fwrite(STDERR, "Could not read the upstream list or data/bots.json.\n");
    exit(1);
}

$tokens = array_values(array_filter(array_keys($upstream), static fn ($t): bool => !str_starts_with((string) $t, '__')));

// A list this short means the upstream file broke, not that the bots went away.
// Publishing it would wipe new entries from every shop's next refresh.
if (count($tokens) < 100) {
    fwrite(STDERR, 'The upstream list has only ' . count($tokens) . " entries. Nothing was written.\n");
    exit(1);
}
$bots = [];
$new = [];

foreach ($tokens as $token) {
    $token = (string) $token;

    if (skip($token, $tokens)) {
        continue;
    }

    $key = slug($token);
    $facts = facts((array) $upstream[$token]);

    // Described by us: published exactly as described. Our descriptions are
    // shortened by hand, and ai.robots.txt writes the operator as a sentence.
    if (isset($ours[$key])) {
        $bots[$key] = $ours[$key];

        continue;
    }

    $bots[$key] = [
        'token' => $token,
        'label' => $token,
        'vendor' => $facts['vendor'],
        'category' => 'other',
        'description' => $facts['description'],
        'docs_url' => $facts['docs_url'],
        'verify_host' => '',
        'respects' => $facts['respects'] !== '' ? $facts['respects'] : 'unclear',
        'default_policy' => 'allow',
    ];
    $new[$key] = $token;
}

// Bots described by us that ai.robots.txt does not list.
foreach ($ours as $key => $bot) {
    if (!isset($bots[$key])) {
        $bots[$key] = $bot;
    }
}

ksort($bots);

// The date moves only when the list does, so a day without changes makes no commit.
$previous = is_file($outFile) ? json_decode((string) file_get_contents($outFile), true) : null;
$updated = gmdate('Y-m-d');

if (is_array($previous) && ($previous['robots']['bots'] ?? null) === $bots && isset($previous['robots']['updated'])) {
    $updated = (string) $previous['robots']['updated'];
}

// Only the "robots" section is ours; the release sections are written by
// build-releases.php and stay as they are.
$manifest = is_array($previous) ? $previous : [];
$manifest['robots'] = [
    'updated' => $updated,
    'source' => 'https://github.com/ai-robots-txt/ai.robots.txt',
    'bots' => $bots,
];

file_put_contents($outFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

if ($newBotsFile !== null) {
    $lines = [];

    foreach ($new as $key => $token) {
        $lines[] = '- `' . $token . '` (key `' . $key . '`)';
    }

    file_put_contents($newBotsFile, $lines === [] ? '' : implode("\n", $lines) . "\n");
}

echo 'Bots: ' . count($bots) . ', not yet described: ' . count($new) . "\n";

// ---------------------------------------------------------------- helpers

/** The key the module stores a bot under: the token in lower case, words joined by "-". */
function slug(string $token): string
{
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($token)), '-');
}

/**
 * Names the module must not match on.
 *
 * - "Spider", "Code" and "OpenAI" are placeholders in ai.robots.txt. As a fragment
 *   of a user agent they would catch traffic nobody meant: "Spider" is in the header
 *   of hundreds of ordinary crawlers, "OpenAI" is in the header of GPTBot.
 * - Names shorter than five characters match too much for the same reason.
 * - A name with a version number is skipped when the list has it without one.
 *
 * @param string[] $all
 */
function skip(string $token, array $all): bool
{
    if (in_array($token, ['Spider', 'Code', 'OpenAI'], true) || mb_strlen($token) < 5) {
        return true;
    }

    if (preg_match('~^(.+?)[ /]v?\d+(\.\d+)*$~', $token, $m) === 1) {
        return in_array($m[1], $all, true);
    }

    return false;
}

/**
 * Facts from an ai.robots.txt entry, in the module's fields.
 *
 * @param array<string,mixed> $entry
 * @return array{vendor:string,description:string,docs_url:string,respects:string}
 */
function facts(array $entry): array
{
    $operator = trim((string) ($entry['operator'] ?? ''));
    $respect = trim((string) ($entry['respect'] ?? ''));
    $description = trim((string) ($entry['description'] ?? ''));

    $vendor = $operator;
    $docs = '';

    if (preg_match('~^\[([^\]]+)\]\(([^)]+)\)~', $operator, $m) === 1) {
        $vendor = $m[1];
        $docs = $m[2];
    }

    if (str_starts_with($vendor, 'Unclear')) {
        $vendor = '';
    }

    // ai.robots.txt often writes the operator as a sentence ("Amazon, used for …").
    // The module shows a name, so only the part before the first comma stays, and
    // anything still longer than a name is left for whoever describes the bot.
    $vendor = trim(explode(',', $vendor)[0]);

    if (mb_strlen($vendor) > 40) {
        $vendor = '';
    }

    // The trailing "More info can be found at …" points at a third-party directory,
    // not at the bot's operator, so it is not part of the description.
    $description = trim((string) preg_replace('~\s*More info can be found at \S+\s*$~', '', $description));

    $respects = '';

    if (preg_match('~^\[?(Yes|No)\b~', $respect, $m) === 1) {
        $respects = strtolower($m[1]);
    } elseif ($respect !== '') {
        $respects = 'unclear';
    }

    return ['vendor' => $vendor, 'description' => $description, 'docs_url' => $docs, 'respects' => $respects];
}
