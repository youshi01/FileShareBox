param(
    [string]$NginxRoot = "D:\phpstudy_pro\Extensions\Nginx1.15.11",
    [string]$PhpRoot = "D:\phpstudy_pro\Extensions\php\php8.2.9nts",
    [int]$PhpPort = 9000
)

$phpCgiExe = Join-Path $PhpRoot "php-cgi.exe"
$phpIni = Join-Path $PhpRoot "php.ini"
$nginxExe = Join-Path $NginxRoot "nginx.exe"

if (-not (Test-Path $phpCgiExe)) {
    throw "php-cgi not found: $phpCgiExe"
}
if (-not (Test-Path $nginxExe)) {
    throw "nginx not found: $nginxExe"
}

$phpListening = Get-NetTCPConnection -LocalPort $PhpPort -State Listen -ErrorAction SilentlyContinue
if (-not $phpListening) {
    Start-Process -FilePath $phpCgiExe -ArgumentList "-b", "127.0.0.1:$PhpPort", "-c", $phpIni -WindowStyle Hidden
    Start-Sleep -Seconds 1
}

if (-not (Get-Process nginx -ErrorAction SilentlyContinue)) {
    Start-Process -FilePath $nginxExe -WorkingDirectory $NginxRoot -ArgumentList "-p", "$NginxRoot\", "-c", "conf/nginx.conf" -WindowStyle Hidden
    Start-Sleep -Seconds 1
}

Write-Output "Nginx/PHP-CGI started (if they were not running)."
Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue |
    Where-Object { $_.LocalPort -in 80, 8080, 9000 } |
    Select-Object LocalAddress, LocalPort, OwningProcess
