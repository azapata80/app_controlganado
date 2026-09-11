param([string]$Version)

$ErrorActionPreference='Stop'
$projectRoot=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
if([string]::IsNullOrWhiteSpace($Version)){$Version=(Get-Content -LiteralPath (Join-Path $projectRoot 'VERSION') -Raw).Trim()}
if($Version -notmatch '^\d+\.\d+\.\d+$'){throw "Versión no válida: $Version"}

$buildRoot=Join-Path $projectRoot '.release-build'
$staging=Join-Path $buildRoot 'siteground'
$releaseRoot=Join-Path $projectRoot 'releases'
$zipPath=Join-Path $releaseRoot "sistema-gestion-ganado-siteground-v$Version.zip"
$hashPath="$zipPath.sha256"

if(-not ([IO.Path]::GetFullPath($staging)).StartsWith([IO.Path]::GetFullPath($projectRoot),[StringComparison]::OrdinalIgnoreCase)){throw 'La carpeta temporal está fuera del proyecto.'}
if(Test-Path -LiteralPath $staging){Remove-Item -LiteralPath $staging -Recurse -Force}
New-Item -ItemType Directory -Path $staging,$releaseRoot -Force | Out-Null

Get-ChildItem -LiteralPath $projectRoot -Filter '*.php' -File | Copy-Item -Destination $staging
foreach($directory in @('api','assets','docs','exports','includes','sql')){Copy-Item -LiteralPath (Join-Path $projectRoot $directory) -Destination $staging -Recurse}
New-Item -ItemType Directory -Path (Join-Path $staging 'scripts') -Force | Out-Null
Get-ChildItem -LiteralPath (Join-Path $projectRoot 'scripts') -Filter '*.php' -File | Copy-Item -Destination (Join-Path $staging 'scripts')
foreach($file in @('.htaccess','config.sample.php','VERSION','CHANGELOG.md','README.md','RESUMEN_AVANCE.md','QUICKSTART_SITEGROUND.md')){Copy-Item -LiteralPath (Join-Path $projectRoot $file) -Destination $staging}

if(Test-Path -LiteralPath $zipPath){Remove-Item -LiteralPath $zipPath -Force}
if(Test-Path -LiteralPath $hashPath){Remove-Item -LiteralPath $hashPath -Force}
Add-Type -AssemblyName System.IO.Compression.FileSystem
[IO.Compression.ZipFile]::CreateFromDirectory($staging,$zipPath,[IO.Compression.CompressionLevel]::Optimal,$false)
$hash=(Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
Set-Content -LiteralPath $hashPath -Value "$hash  $([IO.Path]::GetFileName($zipPath))" -Encoding ascii

$archive=[IO.Compression.ZipFile]::OpenRead($zipPath)
try{$entries=@($archive.Entries | ForEach-Object FullName)}finally{$archive.Dispose()}
$required=@('.htaccess','index.php','apps.php','warehouse.php','data_reports.php','config.sample.php','sql/schema.sql','sql/migrations/009_warehouse_management.sql','assets/logo-control-ganado.png')
$missing=@($required | Where-Object {$_ -notin $entries});if($missing){throw "Faltan archivos requeridos: $($missing -join ', ')"}
$forbidden=@($entries | Where-Object {$_ -match '(^|/)(?:\.env(?:\.|$)|config\.php$|node_modules(?:/|$)|tests(?:/|$)|test-results(?:/|$)|Dockerfile$|compose\.yaml$)'});if($forbidden){throw "El paquete contiene archivos excluidos: $($forbidden -join ', ')"}

[pscustomobject]@{Version=$Version;Package=$zipPath;Sha256=$hash;Files=$entries.Count;Bytes=(Get-Item -LiteralPath $zipPath).Length}
