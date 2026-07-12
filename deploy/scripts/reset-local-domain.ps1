$ErrorActionPreference = 'Stop'

$root = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$compose = Join-Path $root 'docker-compose.local-domain.yml'
$env:PESANTRENKU_LOCAL_IMAGE = 'pesantrenku:local-domain'

docker compose -f $compose stop app queue scheduler reverb | Out-Null
docker compose -f $compose up -d --wait mysql redis | Out-Null
docker compose -f $compose exec -T redis redis-cli FLUSHDB | Out-Null
docker compose -f $compose run --rm --no-deps app sh -lc 'find storage/app/private -mindepth 1 ! -name .gitignore -delete; find storage/app/public -mindepth 1 ! -name .gitignore -delete'
if ($LASTEXITCODE -ne 0) {
    throw 'Pesantrenku local operation files cleanup failed.'
}
docker compose -f $compose run --rm --no-deps app php artisan migrate:fresh --seed --force
if ($LASTEXITCODE -ne 0) {
    throw 'Pesantrenku local database reset failed.'
}

docker compose -f $compose up -d app queue scheduler reverb | Out-Null
docker restart pesantrenku-domain-caddy-1 | Out-Null

$deadline = (Get-Date).AddMinutes(2)
do {
    $health = docker inspect pesantrenku-domain-app-1 --format '{{.State.Health.Status}}'
    if ($health -eq 'healthy') {
        break
    }
    if ((Get-Date) -gt $deadline) {
        throw "Pesantrenku app did not become healthy after reset: $health"
    }
    Start-Sleep -Seconds 2
} while ($true)

Write-Output 'Pesantrenku local database has been reset and seeded.'
