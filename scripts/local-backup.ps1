param([ValidateSet('create','test-restore')][string]$Action='create',[string]$BackupPath)
$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.Security
$root=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$backupDir=Join-Path $root '.local/backups'
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
$mysql='C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe'
$dump='C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe'
$credentialsPath=Join-Path $root '.local/mysql.json'
if(-not(Test-Path -LiteralPath $credentialsPath)){throw 'Local MySQL credentials are unavailable.'}
$credentials=Get-Content -LiteralPath $credentialsPath -Raw|ConvertFrom-Json
function Invoke-MySqlCommand([string]$exe,[string[]]$arguments,[string]$password,[string]$inputText=''){
 $info=[System.Diagnostics.ProcessStartInfo]::new()
 $info.FileName=$exe;$info.UseShellExecute=$false;$info.CreateNoWindow=$true
 $info.RedirectStandardOutput=$true;$info.RedirectStandardError=$true;$info.RedirectStandardInput=$true
 $info.StandardOutputEncoding=[System.Text.Encoding]::UTF8;$info.StandardInputEncoding=[System.Text.UTF8Encoding]::new($false)
 foreach($argument in $arguments){[void]$info.ArgumentList.Add($argument)}
 $info.Environment['MYSQL_PWD']=$password
 $process=[System.Diagnostics.Process]::new();$process.StartInfo=$info
 if(-not $process.Start()){throw 'Could not start MySQL client.'}
 $outputTask=$process.StandardOutput.ReadToEndAsync();$errorTask=$process.StandardError.ReadToEndAsync()
 if($inputText){$process.StandardInput.Write($inputText)}
 $process.StandardInput.Close();$process.WaitForExit()
 $output=$outputTask.GetAwaiter().GetResult();$null=$errorTask.GetAwaiter().GetResult()
 if($process.ExitCode -ne 0){throw "MySQL client failed (exit $($process.ExitCode)); check local database credentials and permissions."}
 return $output
}
$common=@('--protocol=TCP','--host=127.0.0.1','--port=3306','--default-character-set=utf8mb4')
if($Action -eq 'create'){
 $sql=Invoke-MySqlCommand $dump ($common+@('--user=idps_migrator','--single-transaction','--skip-lock-tables','--no-tablespaces','--skip-add-drop-table','--skip-triggers','--skip-routines','idps_dev')) $credentials.idps_migrator
 if(-not $sql -or $sql -notmatch 'CREATE TABLE'){throw 'Empty or invalid database dump.'}
 $bytes=[System.Text.Encoding]::UTF8.GetBytes($sql)
 $encrypted=[System.Security.Cryptography.ProtectedData]::Protect($bytes,$null,[System.Security.Cryptography.DataProtectionScope]::CurrentUser)
 $filename='idps-db-'+[DateTime]::UtcNow.ToString('yyyyMMddTHHmmss')+'-'+[Guid]::NewGuid().ToString('N')+'.dpapi'
 $path=Join-Path $backupDir $filename
 [System.IO.File]::WriteAllBytes($path,$encrypted)
 Write-Output "Encrypted database backup saved: $path"
 Write-Output ('SHA256: '+(Get-FileHash -Algorithm SHA256 -LiteralPath $path).Hash)
 exit 0
}
if(-not $BackupPath){throw 'Supply -BackupPath for test-restore.'}
$resolved=(Resolve-Path -LiteralPath $BackupPath).Path
if(-not $resolved.StartsWith($backupDir+[System.IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)){throw 'Backup must be inside .local/backups.'}
$encrypted=[System.IO.File]::ReadAllBytes($resolved)
$bytes=[System.Security.Cryptography.ProtectedData]::Unprotect($encrypted,$null,[System.Security.Cryptography.DataProtectionScope]::CurrentUser)
$sql=[System.Text.Encoding]::UTF8.GetString($bytes)
if($sql -notmatch 'CREATE TABLE' -or $sql -match '(?im)^\s*(USE\s+|CREATE\s+DATABASE\s+|DROP\s+DATABASE\s+)'){throw 'Backup contains forbidden database directives.'}
$schema='idps_restore_'+[Guid]::NewGuid().ToString('N').Substring(0,16)
if($schema -notmatch '^idps_restore_[a-f0-9]{16}$'){throw 'Invalid test schema identifier.'}
$adminUser=if($env:IDPS_SETUP_USER){$env:IDPS_SETUP_USER}else{'root'}
$adminPass=if($env:IDPS_SETUP_PASSWORD){$env:IDPS_SETUP_PASSWORD}else{''}
$adminArgs=$common+@("--user=$adminUser",'--batch','--skip-column-names')
$null=Invoke-MySqlCommand $mysql $adminArgs $adminPass "CREATE DATABASE ``$schema`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
try{
 $null=Invoke-MySqlCommand $mysql ($adminArgs+@($schema)) $adminPass $sql
 $tables=(Invoke-MySqlCommand $mysql ($adminArgs+@($schema)) $adminPass 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE();').Trim()
 if([int]$tables -lt 1){throw 'Restore contains no tables.'}
 $check='SELECT COUNT(*) FROM monthly_observations o LEFT JOIN source_files s ON s.id=o.source_file_id LEFT JOIN import_rows r ON r.id=o.import_row_id WHERE s.id IS NULL OR r.id IS NULL;'
 $orphans=(Invoke-MySqlCommand $mysql ($adminArgs+@($schema)) $adminPass $check).Trim()
 if($orphans -ne '0'){throw 'Restored source lineage contains dangling references.'}
 $observations=(Invoke-MySqlCommand $mysql ($adminArgs+@($schema)) $adminPass 'SELECT COUNT(*) FROM monthly_observations;').Trim()
 Write-Output "Restore validated in isolated schema $schema`: $tables tables, $observations monthly observations, zero broken source references. Schema retained for inspection; no existing schema modified."
}catch{throw "Restore failed in isolated schema $schema; schema retained for investigation. $($_.Exception.Message)"}
