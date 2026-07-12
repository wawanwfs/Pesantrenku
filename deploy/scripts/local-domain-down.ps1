$ErrorActionPreference = 'Stop'

$root = Resolve-Path (Join-Path $PSScriptRoot '..\..')
docker compose -f (Join-Path $root 'docker-compose.local-domain.yml') down

Write-Output 'Pesantrenku local domain stack has stopped.'
