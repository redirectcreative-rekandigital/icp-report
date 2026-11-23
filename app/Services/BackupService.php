<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Full data backup / restore as one ZIP (no shell access needed on shared hosting):
 *   manifest.json · data/{table}.json · files/{logos|snapshots|documents}/…
 * Sessions, cache, queues and temporary print batches are not included.
 */
class BackupService
{
    public const APP = 'icp-cefr-report'; // internal backup ID (kept so older backups still restore), not the app name

    public const FORMAT = 1;

    /** Parent tables first (insert order); restore deletes in reverse. */
    public const TABLES = [
        'settings', 'level_meta', 'descriptors', 'academic_years', 'terms', 'scoring_configs',
        'classrooms', 'students', 'enrollments', 'scores', 'finalizations', 'final_results',
        'documents', 'activity_logs',
    ];

    public const FILE_DIRS = ['logos', 'snapshots', 'documents'];

    public function __construct(private Settings $settings) {}

    /** Builds the backup in a temp file and returns its path. */
    public function create(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'icpbak').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $counts = [];
        foreach (self::TABLES as $table) {
            $rows = DB::table($table)->get()->map(fn ($r) => (array) $r)->all();
            $counts[$table] = count($rows);
            $zip->addFromString("data/{$table}.json", json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $disk = Storage::disk('local');
        $files = 0;
        foreach (self::FILE_DIRS as $dir) {
            foreach ($disk->allFiles($dir) as $file) {
                $zip->addFile($disk->path($file), "files/{$file}");
                $files++;
            }
        }

        $zip->addFromString('manifest.json', json_encode([
            'app' => self::APP,
            'format' => self::FORMAT,
            'created_at' => now()->toIso8601String(),
            'school' => $this->settings->get('school_name'),
            'tables' => $counts,
            'files' => $files,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        return $path;
    }

    /** @return array{tables: array<string, int>, files: int, created_at: string} */
    public function restore(string $zipPath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new BusinessRuleException('File cadangan tidak dapat dibuka. Pastikan file .zip dari menu Cadangan.');
        }

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (($manifest['app'] ?? null) !== self::APP || ($manifest['format'] ?? null) !== self::FORMAT) {
            $zip->close();
            throw new BusinessRuleException('File ini bukan cadangan ICP Report.');
        }

        $data = [];
        foreach (self::TABLES as $table) {
            $json = $zip->getFromName("data/{$table}.json");
            if ($json === false) {
                $zip->close();
                throw new BusinessRuleException("Cadangan tidak lengkap: data {$table} tidak ada.");
            }
            $data[$table] = json_decode($json, true) ?? [];
        }

        // Only plain relative paths under the known folders may be extracted.
        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^files/((?:'.implode('|', self::FILE_DIRS).')/[^/\\\\]+(?:/[^/\\\\]+)*)$#', $name, $m) && ! str_contains($m[1], '..')) {
                $files[$m[1]] = $i;
            }
        }

        DB::transaction(function () use ($data) {
            Schema::disableForeignKeyConstraints();
            foreach (array_reverse(self::TABLES) as $table) {
                DB::table($table)->delete();
            }
            // classrooms ↔ finalizations reference each other: insert classrooms without the
            // pointer first, then restore it once the finalizations exist (works even where FK
            // checks cannot be switched off inside a transaction, e.g. SQLite).
            $pointers = [];
            foreach (self::TABLES as $table) {
                $columns = array_flip(Schema::getColumnListing($table));
                $rows = array_map(fn ($row) => array_intersect_key($row, $columns), $data[$table]);
                if ($table === 'classrooms') {
                    foreach ($rows as &$row) {
                        if (! empty($row['current_finalization_id'])) {
                            $pointers[$row['id']] = $row['current_finalization_id'];
                        }
                        $row['current_finalization_id'] = null;
                    }
                    unset($row);
                }
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
            foreach ($pointers as $classId => $finalizationId) {
                DB::table('classrooms')->where('id', $classId)->update(['current_finalization_id' => $finalizationId]);
            }
            Schema::enableForeignKeyConstraints();
        });

        $disk = Storage::disk('local');
        foreach (self::FILE_DIRS as $dir) {
            $disk->deleteDirectory($dir);
        }
        foreach ($files as $relative => $index) {
            $disk->put($relative, $zip->getFromIndex($index));
        }
        $zip->close();

        $this->settings->flush();
        Cache::flush(); // recap caches, rate limiters, pending imports

        return ['tables' => array_map('count', $data), 'files' => count($files), 'created_at' => $manifest['created_at']];
    }
}
