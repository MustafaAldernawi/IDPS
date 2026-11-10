param(
 [Parameter(Mandatory=$true)][string]$Schema,
 [Parameter(Mandatory=$true)][string]$RestoredFiles
)
$ErrorActionPreference='Stop'
if($Schema -notmatch '^idps_restore_[a-f0-9]{16}$'){throw 'Only an isolated idps_restore_<16 hex> schema is allowed.'}
$root=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$restoreBase=Join-Path $root '.local/restore-files'
$restore=(Resolve-Path -LiteralPath $RestoredFiles).Path
$restoreParent=(Resolve-Path -LiteralPath $restoreBase).Path
if((Split-Path -Parent $restore) -ne $restoreParent -or (Split-Path -Leaf $restore) -notmatch '^[a-f0-9]{32}$'){throw 'Files must be a direct isolated restore under .local/restore-files.'}
if(((Get-Item -LiteralPath $restore).Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0){throw 'Linked restore directory is not allowed.'}
$mysql='C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe'
$user=if($env:IDPS_SETUP_USER){$env:IDPS_SETUP_USER}else{'root'}
$password=if($env:IDPS_SETUP_PASSWORD){$env:IDPS_SETUP_PASSWORD}else{''}
function Read-RestoredDb([string]$query){
 $info=[System.Diagnostics.ProcessStartInfo]::new();$info.FileName=$mysql;$info.UseShellExecute=$false;$info.CreateNoWindow=$true
 $info.RedirectStandardOutput=$true;$info.RedirectStandardError=$true;$info.RedirectStandardInput=$true
 $info.StandardOutputEncoding=[System.Text.Encoding]::UTF8;$info.StandardInputEncoding=[System.Text.UTF8Encoding]::new($false)
 foreach($arg in @('--protocol=TCP','--host=127.0.0.1','--port=3306','--default-character-set=utf8mb4',"--user=$user",'--batch','--raw','--skip-column-names',$Schema)){[void]$info.ArgumentList.Add($arg)}
 $info.Environment['MYSQL_PWD']=$password
 $process=[System.Diagnostics.Process]::new();$process.StartInfo=$info
 if(-not $process.Start()){throw 'Could not start MySQL client.'}
 $output=$process.StandardOutput.ReadToEndAsync();$errors=$process.StandardError.ReadToEndAsync()
 $process.StandardInput.Write($query);$process.StandardInput.Close();$process.WaitForExit()
 $result=$output.GetAwaiter().GetResult();$null=$errors.GetAwaiter().GetResult()
 if($process.ExitCode -ne 0){throw "MySQL read failed (exit $($process.ExitCode)); verify isolated schema and read permissions."}
 return $result
}
$sourceText=Read-RestoredDb 'SELECT id,sha256 FROM source_files ORDER BY id;'
$modelText=Read-RestoredDb 'SELECT id,artifact_sha256,service_model_id,status FROM model_versions ORDER BY id;'
$hashIndex=@{}
foreach($prefix in @('raw-sources','private-storage','ai-artifacts')){
 $base=Join-Path $restore $prefix
 if(-not(Test-Path -LiteralPath $base)){continue}
 $resolvedBase=(Resolve-Path -LiteralPath $base).Path
 if(-not $resolvedBase.StartsWith($restore+[System.IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)){throw 'Restored category escapes its isolated directory.'}
 if(((Get-Item -LiteralPath $resolvedBase).Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0){throw 'Linked category is not allowed.'}
 foreach($file in Get-ChildItem -LiteralPath $resolvedBase -File -Recurse -Force){
  if(($file.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0){throw 'Linked restored file is not allowed.'}
  $relative=[System.IO.Path]::GetRelativePath($restore,$file.FullName).Replace('\','/')
  if($relative.StartsWith('../') -or [System.IO.Path]::IsPathRooted($relative)){throw 'Restored file escapes isolated directory.'}
  $hash=(Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
  if(-not $hashIndex.ContainsKey($hash)){$hashIndex[$hash]=[System.Collections.Generic.List[string]]::new()}
  $hashIndex[$hash].Add($relative)
 }
}
$sourceTotal=0;$sourceMatched=0;$missingSources=[System.Collections.Generic.List[object]]::new()
foreach($line in ($sourceText -split "`r?`n" | Where-Object {$_})){
 $columns=$line -split "`t",2;if($columns.Count -ne 2){throw 'Unexpected source query output.'}
 $sourceTotal++;$hash=$columns[1].ToLowerInvariant()
 $found=$hash -match '^[a-f0-9]{64}$' -and $hashIndex.ContainsKey($hash) -and @($hashIndex[$hash] | Where-Object { $_.StartsWith('raw-sources/') -or $_.StartsWith('private-storage/') }).Count -gt 0
 if($found){$sourceMatched++}else{$missingSources.Add(@{source_id=$columns[0];sha256=$hash})}
}
$modelTotal=0;$modelMatched=0;$missingModels=[System.Collections.Generic.List[object]]::new();$warnings=[System.Collections.Generic.List[object]]::new()
foreach($line in ($modelText -split "`r?`n" | Where-Object {$_})){
 $columns=$line -split "`t",4;if($columns.Count -ne 4){throw 'Unexpected model query output.'}
 $modelTotal++;$hash=$columns[1].ToLowerInvariant();$serviceId=$columns[2].ToLowerInvariant()
 $paths=if($hash -match '^[a-f0-9]{64}$' -and $hashIndex.ContainsKey($hash)){@($hashIndex[$hash] | Where-Object {$_.StartsWith('ai-artifacts/')})}else{@()}
 if($paths.Count -eq 0){$missingModels.Add(@{model_id=$columns[0];sha256=$hash;reason='artifact_checksum_not_found'});continue}
 $expected='ai-artifacts/'+$serviceId+'.json'
 if($serviceId -notmatch '^[a-f0-9]{64}$' -or $expected -notin $paths){$missingModels.Add(@{model_id=$columns[0];sha256=$hash;reason='artifact_serving_path_missing_or_mismatched'});continue}
 if($columns[3] -eq 'active'){
  $approval=Join-Path $restore ('ai-artifacts/'+$serviceId+'.approval.json')
  if(-not(Test-Path -LiteralPath $approval)){$missingModels.Add(@{model_id=$columns[0];sha256=$hash;reason='active_model_approval_metadata_missing'});continue}
  try{$approved=Get-Content -LiteralPath $approval -Raw|ConvertFrom-Json}catch{$approved=$null}
  if(-not $approved -or $approved.artifact_sha256 -ne $hash){$missingModels.Add(@{model_id=$columns[0];sha256=$hash;reason='active_model_approval_checksum_mismatch'});continue}
 }
 $modelMatched++
}
if($modelTotal -eq 0){$warnings.Add(@{code='model_references_absent';message='No model artifacts were referenced; model restore coverage was not exercised.'})}
if($sourceTotal -eq 0){$warnings.Add(@{code='source_references_absent';message='No source files were referenced; source restore coverage was not exercised.'})}
$result=@{schema=$Schema;restored_files=$restore;source_references=$sourceTotal;source_matched=$sourceMatched;missing_sources=@($missingSources);model_references=$modelTotal;model_matched=$modelMatched;missing_models=@($missingModels);warnings=@($warnings);status=if($missingSources.Count -or $missingModels.Count){'incomplete'}else{'matched'}}
$result|ConvertTo-Json -Depth 6
if($missingSources.Count -or $missingModels.Count){exit 1}
