<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class InstallController extends Controller
{
    /**
     * Check installation status.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'installed' => File::exists(storage_path('installed')),
        ]);
    }

    /**
     * Run preflight server requirement checks.
     */
    public function preflight(): JsonResponse
    {
        $checks = [
            'php_version' => [
                'name' => 'PHP Version (>= 8.2)',
                'current' => PHP_VERSION,
                'pass' => version_compare(PHP_VERSION, '8.2.0', '>='),
            ],
            'pdo' => [
                'name' => 'PDO Extension',
                'pass' => extension_loaded('pdo'),
            ],
            'pdo_mysql' => [
                'name' => 'MySQL PDO Driver',
                'pass' => extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'),
            ],
            'openssl' => [
                'name' => 'OpenSSL Extension',
                'pass' => extension_loaded('openssl'),
            ],
            'mbstring' => [
                'name' => 'Mbstring Extension',
                'pass' => extension_loaded('mbstring'),
            ],
            'curl' => [
                'name' => 'cURL Extension',
                'pass' => extension_loaded('curl'),
            ],
            'gd' => [
                'name' => 'GD Image Extension',
                'pass' => extension_loaded('gd'),
            ],
            'zip' => [
                'name' => 'Zip Extension',
                'pass' => extension_loaded('zip'),
            ],
            'fileinfo' => [
                'name' => 'Fileinfo Extension',
                'pass' => extension_loaded('fileinfo'),
            ],
            'storage_writable' => [
                'name' => 'storage/ Directory Writable',
                'pass' => File::isWritable(storage_path()),
            ],
            'cache_writable' => [
                'name' => 'bootstrap/cache/ Directory Writable',
                'pass' => File::isWritable(base_path('bootstrap/cache')),
            ],
        ];

        $ready = !in_array(false, array_column($checks, 'pass'), true);

        return response()->json([
            'ready' => $ready,
            'checks' => $checks,
        ]);
    }

    /**
     * Live test database connectivity via PDO.
     */
    public function testDb(Request $request): JsonResponse
    {
        $request->validate([
            'db_connection' => 'nullable|string|in:mysql,sqlite',
            'db_host' => 'required_if:db_connection,mysql|string',
            'db_port' => 'required_if:db_connection,mysql',
            'db_database' => 'required|string',
            'db_username' => 'nullable|string',
            'db_password' => 'nullable|string',
        ]);

        $connection = $request->input('db_connection', 'mysql');

        if ($connection === 'sqlite') {
            $path = $request->input('db_database');
            if (!File::exists($path) && !@touch($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to create or write SQLite file at: ' . $path,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'SQLite database file is accessible and writable.',
            ]);
        }

        $host = $request->input('db_host', '127.0.0.1');
        $port = (int) $request->input('db_port', 3306);
        $database = $request->input('db_database');
        $username = $request->input('db_username', 'root');
        $password = (string) $request->input('db_password', '');

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 4,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Successfully connected to MySQL database: ' . $database,
            ]);
        } catch (\PDOException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Complete the installation process.
     */
    public function execute(Request $request): JsonResponse
    {
        if (File::exists(storage_path('installed'))) {
            return response()->json(['message' => 'Application is already installed.'], 403);
        }

        $data = $request->validate([
            'db_connection' => 'required|string|in:mysql,sqlite',
            'db_host' => 'required_if:db_connection,mysql|string',
            'db_port' => 'required_if:db_connection,mysql',
            'db_database' => 'required|string',
            'db_username' => 'nullable|string',
            'db_password' => 'nullable|string',
            'app_name' => 'required|string|max:100',
            'app_url' => 'required|url',
            'admin_name' => 'required|string|max:100',
            'admin_email' => 'required|email|max:150',
            'admin_password' => 'required|string|min:8',
            'store_name' => 'required|string|max:100',
        ]);

        // 1. Prepare and write .env
        $this->writeEnvFile($data);

        // 2. Dynamically set DB connection in current process
        $this->configureRuntimeDb($data);

        // 3. Run database migrations
        try {
            ini_set('max_execution_time', '300');
            ini_set('memory_limit', '512M');

            Artisan::call('migrate', ['--force' => true]);
        } catch (\Exception $e) {
            Log::error('Migration execution failed during install: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Database migration failed: ' . $e->getMessage(),
            ], 500);
        }

        // 4. Create superadmin user and default workspace
        try {
            $user = User::updateOrCreate(
                ['email' => strtolower(trim($data['admin_email']))],
                [
                    'name' => trim($data['admin_name']),
                    'password' => $data['admin_password'],
                ]
            );

            $workspace = Workspace::firstOrCreate(
                ['owner_user_id' => $user->id],
                ['name' => trim($data['store_name'])]
            );

            WorkspaceMember::firstOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'user_id' => $user->id,
                ],
                ['role' => WorkspaceMember::ROLE_OWNER]
            );
        } catch (\Exception $e) {
            Log::error('Admin/Workspace creation failed during install: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Admin account creation failed: ' . $e->getMessage(),
            ], 500);
        }

        // 5. Lock installer permanently
        File::put(storage_path('installed'), json_encode([
            'installed_at' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
        ], JSON_PRETTY_PRINT));

        return response()->json([
            'success' => true,
            'message' => 'MyStore has been installed successfully.',
            'redirect' => '/login',
        ]);
    }

    /**
     * Write .env file with supplied parameters.
     */
    private function writeEnvFile(array $data): void
    {
        $envPath = base_path('.env');
        $examplePath = base_path('.env.example');

        $baseContent = File::exists($examplePath) ? File::get($examplePath) : '';
        $appKey = 'base64:' . base64_encode(random_bytes(32));

        $envValues = [
            'APP_NAME' => '"' . addslashes($data['app_name']) . '"',
            'APP_ENV' => 'production',
            'APP_KEY' => $appKey,
            'APP_DEBUG' => 'false',
            'APP_URL' => rtrim($data['app_url'], '/'),
            'FRONTEND_URL' => rtrim($data['app_url'], '/'),
            'DB_CONNECTION' => $data['db_connection'],
            'DB_HOST' => $data['db_host'] ?? '127.0.0.1',
            'DB_PORT' => $data['db_port'] ?? '3306',
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'] ?? '',
            'DB_PASSWORD' => '"' . addslashes($data['db_password'] ?? '') . '"',
            'SESSION_DRIVER' => 'database',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
        ];

        // Replace existing keys in example content or append
        $lines = explode("\n", $baseContent);
        $writtenKeys = [];

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            if (empty($trimmed) || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key] = explode('=', $line, 2);
                $key = trim($key);
                if (array_key_exists($key, $envValues)) {
                    $lines[$i] = $key . '=' . $envValues[$key];
                    $writtenKeys[$key] = true;
                }
            }
        }

        // Append any keys not found in example file
        foreach ($envValues as $key => $val) {
            if (!isset($writtenKeys[$key])) {
                $lines[] = $key . '=' . $val;
            }
        }

        File::put($envPath, implode("\n", $lines));
    }

    /**
     * Configure database connection in the active request runtime.
     */
    private function configureRuntimeDb(array $data): void
    {
        $connection = $data['db_connection'];
        config(['database.default' => $connection]);

        if ($connection === 'mysql') {
            config([
                'database.connections.mysql.host' => $data['db_host'] ?? '127.0.0.1',
                'database.connections.mysql.port' => (int) ($data['db_port'] ?? 3306),
                'database.connections.mysql.database' => $data['db_database'],
                'database.connections.mysql.username' => $data['db_username'] ?? 'root',
                'database.connections.mysql.password' => $data['db_password'] ?? '',
            ]);
            DB::purge('mysql');
            DB::reconnect('mysql');
        } elseif ($connection === 'sqlite') {
            config([
                'database.connections.sqlite.database' => $data['db_database'],
            ]);
            DB::purge('sqlite');
            DB::reconnect('sqlite');
        }
    }
}
