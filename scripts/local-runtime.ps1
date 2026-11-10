param([ValidateSet('start','status','stop')][string]$Action='start')
$ErrorActionPreference='Stop'
$root=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$stateDir=Join-Path $root '.local/runtime'
New-Item -ItemType Directory -Path $stateDir -Force | Out-Null
$stateFile=Join-Path $stateDir 'processes.json'
$record=@{}
if(Test-Path -LiteralPath $stateFile){$loaded=Get-Content -LiteralPath $stateFile -Raw|ConvertFrom-Json -AsHashtable; if($loaded){$record=$loaded}}
function Save-State { $record | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $stateFile -Encoding utf8 }
function Active-Owned($name){
 if(-not $record.ContainsKey($name)){return $false}
 $item=$record[$name];$process=Get-Process -Id $item.pid -ErrorAction SilentlyContinue
 return ($null -ne $process -and $process.StartTime.ToUniversalTime().Ticks -eq ([DateTime]$item.started).ToUniversalTime().Ticks -and $process.Path -eq $item.path)
}
function Port-Listening([int]$port){ return [bool](Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue | Where-Object {$_.LocalAddress -in @('127.0.0.1','::1','0.0.0.0','::')}) }
function Start-Managed($name,$exe,$arguments,$directory){
 if(Active-Owned $name){Write-Output "$name already managed"; return}
 $out=Join-Path $stateDir "$name.stdout.log";$err=Join-Path $stateDir "$name.stderr.log"
 $process=Start-Process -FilePath $exe -ArgumentList $arguments -WorkingDirectory $directory -WindowStyle Hidden -RedirectStandardOutput $out -RedirectStandardError $err -PassThru
 $record[$name]=@{pid=$process.Id;started=$process.StartTime.ToUniversalTime().ToString('o');path=$process.Path}
 Save-State
 Write-Output "$name started"
}
if($Action -eq 'stop'){
 foreach($name in @('scheduler','queue','frontend','backend','ai')){if(Active-Owned $name){Stop-Process -Id $record[$name].pid;Write-Output "$name stopped"}elseif($record.ContainsKey($name)){Write-Output "$name already stopped or identity changed"};$record.Remove($name)}
 Save-State
 Write-Output 'MySQL is shared with Laragon and is not stopped.'
 exit 0
}
if($Action -eq 'status'){
 foreach($entry in @(@('mysql',3306),@('backend',8000),@('ai',8001),@('frontend',5173))){$state=if(Port-Listening $entry[1]){'listening'}else{'down'};Write-Output "$($entry[0]): $state"}
 foreach($name in @('queue','scheduler')){$state=if(Active-Owned $name){'running (managed)'}else{'not managed'};Write-Output "$name`: $state"}
 exit 0
}
$mysql='C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqld.exe'
if(-not (Port-Listening 3306)){
 if(-not(Test-Path -LiteralPath $mysql)){throw 'MySQL 8.4.3 not found; start compatible Laragon MySQL yourself.'}
 Start-Managed 'mysql' $mysql @('--defaults-file=C:/laragon/bin/mysql/mysql-8.4.3-winx64/my.ini','--bind-address=127.0.0.1') (Split-Path $mysql)
 for($attempt=0;$attempt -lt 90 -and -not (Port-Listening 3306);$attempt++){Start-Sleep -Seconds 1}
 if(-not(Port-Listening 3306)){throw 'MySQL did not start; inspect private runtime logs.'}
}
$php=(Get-Command php.exe -ErrorAction Stop).Source
$node=(Get-Command node.exe -ErrorAction Stop).Source
$python=(Get-Command python.exe -ErrorAction Stop).Source
$backend=Join-Path $root 'apps/backend';$frontend=Join-Path $root 'apps/frontend';$ai=Join-Path $root 'services/ai'
$dotenv=Join-Path $backend '.env';if(-not(Test-Path -LiteralPath $dotenv)){throw 'Backend .env missing; follow setup instructions.'}
$match=Select-String -LiteralPath $dotenv -Pattern '^IDPS_AI_SERVICE_KEY=(.+)$' | Select-Object -Last 1
if(-not $match){throw 'IDPS_AI_SERVICE_KEY missing from backend .env.'}
$previousKey=$env:IDPS_AI_SERVICE_KEY;$previousArtifacts=$env:IDPS_AI_ARTIFACT_DIR
try{
 $env:IDPS_AI_SERVICE_KEY=$match.Matches[0].Groups[1].Value.Trim().Trim('"').Trim("'")
 $env:IDPS_AI_ARTIFACT_DIR=Join-Path $root '.local/ai-artifacts'
 if(-not(Port-Listening 8001)){Start-Managed 'ai' $python @('-B','-m','uvicorn','app.main:app','--host','127.0.0.1','--port','8001') $ai}else{Write-Output 'AI port occupied; verify existing listener and health.'}
}finally{$env:IDPS_AI_SERVICE_KEY=$previousKey;$env:IDPS_AI_ARTIFACT_DIR=$previousArtifacts}
if(-not(Port-Listening 8000)){Start-Managed 'backend' $php @('-S','127.0.0.1:8000','-t','public') $backend}else{Write-Output 'Backend port occupied; verify existing listener and health.'}
if(-not(Port-Listening 5173)){Start-Managed 'frontend' $node @('node_modules/vite/bin/vite.js','--host','127.0.0.1','--port','5173','--strictPort') $frontend}else{Write-Output 'Frontend port occupied; verify existing listener and health.'}
Start-Managed 'queue' $php @('artisan','queue:work','database','--sleep=2','--tries=3','--timeout=240','--no-interaction') $backend
Start-Managed 'scheduler' $php @('artisan','schedule:work','--no-interaction') $backend
Write-Output 'Startup initiated. Run local-runtime.ps1 status and check http://127.0.0.1:8000/api/v1/health.'
