<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

/* ---------- env ---------- */
(function (): void {
    $file = __DIR__ . '/../.env';
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
})();

function env(string $key, ?string $default = null): ?string
{
    $v = $_ENV[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : (string)$v;
}

/* ---------- helpers ---------- */
function respond(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        respond(['success' => false, 'message' => 'Method not allowed'], 405);
    }
}

function clean(?string $v): string
{
    return trim(strip_tags((string)$v));
}

/* ---------- MySQL (prepared statements via PDO) ---------- */
function mysql(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env('MYSQL_HOST', '127.0.0.1'), env('MYSQL_PORT', '3306'), env('MYSQL_DB', 'authdb'));
        $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (env('MYSQL_SSL') === '1') {
        $opts[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
        $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
    $pdo = new PDO($dsn, env('MYSQL_USER', 'root'), env('MYSQL_PASS', ''), $opts);   
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(30) NOT NULL UNIQUE,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB');
    return $pdo;
}

/* ---------- MongoDB (profile details) ---------- */
function profiles(): MongoDB\Collection
{
    static $col = null;
    if ($col) return $col;
    $client = new MongoDB\Client(env('MONGO_URI', 'mongodb://127.0.0.1:27017'));
    $col = $client->selectCollection(env('MONGO_DB', 'authdb'), 'profiles');
    $col->createIndex(['user_id' => 1], ['unique' => true]);
    return $col;
}

/* ---------- Redis (sessions) ---------- */
function redis(): Predis\Client
{
    static $r = null;
    if ($r) return $r;
    $params = [
        'scheme' => env('REDIS_SCHEME', 'tcp'),
        'host'   => env('REDIS_HOST', '127.0.0.1'),
        'port'   => (int)env('REDIS_PORT', '6379'),
    ];
    if (env('REDIS_PASS')) $params['password'] = env('REDIS_PASS');
    return $r = new Predis\Client($params);
}

function session_ttl(): int
{
    return (int)env('SESSION_TTL', '604800');
}

function create_session(int $userId): string
{
    $token = bin2hex(random_bytes(32));
    redis()->setex("session:$token", session_ttl(), (string)$userId);
    return $token;
}

function request_token(): string
{
    $t = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    return preg_match('/^[a-f0-9]{64}$/', $t) ? $t : '';
}

/** Returns user id or sends 401. Slides the expiry so active users stay signed in. */
function require_auth(): int
{
    $token = request_token();
    if ($token === '') respond(['success' => false, 'message' => 'Not authenticated'], 401);
    $uid = redis()->get("session:$token");
    if (!$uid) respond(['success' => false, 'message' => 'Session expired'], 401);
    redis()->expire("session:$token", session_ttl());
    return (int)$uid;
}
