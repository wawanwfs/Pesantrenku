param(
    [switch]$SkipBuild
)

$ErrorActionPreference = 'Stop'

$root = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$compose = Join-Path $root 'docker-compose.local-domain.yml'
$image = 'pesantrenku:local-domain'
$shards = @(
    @{ Service = 'app-e2e-desktop-a'; Database = 'pesantenku_e2e_desktop_a'; Url = 'https://e2e-desktop-a.pesantrenku.localhost/up' },
    @{ Service = 'app-e2e-desktop-b'; Database = 'pesantenku_e2e_desktop_b'; Url = 'https://e2e-desktop-b.pesantrenku.localhost/up' },
    @{ Service = 'app-e2e-mobile-a'; Database = 'pesantenku_e2e_mobile_a'; Url = 'https://e2e-mobile-a.pesantrenku.localhost/up' },
    @{ Service = 'app-e2e-mobile-b'; Database = 'pesantenku_e2e_mobile_b'; Url = 'https://e2e-mobile-b.pesantrenku.localhost/up' }
)

if (-not $SkipBuild) {
    docker build -t $image (Join-Path $root 'backend')
    if ($LASTEXITCODE -ne 0) {
        throw 'Gagal membangun image E2E Pesantrenku.'
    }
}

$env:PESANTRENKU_LOCAL_IMAGE = $image
docker compose -f $compose config --quiet
if ($LASTEXITCODE -ne 0) {
    throw 'Konfigurasi Docker Compose lokal tidak valid.'
}

docker compose -f $compose up -d --wait mysql redis minio
docker compose -f $compose up minio-init
if ($LASTEXITCODE -ne 0) {
    throw 'MinIO lokal gagal disiapkan.'
}

foreach ($shard in $shards) {
    $sql = "CREATE DATABASE IF NOT EXISTS ``$($shard.Database)`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON ``$($shard.Database)``.* TO 'pesantenku'@'%'; FLUSH PRIVILEGES;"
    docker compose -f $compose exec -T mysql mysql -uroot -ppesantenku-local-root -e $sql
    if ($LASTEXITCODE -ne 0) {
        throw "Gagal membuat database $($shard.Database)."
    }
}

$services = $shards.Service
docker compose -f $compose --profile e2e up -d --force-recreate @services reverb caddy
if ($LASTEXITCODE -ne 0) {
    throw 'Service shard E2E gagal dijalankan.'
}

foreach ($shard in $shards) {
    docker compose -f $compose --profile e2e exec -T $shard.Service php artisan migrate:fresh --seed --force
    if ($LASTEXITCODE -ne 0) {
        throw "Migrasi atau seed gagal pada $($shard.Service)."
    }
}

$deadline = (Get-Date).AddMinutes(3)
foreach ($shard in $shards) {
    do {
        curl.exe -k -fsS $shard.Url | Out-Null
        if ($LASTEXITCODE -eq 0) {
            break
        }
        if ((Get-Date) -gt $deadline) {
            throw "Shard $($shard.Service) tidak siap di $($shard.Url)."
        }
        Start-Sleep -Seconds 2
    } while ($true)
}

Write-Output 'Empat shard E2E Pesantrenku siap dengan database terisolasi.'
