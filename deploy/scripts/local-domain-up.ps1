param(
    [switch]$Build
)

$ErrorActionPreference = 'Stop'

$requiredBuildImages = @('NODE_BASE_IMAGE', 'COMPOSER_BASE_IMAGE', 'RUNTIME_BASE_IMAGE')
foreach ($name in $requiredBuildImages) {
    $value = [Environment]::GetEnvironmentVariable($name)
    if ([string]::IsNullOrWhiteSpace($value) -or $value -notmatch '@sha256:[a-f0-9]{64}$') {
        throw "$name must be set to an immutable image digest before building."
    }
}
$dockerBuildArgs = @(
    '--build-arg', "NODE_BASE_IMAGE=$env:NODE_BASE_IMAGE",
    '--build-arg', "COMPOSER_BASE_IMAGE=$env:COMPOSER_BASE_IMAGE",
    '--build-arg', "RUNTIME_BASE_IMAGE=$env:RUNTIME_BASE_IMAGE"
)

$root = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$compose = Join-Path $root 'docker-compose.local-domain.yml'
$certificate = Join-Path $root 'build\caddy-local-root.crt'
$hostsSnippet = Join-Path $root 'build\pesantrenku-hosts.txt'
$localImage = 'pesantrenku:local-domain'

New-Item -ItemType Directory -Force (Split-Path $certificate) | Out-Null

$imageExists = docker image ls --format '{{.Repository}}:{{.Tag}}' | Where-Object { $_ -eq $localImage }
if ($Build -or -not $imageExists) {
    docker build @dockerBuildArgs -t $localImage (Join-Path $root 'backend')
    if ($LASTEXITCODE -ne 0) {
        throw 'Pesantrenku local image failed to build.'
    }
}

$env:PESANTRENKU_LOCAL_IMAGE = $localImage

docker compose -f $compose config --quiet
docker compose -f $compose up -d --wait mysql redis
if ($LASTEXITCODE -ne 0) {
    throw 'Pesantrenku local database services failed to start.'
}

docker compose -f $compose run --rm --no-deps app php artisan migrate --force
if ($LASTEXITCODE -ne 0) {
    throw 'Pesantrenku local database migration failed.'
}

$userCount = docker compose -f $compose exec -T mysql mysql -upesantenku -ppesantenku-local -Nse 'SELECT COUNT(*) FROM pesantenku.users'
if ($LASTEXITCODE -ne 0) {
    throw 'Pesantrenku local database seed check failed.'
}
if ([int]$userCount -eq 0) {
    docker compose -f $compose run --rm --no-deps app php artisan db:seed --force
    if ($LASTEXITCODE -ne 0) {
        throw 'Pesantrenku local database seeding failed.'
    }
}
docker compose -f $compose up -d
if ($LASTEXITCODE -ne 0) {
    throw 'Pesantrenku local domain stack failed to start.'
}

$startupDeadline = (Get-Date).AddMinutes(2)
do {
    $appHealth = docker inspect pesantrenku-domain-app-1 --format '{{.State.Health.Status}}' 2>$null
    $caddyRunning = docker inspect pesantrenku-domain-caddy-1 --format '{{.State.Running}}' 2>$null
    if ($appHealth -eq 'healthy' -and $caddyRunning -eq 'true') {
        break
    }
    if ((Get-Date) -gt $startupDeadline) {
        throw "Local domain services did not become ready (app=$appHealth, caddy=$caddyRunning)."
    }
    Start-Sleep -Seconds 2
} while ($true)

$deadline = (Get-Date).AddMinutes(2)
do {
    $ErrorActionPreference = 'SilentlyContinue'
    docker cp pesantrenku-domain-caddy-1:/data/caddy/pki/authorities/local/root.crt $certificate 2>$null
    $copied = $LASTEXITCODE -eq 0 -and (Test-Path $certificate)
    $ErrorActionPreference = 'Stop'
    if ($copied) {
        break
    }
    if ((Get-Date) -gt $deadline) {
        throw 'Caddy local root certificate was not generated.'
    }
    Start-Sleep -Seconds 2
} while ($true)

certutil -user -addstore Root $certificate | Out-Null

$hostnames = @(
    'pesantrenku.test',
    'www.pesantrenku.test',
    'api.pesantrenku.test',
    'ws.pesantrenku.test',
    'monitor.pesantrenku.test',
    'db.pesantrenku.test',
    'storage.pesantrenku.test'
)
$hostsLine = '127.0.0.1 ' + ($hostnames -join ' ')
Set-Content -Path $hostsSnippet -Value $hostsLine

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($identity)
$isAdmin = $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
$hostsFile = Join-Path $env:SystemRoot 'System32\drivers\etc\hosts'

if ($isAdmin -and -not (Select-String -Path $hostsFile -SimpleMatch 'pesantrenku.test' -Quiet)) {
    Add-Content -Path $hostsFile -Value "`r`n# Pesantrenku local domain`r`n$hostsLine"
}

Write-Output 'Pesantrenku local domain is ready:'
Write-Output '  https://pesantrenku.localhost'
Write-Output '  https://api.pesantrenku.localhost'
Write-Output '  wss://ws.pesantrenku.localhost'
Write-Output '  https://monitor.pesantrenku.localhost'
Write-Output '  https://db.pesantrenku.localhost'
Write-Output '  https://storage.pesantrenku.localhost'

if (-not $isAdmin) {
    Write-Output "Optional .test hosts entry is available at: $hostsSnippet"
    Write-Output 'Run this script once from an Administrator PowerShell to enable pesantrenku.test.'
}
