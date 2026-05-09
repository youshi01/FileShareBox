Get-Process nginx -ErrorAction SilentlyContinue | Stop-Process -Force
Get-Process php-cgi -ErrorAction SilentlyContinue | Stop-Process -Force

Write-Output "Attempted to stop nginx and php-cgi processes."
Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue |
    Where-Object { $_.LocalPort -in 80, 8080, 9000 } |
    Select-Object LocalAddress, LocalPort, OwningProcess
