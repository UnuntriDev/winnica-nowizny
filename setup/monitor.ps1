param(
    [string]$Url = 'http://localhost:8080/wp-json/winnica/v1/health'
)

$ErrorActionPreference = 'Stop'
$response = Invoke-RestMethod -Uri $Url -Method Get -TimeoutSec 15
if ($response.status -ne 'ok') {
    throw "Monitoring zgłosił stan: $($response.status)"
}
Write-Host "OK — $($response.service)"
