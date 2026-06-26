<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class FileController extends Controller
{
    /**
     * Upload files + save params (замена legacy /prepare).
     */
    public function upload(Request $request, string $name): JsonResponse
    {
        $servlet = $this->findServlet($name);
        if (! $servlet) {
            abort(404, "Сервлет '{$name}' не найден");
        }

        $userId = $request->user()->getAuthIdentifier();
        $servletDir = $this->servletDir($userId, $name);

        // Save uploaded files to /in/
        if ($request->hasFile('files')) {
            $inputDir = $servletDir.'/in';
            if (! is_dir($inputDir)) {
                mkdir($inputDir, 0755, true);
            }

            foreach ($request->file('files') as $file) {
                $file->move($inputDir, $file->getClientOriginalName());
            }
        }

        // Save params.json
        $params = $request->except(['files', '_token']);
        if (! empty($params)) {
            if (! is_dir($servletDir)) {
                mkdir($servletDir, 0755, true);
            }
            file_put_contents(
                $servletDir.'/params.json',
                json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            // Update local_params.json (catalog checkboxes)
            $this->updateLocalParams($userId, $name, $params);
        }

        return response()->json([
            'success' => true,
            'inputFiles' => $this->getInputFiles($userId, $name),
        ]);
    }

    /**
     * List input files.
     */
    public function inputFiles(Request $request, string $name): JsonResponse
    {
        $userId = $request->user()->getAuthIdentifier();
        $subdir = $request->query('subdir', '');

        return response()->json(
            $this->getInputFiles($userId, $name, $subdir)
        );
    }

    /**
     * List result files.
     */
    public function resultFiles(Request $request, string $name): JsonResponse
    {
        $userId = $request->user()->getAuthIdentifier();

        return response()->json(
            $this->getResultFiles($userId, $name)
        );
    }

    /**
     * Download a single result file.
     */
    public function download(Request $request, string $name, string $file): BinaryFileResponse
    {
        $userId = $request->user()->getAuthIdentifier();
        $filePath = $this->servletDir($userId, $name).'/out/'.$file;

        if (! file_exists($filePath) || ! is_file($filePath)) {
            abort(404, 'Файл не найден');
        }

        return response()->download($filePath);
    }

    /**
     * Download all result files as ZIP.
     */
    public function downloadZip(Request $request, string $name): BinaryFileResponse|JsonResponse
    {
        $servlet = $this->findServlet($name);
        $userId = $request->user()->getAuthIdentifier();
        $outputDir = $this->servletDir($userId, $name).'/out';

        if (! is_dir($outputDir)) {
            abort(404, 'Нет выходных файлов');
        }

        $title = $servlet['title'] ?? $name;
        $zipName = "Результат {$title} ".date('Y-m-d H_i').'.zip';
        $zipPath = $outputDir.'/'.$zipName;

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Ошибка создания архива');
        }

        $count = $this->addDirToZip($outputDir, $zip, '');
        $zip->close();

        if ($count === 0) {
            @unlink($zipPath);
            abort(404, 'Файлов результата не найдено');
        }

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }

    /**
     * Clear input files.
     */
    public function clearIn(Request $request, string $name): JsonResponse
    {
        $userId = $request->user()->getAuthIdentifier();
        $inputDir = $this->servletDir($userId, $name).'/in';

        $this->clearDirectory($inputDir);

        return response()->json(['success' => true]);
    }

    /**
     * Clear output files.
     */
    public function clearOut(Request $request, string $name): JsonResponse
    {
        $userId = $request->user()->getAuthIdentifier();
        $outputDir = $this->servletDir($userId, $name).'/out';

        $this->clearDirectory($outputDir);

        return response()->json(['success' => true]);
    }

    /**
     * Save params only (without file upload).
     */
    public function saveParams(Request $request, string $name): JsonResponse
    {
        $servlet = $this->findServlet($name);
        if (! $servlet) {
            abort(404, "Сервлет '{$name}' не найден");
        }

        $userId = $request->user()->getAuthIdentifier();
        $servletDir = $this->servletDir($userId, $name);

        if (! is_dir($servletDir)) {
            mkdir($servletDir, 0755, true);
        }

        $params = $request->except(['_token']);
        file_put_contents(
            $servletDir.'/params.json',
            json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return response()->json(['success' => true]);
    }

    /**
     * Public download (no auth) — only image files from /in/ subdirectories.
     */
    public function publicDownload(int $userId, string $servlet, string $file): BinaryFileResponse
    {
        // Only allow image extensions
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            abort(403, 'Доступ запрещён');
        }

        $filePath = $this->basePath()."/{$userId}/{$servlet}/in/{$file}";

        if (! file_exists($filePath) || ! is_file($filePath)) {
            abort(404, 'Файл не найден');
        }

        return response()->file($filePath);
    }

    // ─── Helpers ────────────────────────────────────────────

    private function basePath(): string
    {
        return config('workbench.files_path');
    }

    private function servletDir(int $userId, string $servletName): string
    {
        return $this->basePath()."/{$userId}/{$servletName}";
    }

    public function getInputFiles(int $userId, string $servletName, string $subdir = ''): array
    {
        $inputDir = $this->servletDir($userId, $servletName).'/in';
        if ($subdir !== '') {
            // Sanitize: prevent directory traversal
            $subdir = str_replace(['..', "\0"], '', $subdir);
            $inputDir .= '/'.$subdir;
        }

        $files = [];
        $totalSize = 0;

        if (is_dir($inputDir)) {
            foreach (scandir($inputDir) as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $fullPath = $inputDir.'/'.$file;

                if (is_dir($fullPath)) {
                    // Count files inside subdirectory
                    $fileCount = count(array_filter(scandir($fullPath), fn ($f) => $f !== '.' && $f !== '..' && is_file($fullPath.'/'.$f)));
                    $files[] = [
                        'filename' => $file,
                        'size' => 0,
                        'sizeFormatted' => $fileCount.' файл(ов)',
                        'icon' => 'folder',
                        'isDir' => true,
                        'fileCount' => $fileCount,
                    ];
                } else {
                    $size = filesize($fullPath);
                    $totalSize += $size;
                    $files[] = [
                        'filename' => $file,
                        'size' => $size,
                        'sizeFormatted' => $this->formatSize($size),
                        'icon' => $this->getFileIcon($fullPath),
                        'isDir' => false,
                    ];
                }
            }
        }

        return [
            'files' => $files,
            'totalCount' => count($files),
            'totalSize' => $this->formatSize($totalSize),
            'subdir' => $subdir,
        ];
    }

    public function getResultFiles(int $userId, string $servletName): array
    {
        $outputDir = $this->servletDir($userId, $servletName).'/out';
        $files = [];

        if (is_dir($outputDir)) {
            foreach (scandir($outputDir) as $file) {
                if ($file === '.' || $file === '..' || str_ends_with($file, '.zip')) {
                    continue;
                }
                $fullPath = $outputDir.'/'.$file;
                $size = is_file($fullPath) ? filesize($fullPath) : 0;
                $files[] = [
                    'filename' => $file,
                    'size' => $size,
                    'sizeFormatted' => $this->formatSize($size),
                    'icon' => $this->getFileIcon($fullPath),
                    'isDir' => is_dir($fullPath),
                ];
            }
        }

        return ['files' => $files];
    }

    public function getSavedParams(int $userId, string $servletName): ?array
    {
        $paramsPath = $this->servletDir($userId, $servletName).'/params.json';

        if (! file_exists($paramsPath)) {
            return null;
        }

        return json_decode(file_get_contents($paramsPath), true);
    }

    /**
     * Load local_params.json (dynamic catalogs generated by renewcatalogs action).
     */
    public function getLocalParams(int $userId, string $servletName): ?array
    {
        $path = $this->servletDir($userId, $servletName).'/local_params.json';

        if (! file_exists($path)) {
            return null;
        }

        return json_decode(file_get_contents($path), true);
    }

    /**
     * Update local_params.json values from form data (catalog checkbox on/off).
     */
    public function updateLocalParams(int $userId, string $servletName, array $formData): void
    {
        $path = $this->servletDir($userId, $servletName).'/local_params.json';

        if (! file_exists($path)) {
            return;
        }

        $localParams = json_decode(file_get_contents($path), true);
        if (! is_array($localParams)) {
            return;
        }

        foreach ($localParams as &$lp) {
            if (! isset($lp['name'])) {
                continue;
            }
            // PHP converts dots in POST param names to underscores
            $phpKey = str_replace('.', '_', $lp['name']);
            if (array_key_exists($phpKey, $formData)) {
                $lp['value'] = $formData[$phpKey] === 'on' ? 'on' : '';
            }
        }
        unset($lp);

        file_put_contents($path, json_encode($localParams, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1).' Гб';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' Мб';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024).' Кб';
        }

        return $bytes.' б';
    }

    private function getFileIcon(string $file): string
    {
        if (is_dir($file)) {
            return 'folder';
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (preg_match('/^(jpe?g|gif|png|w?bmp|webp)$/', $ext)) {
            return 'image';
        }
        if ($ext === 'zip') {
            return 'archive';
        }
        if (in_array($ext, ['csv', 'txt', 'log'])) {
            return 'file-text';
        }
        if (in_array($ext, ['xls', 'xlsx'])) {
            return 'file-spreadsheet';
        }

        return 'file';
    }

    private function addDirToZip(string $dir, ZipArchive $zip, string $zipPath): int
    {
        $count = 0;
        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..' || str_ends_with($file, '.zip')) {
                continue;
            }
            $fullPath = $dir.'/'.$file;
            if (is_dir($fullPath)) {
                $zip->addEmptyDir($zipPath.$file);
                $count += $this->addDirToZip($fullPath, $zip, $zipPath.$file.'/');
            } else {
                $zip->addFile($fullPath, $zipPath.$file);
                $count++;
            }
        }

        return $count;
    }

    private function clearDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->clearDirectory($path);
                rmdir($path);
            } else {
                unlink($path);
            }
        }
    }

    private function findServlet(string $name): ?array
    {
        $path = resource_path('data/servlets.json');
        if (! file_exists($path)) {
            return null;
        }

        $servlets = json_decode(file_get_contents($path), true) ?: [];
        foreach ($servlets as $category => $items) {
            if (isset($items[$name])) {
                return array_merge($items[$name], ['key' => $name, 'category' => $category]);
            }
        }

        return null;
    }
}
