<?php
/** Narrow runtime grants after migrations. Never grants privileges outside idps_dev. */
$pdo=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4',getenv('IDPS_SETUP_USER')?:'root',getenv('IDPS_SETUP_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
try { $pdo->exec("REVOKE SELECT, INSERT, UPDATE, DELETE ON idps_dev.* FROM 'idps_app'@'127.0.0.1'"); } catch (PDOException $error) { if (($error->errorInfo[1] ?? null) !== 1141) { throw $error; } }
$tables=$pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='idps_dev'")->fetchAll(PDO::FETCH_COLUMN);
foreach($tables as $table){if(!preg_match('/^[a-z0-9_]+$/',$table)){throw new RuntimeException('Unexpected table');}$privileges=in_array($table,['audit_logs','approval_actions'])?'SELECT, INSERT':'SELECT, INSERT, UPDATE, DELETE';$pdo->exec("GRANT $privileges ON idps_dev.`$table` TO 'idps_app'@'127.0.0.1'");}
echo "Runtime privileges limited to existing IDPS tables; audit/approval update and delete not granted.\n";
