<?php
/** Local-only bootstrap. Creates dedicated IDPS schemas; never resets existing data. */
declare(strict_types=1);

$root = dirname(__DIR__);
$secretFile = $root.'/.local/mysql.json';
if (!is_dir(dirname($secretFile))) {
    mkdir(dirname($secretFile), 0700, true);
}
$credentials = is_file($secretFile) ? json_decode(file_get_contents($secretFile), true, flags: JSON_THROW_ON_ERROR) : [
    'idps_app' => bin2hex(random_bytes(24)),
    'idps_migrator' => bin2hex(random_bytes(24)),
    'idps_test' => bin2hex(random_bytes(24)),
];
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', getenv('IDPS_SETUP_USER') ?: 'root', getenv('IDPS_SETUP_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (['idps_dev', 'idps_test'] as $database) {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}
foreach ($credentials as $username => $password) {
    $pdo->exec("CREATE USER IF NOT EXISTS '$username'@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($password));
}
$pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON idps_dev.* TO 'idps_app'@'127.0.0.1'");
$pdo->exec("GRANT ALL PRIVILEGES ON idps_dev.* TO 'idps_migrator'@'127.0.0.1'");
$pdo->exec("GRANT ALL PRIVILEGES ON idps_test.* TO 'idps_test'@'127.0.0.1'");
file_put_contents($secretFile, json_encode($credentials, JSON_THROW_ON_ERROR));
foreach (['.env' => ['idps_dev', 'idps_app'], '.env.testing' => ['idps_test', 'idps_test']] as $file => [$database, $username]) {
    $path = $root.'/apps/backend/'.$file;
    $content = file_get_contents(is_file($path) ? $path : $root.'/apps/backend/.env.example');
    $values = ['DB_CONNECTION'=>'mysql','DB_HOST'=>'127.0.0.1','DB_PORT'=>'3306','DB_DATABASE'=>$database,'DB_USERNAME'=>$username,'DB_PASSWORD'=>$credentials[$username], 'SESSION_DRIVER'=>'file', 'CACHE_STORE'=>'file'];
    if ($file === '.env.testing') {
        $values += ['APP_ENV'=>'testing', 'SESSION_DRIVER'=>'array', 'CACHE_STORE'=>'array', 'QUEUE_CONNECTION'=>'sync'];
        $values['SESSION_DRIVER'] = 'array'; $values['CACHE_STORE'] = 'array';
    } else {
        $values += ['DB_MIGRATION_USERNAME'=>'idps_migrator','DB_MIGRATION_PASSWORD'=>$credentials['idps_migrator']];
    }
    foreach ($values as $key => $value) {
        $line = "$key=$value";
        $content = preg_match('/^'.preg_quote($key, '/').'=/m', $content) ? preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $content) : $content."\n$line\n";
    }
    file_put_contents($path, $content);
}
echo "Dedicated IDPS databases and scoped local credentials configured; existing data preserved.\n";
