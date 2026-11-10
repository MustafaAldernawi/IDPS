param([ValidateSet('create','test-restore')][string]$Action='create',[string]$BackupPath)
$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.Security
Add-Type -AssemblyName System.IO.Compression
$root=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$backupDir=Join-Path $root '.local/backups'
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
$utf8=[System.Text.UTF8Encoding]::new($false)
if($Action -eq 'create'){
 $sources=@(
  @{prefix='raw-sources';path=(Join-Path $root 'data/raw-sources')},
  @{prefix='private-storage';path=(Join-Path $root 'apps/backend/storage/app/private')},
  @{prefix='ai-artifacts';path=(Join-Path $root '.local/ai-artifacts')}
 )
 $files=[System.Collections.Generic.List[object]]::new();$size=[long]0
 foreach($source in $sources){
  if(-not(Test-Path -LiteralPath $source.path)){continue}
  $base=(Resolve-Path -LiteralPath $source.path).Path
  if(((Get-Item -LiteralPath $base).Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0){throw 'Refusing linked source directory.'}
  foreach($file in Get-ChildItem -LiteralPath $base -File -Recurse -Force){
   if($file.Name -eq '.gitignore'){continue}
   if(($file.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0){throw 'Refusing linked source file.'}
   $relative=[System.IO.Path]::GetRelativePath($base,$file.FullName).Replace('\','/')
   if($relative.StartsWith('../') -or [System.IO.Path]::IsPathRooted($relative)){throw 'Source file escapes its directory.'}
   $size+=$file.Length;if($size -gt 268435456){throw 'Source files exceed 256 MiB; use an approved streaming backup solution.'}
   $files.Add(@{entry=($source.prefix+'/'+$relative);path=$file.FullName;sha256=(Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash.ToLowerInvariant();bytes=$file.Length})
  }
 }
 if($files.Count -eq 0){throw 'No source files found.'}
 $memory=[System.IO.MemoryStream]::new()
 $zip=[System.IO.Compression.ZipArchive]::new($memory,[System.IO.Compression.ZipArchiveMode]::Create,$true)
 try{
  foreach($file in $files){
   $entry=$zip.CreateEntry($file.entry,[System.IO.Compression.CompressionLevel]::Optimal)
   $out=$entry.Open();$input=[System.IO.File]::OpenRead($file.path)
   try{$input.CopyTo($out)}finally{$input.Dispose();$out.Dispose()}
   if((Get-FileHash -LiteralPath $file.path -Algorithm SHA256).Hash.ToLowerInvariant() -ne $file.sha256){throw 'A source file changed during backup; retry with writes paused.'}
  }
  $manifest=@{format='idps-files-v1';created_utc=[DateTime]::UtcNow.ToString('o');files=@($files|ForEach-Object {@{entry=$_.entry;sha256=$_.sha256;bytes=$_.bytes}})}
  $manifestEntry=$zip.CreateEntry('_manifest.json');$stream=$manifestEntry.Open();$writer=[System.IO.StreamWriter]::new($stream,$utf8)
  try{$writer.Write(($manifest|ConvertTo-Json -Depth 6 -Compress))}finally{$writer.Dispose();$stream.Dispose()}
 }finally{$zip.Dispose()}
 $encrypted=[System.Security.Cryptography.ProtectedData]::Protect($memory.ToArray(),$null,[System.Security.Cryptography.DataProtectionScope]::CurrentUser);$memory.Dispose()
 $path=Join-Path $backupDir ('idps-files-'+[DateTime]::UtcNow.ToString('yyyyMMddTHHmmss')+'-'+[Guid]::NewGuid().ToString('N')+'.dpapi')
 [System.IO.File]::WriteAllBytes($path,$encrypted)
 Write-Output "Encrypted files backup saved: $path; files: $($files.Count)"
 Write-Output ('SHA256: '+(Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash)
 exit 0
}
if(-not $BackupPath){throw 'Supply -BackupPath for test-restore.'}
$resolved=(Resolve-Path -LiteralPath $BackupPath).Path
if(-not $resolved.StartsWith($backupDir+[System.IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)){throw 'Backup must be inside .local/backups.'}
$bytes=[System.Security.Cryptography.ProtectedData]::Unprotect([System.IO.File]::ReadAllBytes($resolved),$null,[System.Security.Cryptography.DataProtectionScope]::CurrentUser)
$memory=[System.IO.MemoryStream]::new($bytes,$false);$zip=[System.IO.Compression.ZipArchive]::new($memory,[System.IO.Compression.ZipArchiveMode]::Read,$false)
$restoreRoot=Join-Path (Join-Path $root '.local/restore-files') ([Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $restoreRoot -Force | Out-Null
try{
 $manifestEntry=$zip.GetEntry('_manifest.json');if(-not $manifestEntry){throw 'Missing files manifest.'}
 $stream=$manifestEntry.Open();$reader=[System.IO.StreamReader]::new($stream,$utf8)
 try{$manifest=$reader.ReadToEnd()|ConvertFrom-Json}finally{$reader.Dispose();$stream.Dispose()}
 if($manifest.format -ne 'idps-files-v1'){throw 'Unknown files backup format.'}
 $seen=[System.Collections.Generic.HashSet[string]]::new([System.StringComparer]::OrdinalIgnoreCase)
 foreach($file in $manifest.files){
  $name=[string]$file.entry
  if($name -notmatch '^(raw-sources|private-storage|ai-artifacts)/' -or $name.Contains('\') -or $name -match '(^|/)\.\.?(/|$)' -or $name.Contains(':') -or -not $seen.Add($name)){throw 'Invalid or duplicate archive entry.'}
  $target=[System.IO.Path]::GetFullPath((Join-Path $restoreRoot $name))
  if(-not $target.StartsWith($restoreRoot+[System.IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)){throw 'Archive entry escapes test restore.'}
  $entry=$zip.GetEntry($name);if(-not $entry -or $entry.Length -ne [long]$file.bytes){throw 'Missing or invalid archived file.'}
  New-Item -ItemType Directory -Path (Split-Path $target) -Force | Out-Null
  $input=$entry.Open();$out=[System.IO.File]::Open($target,[System.IO.FileMode]::CreateNew,[System.IO.FileAccess]::Write,[System.IO.FileShare]::None)
  try{$input.CopyTo($out)}finally{$input.Dispose();$out.Dispose()}
  if((Get-FileHash -LiteralPath $target -Algorithm SHA256).Hash.ToLowerInvariant() -ne $file.sha256){throw 'Restored file checksum mismatch.'}
 }
 if($zip.Entries.Count -ne $seen.Count+1){throw 'Unexpected archive entries.'}
 Write-Output "Isolated files restore validated: $($seen.Count) SHA256 matches at $restoreRoot. Originals were not changed."
}finally{$zip.Dispose();$memory.Dispose()}
