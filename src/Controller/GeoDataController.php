<?php

namespace App\Controller;

use PDO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class GeoDataController extends AbstractController
{
    private const DEMONSTRATION_DATA = [
        'Agadez' => ['population' => 3700000, 'households' => 480000, 'healthRate' => 58.0, 'povertyRate' => 41.0, 'economicRate' => 49.0, 'literacyRate' => 43.0, 'languageShare' => 91.0, 'housingRate' => 47.0, 'establishments' => 74000],
        'Diffa' => ['population' => 3400000, 'households' => 550000, 'healthRate' => 43.0, 'povertyRate' => 63.0, 'economicRate' => 52.0, 'literacyRate' => 28.0, 'languageShare' => 88.0, 'housingRate' => 38.0, 'establishments' => 68000],
        'Dosso' => ['population' => 3400000, 'households' => 620000, 'healthRate' => 51.0, 'povertyRate' => 57.0, 'economicRate' => 58.0, 'literacyRate' => 39.0, 'languageShare' => 93.0, 'housingRate' => 42.0, 'establishments' => 72000],
        'Maradi' => ['population' => 4300000, 'households' => 790000, 'healthRate' => 53.0, 'povertyRate' => 59.0, 'economicRate' => 62.0, 'literacyRate' => 35.0, 'languageShare' => 96.0, 'housingRate' => 44.0, 'establishments' => 92000],
        'Tahoua' => ['population' => 4500000, 'households' => 780000, 'healthRate' => 49.0, 'povertyRate' => 58.0, 'economicRate' => 55.0, 'literacyRate' => 40.0, 'languageShare' => 90.0, 'housingRate' => 41.0, 'establishments' => 88000],
        'Tillabéri' => ['population' => 3800000, 'households' => 680000, 'healthRate' => 46.0, 'povertyRate' => 61.0, 'economicRate' => 54.0, 'literacyRate' => 38.0, 'languageShare' => 89.0, 'housingRate' => 39.0, 'establishments' => 76000],
        'Zinder' => ['population' => 5100000, 'households' => 890000, 'healthRate' => 52.0, 'povertyRate' => 62.0, 'economicRate' => 60.0, 'literacyRate' => 34.0, 'languageShare' => 95.0, 'housingRate' => 40.0, 'establishments' => 108000],
        'Niamey' => ['population' => 2900000, 'households' => 710000, 'healthRate' => 72.0, 'povertyRate' => 25.0, 'economicRate' => 67.0, 'literacyRate' => 66.0, 'languageShare' => 83.0, 'housingRate' => 76.0, 'establishments' => 96000],
    ];

    #[Route('/api/rgph/indicateurs', name: 'api_rgph_indicators', methods: ['GET'])]
    public function indicators(): JsonResponse
    {
        $regions = self::DEMONSTRATION_DATA;
        $source = 'demonstration';

        try {
            $databaseUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? null;
            if ($databaseUrl) {
                $parts = parse_url($databaseUrl);
                $host = $parts['host'] ?? '127.0.0.1';
                $database = ltrim($parts['path'] ?? '', '/');
                $pdo = new PDO(
                    sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $parts['port'] ?? 5432, $database),
                    rawurldecode($parts['user'] ?? ''),
                    rawurldecode($parts['pass'] ?? ''),
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
                );
                $rows = $pdo->query('SELECT region_name, population, households, health_rate, poverty_rate, economic_rate, literacy_rate, language_share, housing_rate, establishments FROM rgph_region_stats')->fetchAll(PDO::FETCH_ASSOC);
                if ($rows) {
                    $regions = [];
                    foreach ($rows as $row) {
                        $regions[$row['region_name']] = [
                            'population' => (int) $row['population'],
                            'households' => (int) $row['households'],
                            'healthRate' => (float) $row['health_rate'],
                            'povertyRate' => (float) $row['poverty_rate'],
                            'economicRate' => (float) $row['economic_rate'],
                            'literacyRate' => (float) $row['literacy_rate'],
                            'languageShare' => (float) $row['language_share'],
                            'housingRate' => (float) $row['housing_rate'],
                            'establishments' => (int) $row['establishments'],
                        ];
                    }
                    $source = 'database';
                }
            }
        } catch (Throwable) {
            // The dashboard remains usable with its clearly identified demo values if PostgreSQL is offline.
        }

        return $this->json([
            'source' => $source,
            'demo' => true,
            'regions' => $regions,
            'populationTotal' => array_sum(array_column($regions, 'population')),
            'householdsTotal' => array_sum(array_column($regions, 'households')),
        ]);
    }

    #[Route('/api/rgph/base-de-donnees', name: 'api_rgph_database_overview', methods: ['GET'])]
    public function databaseOverview(): JsonResponse
    {
        $columns = [
            ['name' => 'region_name', 'type' => 'character varying(80)', 'nullable' => false, 'key' => 'PRIMARY KEY'],
            ['name' => 'population', 'type' => 'bigint', 'nullable' => false, 'key' => ''],
            ['name' => 'households', 'type' => 'integer', 'nullable' => false, 'key' => ''],
            ['name' => 'health_rate', 'type' => 'numeric(5,2)', 'nullable' => false, 'key' => ''],
            ['name' => 'poverty_rate', 'type' => 'numeric(5,2)', 'nullable' => false, 'key' => ''],
            ['name' => 'economic_rate', 'type' => 'numeric(5,2)', 'nullable' => false, 'key' => ''],
            ['name' => 'literacy_rate', 'type' => 'numeric(5,2)', 'nullable' => false, 'key' => ''],
            ['name' => 'language_share', 'type' => 'numeric(5,2)', 'nullable' => false, 'key' => ''],
            ['name' => 'housing_rate', 'type' => 'numeric(5,2)', 'nullable' => false, 'key' => ''],
            ['name' => 'establishments', 'type' => 'integer', 'nullable' => false, 'key' => ''],
            ['name' => 'updated_at', 'type' => 'timestamp with time zone', 'nullable' => false, 'key' => ''],
        ];
        $rows = [];
        $tables = [];
        foreach (self::DEMONSTRATION_DATA as $region => $stats) {
            $rows[] = [
                'region_name' => $region,
                'population' => $stats['population'],
                'households' => $stats['households'],
                'health_rate' => $stats['healthRate'],
                'poverty_rate' => $stats['povertyRate'],
                'economic_rate' => $stats['economicRate'],
                'literacy_rate' => $stats['literacyRate'],
                'language_share' => $stats['languageShare'],
                'housing_rate' => $stats['housingRate'],
                'establishments' => $stats['establishments'],
                'updated_at' => null,
            ];
        }
        $source = 'demonstration';
        $connected = false;

        try {
            $databaseUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? null;
            if (!$databaseUrl) {
                throw new \RuntimeException('Database URL unavailable');
            }
            $parts = parse_url($databaseUrl);
            $pdo = new PDO(
                sprintf('pgsql:host=%s;port=%d;dbname=%s', $parts['host'] ?? '127.0.0.1', $parts['port'] ?? 5432, ltrim($parts['path'] ?? '', '/')),
                rawurldecode($parts['user'] ?? ''),
                rawurldecode($parts['pass'] ?? ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
            );
            $connected = true;
            $allowedTables = ['region_stat', 'departement', 'commune', 'rgph_region_stats', 'rgph_area_indicators'];
            $tableQuery = $pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' AND table_name = ?");
            $primaryKeyQuery = $pdo->prepare("SELECT kcu.column_name FROM information_schema.table_constraints tc JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema AND tc.table_name = kcu.table_name WHERE tc.table_schema = 'public' AND tc.table_name = ? AND tc.constraint_type = 'PRIMARY KEY'");
            foreach ($allowedTables as $tableName) {
                $tableQuery->execute([$tableName]);
                if (!$tableQuery->fetchColumn()) {
                    continue;
                }
                $columnQuery = $pdo->prepare("SELECT column_name AS name, data_type AS type, is_nullable FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? ORDER BY ordinal_position");
                $columnQuery->execute([$tableName]);
                $primaryKeyQuery->execute([$tableName]);
                $primaryKeys = $primaryKeyQuery->fetchAll(PDO::FETCH_COLUMN);
                $tableColumns = array_map(static fn (array $column): array => [
                    'name' => $column['name'],
                    'type' => $column['type'],
                    'nullable' => $column['is_nullable'] === 'YES',
                    'key' => in_array($column['name'], $primaryKeys, true) ? 'PRIMARY KEY' : '',
                ], $columnQuery->fetchAll(PDO::FETCH_ASSOC));
                $columnNames = array_column($tableColumns, 'name');
                $quotedTable = '"' . str_replace('"', '""', $tableName) . '"';
                $orderBy = in_array('id', $columnNames, true) ? ' ORDER BY "id"' : '';
                $tableRows = $pdo->query('SELECT * FROM public.' . $quotedTable . $orderBy)->fetchAll(PDO::FETCH_ASSOC);
                $tables[] = ['name' => $tableName, 'columns' => $tableColumns, 'rows' => $tableRows, 'row_count' => count($tableRows)];
            }
            if ($tables) {
                $columns = $tables[0]['columns'];
                $rows = $tables[0]['rows'];
                $source = 'database';
            } else {
                $rows = [];
                $source = 'table_missing';
            }
        } catch (Throwable) {
            // Keep the schema and the clearly labelled sample rows visible while PostgreSQL is offline.
        }

        return $this->json([
            'table' => 'rgph_region_stats',
            'connection_available' => $connected,
            'source' => $source,
            'table_count' => count($tables),
            'tables' => $tables ?: [['name' => 'rgph_region_stats', 'columns' => $columns, 'rows' => $rows, 'row_count' => count($rows)]],
            'columns' => $columns,
            'rows' => $rows,
            'row_count' => count($rows),
        ]);
    }

    #[Route('/api/rgph/territoires', name: 'api_rgph_territorial_indicators', methods: ['GET'])]
    public function territorialIndicators(): JsonResponse
    {
        try {
            $databaseUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? null;
            if (!$databaseUrl) {
                throw new \RuntimeException('Database URL unavailable');
            }
            $parts = parse_url($databaseUrl);
            $pdo = new PDO(
                sprintf('pgsql:host=%s;port=%d;dbname=%s', $parts['host'] ?? '127.0.0.1', $parts['port'] ?? 5433, ltrim($parts['path'] ?? '', '/')),
                rawurldecode($parts['user'] ?? ''),
                rawurldecode($parts['pass'] ?? ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
            );
            $rows = $pdo->query('SELECT area_level, region_name, department_name, commune_name, population, households, health_rate, poverty_rate, economic_rate, literacy_rate, language_share, housing_rate, establishments FROM rgph_area_indicators ORDER BY region_name, department_name, area_level, commune_name')->fetchAll(PDO::FETCH_ASSOC);

            return $this->json(['source' => 'database', 'areas' => $rows]);
        } catch (Throwable) {
            return $this->json(['source' => 'demonstration', 'areas' => []]);
        }
    }
}
