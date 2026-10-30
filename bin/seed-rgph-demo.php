<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';
(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

$databaseUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? '';
$parts = parse_url($databaseUrl);
if (!$parts || !in_array($parts['scheme'] ?? '', ['postgres', 'postgresql'], true)) {
    throw new RuntimeException('DATABASE_URL doit pointer vers PostgreSQL.');
}
$pdo = new PDO(
    sprintf('pgsql:host=%s;port=%d;dbname=%s', $parts['host'] ?? '127.0.0.1', $parts['port'] ?? 5433, ltrim($parts['path'] ?? '', '/')),
    rawurldecode($parts['user'] ?? ''),
    rawurldecode($parts['pass'] ?? ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function normalizeGeoName(string $value): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    return strtolower((string) preg_replace('/[^a-z0-9]/i', '', $ascii));
}
function geoSeed(string $department, ?string $commune = null): int
{
    $name = normalizeGeoName($department);
    $sum = array_sum(array_map('ord', str_split($name)));
    if ($commune !== null) {
        $sum += strlen(normalizeGeoName($commune)) * 7;
    }
    return ($sum % 9) - 4;
}
function adjustedRate(float $rate, int $seed): float
{
    return max(0, min(100, $rate + $seed));
}

$baseDir = dirname(__DIR__);
$communeSource = file_get_contents($baseDir . '/public/data/communes-niger.js');
$start = strpos($communeSource, '{');
$end = strrpos($communeSource, ';');
$communesByRegion = json_decode(substr($communeSource, $start, $end - $start), true, 512, JSON_THROW_ON_ERROR);
$template = file_get_contents($baseDir . '/templates/home/index.html.twig');
if (!preg_match('/var regionsData = \[(.*?)\];/s', $template, $regionBlock)
    || !preg_match_all('/\{ name: "([^"]+)", lat: ([0-9.]+), lng: ([0-9.]+), pop: "[^"]*", menages: "[^"]*", deptCount: (\d+) \}/u', $regionBlock[1], $regionMatches, PREG_SET_ORDER)) {
    throw new RuntimeException('Impossible de lire les coordonnées des régions depuis la carte.');
}
if (!preg_match('/var departementsData = \[(.*?)\];/s', $template, $departmentBlock)
    || !preg_match_all('/\{ region: "([^"]+)", name: "([^"]+)", lat: ([0-9.]+), lng: ([0-9.]+) \}/u', $departmentBlock[1], $departmentMatches, PREG_SET_ORDER)) {
    throw new RuntimeException('Impossible de lire les coordonnées des départements depuis la carte.');
}

$schemaSql = file_get_contents($baseDir . '/database/rgph-demo-data.sql') . "\n" . file_get_contents($baseDir . '/database/rgph-geography-schema.sql');
$pdo->beginTransaction();
try {
    $pdo->exec($schemaSql);
    $pdo->exec('ALTER TABLE region_stat ADD COLUMN IF NOT EXISTS lat NUMERIC(9,6)');
    $pdo->exec('ALTER TABLE region_stat ADD COLUMN IF NOT EXISTS lng NUMERIC(9,6)');
    $stats = $pdo->query('SELECT region_name, population, households, health_rate, poverty_rate, economic_rate, literacy_rate, language_share, housing_rate, establishments FROM rgph_region_stats')->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    $updateRegion = $pdo->prepare('UPDATE region_stat SET population = :population, menages = :households, lat = :lat, lng = :lng WHERE nom = :name');
    $updateDepartment = $pdo->prepare('UPDATE departement SET lat = :lat, lng = :lng WHERE region_nom = :region AND nom = :name');
    $existingDepartments = $pdo->query('SELECT region_nom, nom FROM departement')->fetchAll(PDO::FETCH_ASSOC);
    $departmentNames = [];
    foreach ($existingDepartments as $row) {
        $departmentNames[normalizeGeoName($row['region_nom']) . ':' . normalizeGeoName($row['nom'])] = [$row['region_nom'], $row['nom']];
    }
    foreach ($regionMatches as $match) {
        $name = $match[1];
        $updateRegion->execute(['population' => $stats[$name]['population'], 'households' => $stats[$name]['households'], 'lat' => $match[2], 'lng' => $match[3], 'name' => $name]);
        if ($updateRegion->rowCount() !== 1) {
            throw new RuntimeException("La région $name doit exister exactement une fois dans region_stat.");
        }
    }
    foreach ($departmentMatches as $match) {
        $key = normalizeGeoName($match[1]) . ':' . normalizeGeoName($match[2]);
        if (!isset($departmentNames[$key])) {
            throw new RuntimeException("Département absent de la table departement : {$match[1]} / {$match[2]}.");
        }
        [$regionName, $departmentName] = $departmentNames[$key];
        $updateDepartment->execute(['lat' => $match[3], 'lng' => $match[4], 'region' => $regionName, 'name' => $departmentName]);
    }

    $pdo->exec('TRUNCATE TABLE commune, rgph_area_indicators RESTART IDENTITY');
    $insertCommune = $pdo->prepare('INSERT INTO commune (region_nom, departement_nom, nom) VALUES (:region, :department, :name)');
    $insertArea = $pdo->prepare('INSERT INTO rgph_area_indicators (area_level, region_name, department_name, commune_name, population, households, health_rate, poverty_rate, economic_rate, literacy_rate, language_share, housing_rate, establishments) VALUES (:level, :region, :department, :commune, :population, :households, :health, :poverty, :economic, :literacy, :language, :housing, :establishments)');
    $communeCount = 0;
    $areaCount = 0;
    foreach ($communesByRegion as $region => $departments) {
        $regionStats = $stats[$region] ?? null;
        if (!$regionStats) {
            throw new RuntimeException("Statistiques régionales introuvables : $region.");
        }
        $totalCommunes = array_sum(array_map('count', $departments));
        $ordinal = 0;
        foreach ($departments as $department => $communes) {
            $departmentStats = array_fill_keys(['population', 'households', 'establishments'], 0);
            $seed = geoSeed($department);
            foreach ($communes as $commune) {
                $insertCommune->execute(['region' => $region, 'department' => $department, 'name' => $commune]);
                $communeCount++;
                $ordinal++;
                $person = intdiv((int) $regionStats['population'], $totalCommunes) + ($ordinal <= ((int) $regionStats['population'] % $totalCommunes) ? 1 : 0);
                $household = intdiv((int) $regionStats['households'], $totalCommunes) + ($ordinal <= ((int) $regionStats['households'] % $totalCommunes) ? 1 : 0);
                $establishment = intdiv((int) $regionStats['establishments'], $totalCommunes) + ($ordinal <= ((int) $regionStats['establishments'] % $totalCommunes) ? 1 : 0);
                $communeSeed = geoSeed($department, $commune);
                $insertArea->execute([
                    'level' => 'commune', 'region' => $region, 'department' => $department, 'commune' => $commune,
                    'population' => $person, 'households' => $household,
                    'health' => adjustedRate((float) $regionStats['health_rate'], $communeSeed),
                    'poverty' => adjustedRate((float) $regionStats['poverty_rate'], $communeSeed),
                    'economic' => adjustedRate((float) $regionStats['economic_rate'], $communeSeed),
                    'literacy' => adjustedRate((float) $regionStats['literacy_rate'], $communeSeed),
                    'language' => adjustedRate((float) $regionStats['language_share'], $communeSeed),
                    'housing' => adjustedRate((float) $regionStats['housing_rate'], $communeSeed),
                    'establishments' => $establishment,
                ]);
                $areaCount++;
                $departmentStats['population'] += $person;
                $departmentStats['households'] += $household;
                $departmentStats['establishments'] += $establishment;
            }
            $insertArea->execute([
                'level' => 'department', 'region' => $region, 'department' => $department, 'commune' => '',
                'population' => $departmentStats['population'], 'households' => $departmentStats['households'],
                'health' => adjustedRate((float) $regionStats['health_rate'], $seed),
                'poverty' => adjustedRate((float) $regionStats['poverty_rate'], $seed),
                'economic' => adjustedRate((float) $regionStats['economic_rate'], $seed),
                'literacy' => adjustedRate((float) $regionStats['literacy_rate'], $seed),
                'language' => adjustedRate((float) $regionStats['language_share'], $seed),
                'housing' => adjustedRate((float) $regionStats['housing_rate'], $seed),
                'establishments' => $departmentStats['establishments'],
            ]);
            $areaCount++;
        }
    }
    $pdo->commit();
    fwrite(STDOUT, "Base PostgreSQL mise à jour : 8 régions, 64 départements, $communeCount communes, $areaCount lignes d’indicateurs territoriaux.\n");
    fwrite(STDOUT, "Total des régions : " . number_format((int) array_sum(array_column($stats, 'population')), 0, ',', ' ') . " habitants et " . number_format((int) array_sum(array_column($stats, 'households')), 0, ',', ' ') . " ménages.\n");
    fwrite(STDOUT, "Les indicateurs de santé, pauvreté, activité, alphabétisation, langue et logement sont des estimations fictives.\n");
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}
