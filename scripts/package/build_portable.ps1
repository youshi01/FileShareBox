param(
    [string]$OutputDir = "",
    [string]$PhpSource = "D:\phpstudy_pro\Extensions\php\php8.2.9nts",
    [string]$NginxSource = "D:\phpstudy_pro\Extensions\Nginx1.15.11",
    [string]$MySqlSource = "D:\phpstudy_pro\Extensions\MySQL8.0.12",
    [switch]$SkipRuntimeCopy
)

$ErrorActionPreference = "Stop"

function Write-Step([string]$message) {
    Write-Host "[build_portable] $message"
}

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Split-Path -Parent (Split-Path -Parent $scriptDir)

if ([string]::IsNullOrWhiteSpace($OutputDir)) {
    $OutputDir = Join-Path $projectRoot "dist\portable"
}

$outputRoot = (Resolve-Path -Path (Split-Path -Parent $OutputDir) -ErrorAction SilentlyContinue)
if (-not $outputRoot) {
    New-Item -Path (Split-Path -Parent $OutputDir) -ItemType Directory -Force | Out-Null
}

if (Test-Path $OutputDir) {
    Write-Step "Cleaning output directory: $OutputDir"
    Remove-Item -Recurse -Force $OutputDir
}

Write-Step "Creating output directories"
$appOut = Join-Path $OutputDir "app"
$runtimeOut = Join-Path $OutputDir "runtime"
$prefixLogsOut = Join-Path $OutputDir "logs"
$prefixTempOut = Join-Path $OutputDir "temp"
$logsOut = Join-Path $runtimeOut "logs"
$runOut = Join-Path $runtimeOut "run"
New-Item -ItemType Directory -Force -Path $appOut, $runtimeOut, $prefixLogsOut, $prefixTempOut, $logsOut, $runOut | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $prefixTempOut "client_body_temp"), (Join-Path $prefixTempOut "proxy_temp"), (Join-Path $prefixTempOut "fastcgi_temp"), (Join-Path $prefixTempOut "uwsgi_temp"), (Join-Path $prefixTempOut "scgi_temp") | Out-Null

Write-Step "Copying application files"
$excludeDirs = @(
    "$projectRoot\.git",
    "$projectRoot\dist",
    "$projectRoot\storage\uploads",
    "$projectRoot\storage\logs"
)

$robocopyArgs = @(
    $projectRoot,
    $appOut,
    "/E",
    "/R:2",
    "/W:1",
    "/XD"
) + $excludeDirs + @(
    "/XF",
    ".env"
)

& robocopy @robocopyArgs | Out-Null
if ($LASTEXITCODE -ge 8) {
    throw "robocopy failed while copying app files. exit=$LASTEXITCODE"
}

New-Item -ItemType Directory -Force -Path (Join-Path $appOut "storage\uploads"), (Join-Path $appOut "storage\logs") | Out-Null
Set-Content -Path (Join-Path $appOut "storage\uploads\.gitkeep") -Value "" -Encoding ASCII
Set-Content -Path (Join-Path $appOut "storage\logs\.gitkeep") -Value "" -Encoding ASCII

if (-not $SkipRuntimeCopy) {
    foreach ($src in @($PhpSource, $NginxSource, $MySqlSource)) {
        if (-not (Test-Path $src)) {
            throw "Runtime source directory not found: $src"
        }
    }

    Write-Step "Copying PHP runtime from: $PhpSource"
    & robocopy $PhpSource (Join-Path $runtimeOut "php") /E /R:2 /W:1 | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "Failed to copy PHP runtime. exit=$LASTEXITCODE" }

    Write-Step "Copying Nginx runtime from: $NginxSource"
    & robocopy $NginxSource (Join-Path $runtimeOut "nginx") /E /R:2 /W:1 | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "Failed to copy Nginx runtime. exit=$LASTEXITCODE" }

    Write-Step "Copying MySQL runtime from: $MySqlSource"
    & robocopy $MySqlSource (Join-Path $runtimeOut "mysql") /E /R:2 /W:1 /XD "$MySqlSource\data" | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "Failed to copy MySQL runtime. exit=$LASTEXITCODE" }
} else {
    Write-Step "SkipRuntimeCopy is set, runtime folders were not copied."
}

# Keep package clean and deterministic.
if (Test-Path (Join-Path $runtimeOut "mysql\data")) {
    Remove-Item -Recurse -Force (Join-Path $runtimeOut "mysql\data")
}
if (Test-Path (Join-Path $runtimeOut "mysql-data")) {
    Remove-Item -Recurse -Force (Join-Path $runtimeOut "mysql-data")
}
if (Test-Path (Join-Path $appOut "storage\.installed")) {
    Remove-Item -Force (Join-Path $appOut "storage\.installed")
}

Write-Step "Writing portable Nginx config"
$nginxConfPath = Join-Path $runtimeOut "nginx\conf\portable-nginx.conf"
New-Item -ItemType Directory -Force -Path (Split-Path -Parent $nginxConfPath) | Out-Null
@'
worker_processes  1;
pid runtime/run/nginx.pid;
error_log runtime/logs/nginx-error.log;

events {
    worker_connections 1024;
}

http {
    include       mime.types;
    default_type  application/octet-stream;
    sendfile      on;
    keepalive_timeout 65;
    client_max_body_size 100m;
    access_log    runtime/logs/nginx-access.log;

    server {
        listen       18080;
        server_name  localhost 127.0.0.1;
        root         app/public;
        index        index.php index.html;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            include        fastcgi_params;
            fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
            fastcgi_pass   127.0.0.1:19001;
            fastcgi_index  index.php;
        }

        location ~* \.(?:css|js|jpg|jpeg|gif|png|svg|ico|webp)$ {
            expires 7d;
            access_log off;
        }
    }
}
'@ | Set-Content -Path $nginxConfPath -Encoding ASCII

Write-Step "Writing start.cmd"
$startCmdPath = Join-Path $OutputDir "start.cmd"
@'
@echo off
setlocal ENABLEDELAYEDEXPANSION
chcp 65001 >nul 2>nul

set "APP_HOME=%~dp0"
if "%APP_HOME:~-1%"=="\" set "APP_HOME=%APP_HOME:~0,-1%"

set "PHP_DIR=%APP_HOME%\runtime\php"
set "NGINX_DIR=%APP_HOME%\runtime\nginx"
set "MYSQL_DIR=%APP_HOME%\runtime\mysql"
set "MYSQL_DATA=%APP_HOME%\runtime\mysql-data"
set "APP_LOG=%APP_HOME%\logs"
set "APP_TEMP=%APP_HOME%\temp"
set "RUNTIME_RUN=%APP_HOME%\runtime\run"
set "RUNTIME_LOG=%APP_HOME%\runtime\logs"

set "WEB_PORT=18080"
set "PHP_FCGI_PORT=19001"
set "MYSQL_PORT=13306"

if not exist "%RUNTIME_RUN%" mkdir "%RUNTIME_RUN%"
if not exist "%RUNTIME_LOG%" mkdir "%RUNTIME_LOG%"
if not exist "%MYSQL_DATA%" mkdir "%MYSQL_DATA%"
if not exist "%APP_LOG%" mkdir "%APP_LOG%"
if not exist "%APP_TEMP%" mkdir "%APP_TEMP%"
if not exist "%APP_TEMP%\client_body_temp" mkdir "%APP_TEMP%\client_body_temp"
if not exist "%APP_TEMP%\proxy_temp" mkdir "%APP_TEMP%\proxy_temp"
if not exist "%APP_TEMP%\fastcgi_temp" mkdir "%APP_TEMP%\fastcgi_temp"
if not exist "%APP_TEMP%\uwsgi_temp" mkdir "%APP_TEMP%\uwsgi_temp"
if not exist "%APP_TEMP%\scgi_temp" mkdir "%APP_TEMP%\scgi_temp"

if not exist "%PHP_DIR%\php.exe" (
    echo [ERROR] PHP runtime not found: %PHP_DIR%\php.exe
    exit /b 1
)
if not exist "%NGINX_DIR%\nginx.exe" (
    echo [ERROR] Nginx runtime not found: %NGINX_DIR%\nginx.exe
    exit /b 1
)
if not exist "%MYSQL_DIR%\bin\mysqld.exe" (
    echo [ERROR] MySQL runtime not found: %MYSQL_DIR%\bin\mysqld.exe
    exit /b 1
)

call :writePhpIni || exit /b 1
call :writeMyIni || exit /b 1

call :isListening %MYSQL_PORT%
if errorlevel 1 (
    if not exist "%MYSQL_DATA%\mysql" (
        echo [INFO] Initializing MySQL data directory...
        "%MYSQL_DIR%\bin\mysqld.exe" --defaults-file="%MYSQL_DIR%\my-portable.ini" --initialize-insecure --console
        if errorlevel 1 (
            echo [ERROR] Failed to initialize MySQL data directory.
            exit /b 1
        )
    )

    echo [INFO] Starting MySQL...
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath '%MYSQL_DIR%\bin\mysqld.exe' -ArgumentList @('--defaults-file=%MYSQL_DIR%\my-portable.ini','--console') -WindowStyle Hidden"
    call :waitPort %MYSQL_PORT% 40
    if errorlevel 1 (
        echo [ERROR] MySQL did not start on port %MYSQL_PORT%.
        exit /b 1
    )
)

if not exist "%APP_HOME%\app\storage\.installed" (
    echo [INFO] First run setup...
    call :writeEnv || exit /b 1

    "%MYSQL_DIR%\bin\mysql.exe" --protocol=TCP -hlocalhost -P%MYSQL_PORT% -uroot -e "CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY ''; GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION; FLUSH PRIVILEGES;"
    if errorlevel 1 (
        echo [ERROR] Failed to create root@127.0.0.1 user.
        exit /b 1
    )

    "%MYSQL_DIR%\bin\mysql.exe" --protocol=TCP -h127.0.0.1 -P%MYSQL_PORT% -uroot -e "CREATE DATABASE IF NOT EXISTS filecodebox CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
    if errorlevel 1 (
        echo [ERROR] Failed to create database.
        exit /b 1
    )

    pushd "%APP_HOME%\app"
    "%PHP_DIR%\php.exe" -c "%PHP_DIR%\php-portable.ini" "scripts\install.php"
    set "INSTALL_EXIT=!ERRORLEVEL!"
    popd
    if not "!INSTALL_EXIT!"=="0" (
        echo [ERROR] install.php failed.
        exit /b 1
    )

    > "%APP_HOME%\app\storage\.installed" echo ok
)

call :isListening %PHP_FCGI_PORT%
if errorlevel 1 (
    echo [INFO] Starting PHP-CGI...
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath '%PHP_DIR%\php-cgi.exe' -ArgumentList @('-b','127.0.0.1:%PHP_FCGI_PORT%','-c','%PHP_DIR%\php-portable.ini') -WindowStyle Hidden"
    call :waitPort %PHP_FCGI_PORT% 20
    if errorlevel 1 (
        echo [ERROR] PHP-CGI did not start on port %PHP_FCGI_PORT%.
        exit /b 1
    )
)

call :isListening %WEB_PORT%
if errorlevel 1 (
    echo [INFO] Starting Nginx...
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath '%NGINX_DIR%\nginx.exe' -ArgumentList @('-p','%APP_HOME%\','-c','runtime/nginx/conf/portable-nginx.conf') -WindowStyle Hidden"
    call :waitPort %WEB_PORT% 20
    if errorlevel 1 (
        echo [ERROR] Nginx did not start on port %WEB_PORT%.
        exit /b 1
    )
)

echo [OK] FileCodeBox started at http://127.0.0.1:%WEB_PORT%/
start "" "http://127.0.0.1:%WEB_PORT%/"
exit /b 0

:writePhpIni
set "PHP_DIR_UNIX=%PHP_DIR:\=/%"
set "PHP_LOG_UNIX=%RUNTIME_LOG:\=/%/php-error.log"
(
    echo [Date]
    echo date.timezone=Asia/Shanghai
    echo [PHP]
    echo max_execution_time=300
    echo max_input_time=60
    echo max_input_vars=3000
    echo memory_limit=256M
    echo upload_max_filesize=2048M
    echo post_max_size=2050M
    echo display_errors=Off
    echo log_errors=On
    echo error_log="%PHP_LOG_UNIX%"
    echo extension_dir="%PHP_DIR_UNIX%/ext"
    echo extension=mysqli
    echo extension=pdo_mysql
    echo extension=mbstring
    echo extension=fileinfo
    echo extension=curl
    echo extension=openssl
    echo extension=gd
) > "%PHP_DIR%\php-portable.ini"
exit /b 0

:writeMyIni
set "MYSQL_DIR_UNIX=%MYSQL_DIR:\=/%"
set "MYSQL_DATA_UNIX=%MYSQL_DATA:\=/%"
set "MYSQL_LOG_UNIX=%RUNTIME_LOG:\=/%/mysql-error.log"
set "MYSQL_PID_UNIX=%RUNTIME_RUN:\=/%/mysql.pid"
(
    echo [mysqld]
    echo basedir="%MYSQL_DIR_UNIX%"
    echo datadir="%MYSQL_DATA_UNIX%"
    echo port=%MYSQL_PORT%
    echo bind-address=127.0.0.1
    echo pid-file="%MYSQL_PID_UNIX%"
    echo log-error="%MYSQL_LOG_UNIX%"
    echo plugin-dir="%MYSQL_DIR_UNIX%/lib/plugin"
    echo character-set-server=utf8mb4
    echo default_authentication_plugin=mysql_native_password
    echo skip_log_bin
    echo [client]
    echo port=%MYSQL_PORT%
) > "%MYSQL_DIR%\my-portable.ini"
exit /b 0

:writeEnv
(
    echo APP_NAME=FileCodeBox PHP
    echo APP_BASE_URL=http://127.0.0.1:%WEB_PORT%
    echo APP_TIMEZONE=Asia/Shanghai
    echo APP_DEBUG=0
    echo.
    echo DB_HOST=127.0.0.1
    echo DB_PORT=%MYSQL_PORT%
    echo DB_DATABASE=filecodebox
    echo DB_USERNAME=root
    echo DB_PASSWORD=
    echo DB_CHARSET=utf8mb4
    echo.
    echo UPLOAD_DIR=
    echo MAX_UPLOAD_MB=100
    echo MAX_TEXT_LENGTH=20000
    echo.
    echo UPLOAD_WINDOW_SECONDS=300
    echo UPLOAD_MAX_HITS=10
    echo UPLOAD_BLOCK_MINUTES=10
    echo.
    echo FETCH_FAIL_WINDOW_SECONDS=300
    echo FETCH_FAIL_MAX_HITS=8
    echo FETCH_FAIL_BLOCK_MINUTES=10
    echo.
    echo CODE_MIN_LEN=4
    echo CODE_MAX_LEN=32
    echo DEFAULT_CODE_LEN=6
    echo SESSION_TTL=7200
    echo ALLOW_GUEST_UPLOAD=1
) > "%APP_HOME%\app\.env"
exit /b 0

:isListening
powershell -NoProfile -ExecutionPolicy Bypass -Command "$p=%1; if (Get-NetTCPConnection -LocalPort $p -State Listen -ErrorAction SilentlyContinue) { exit 0 } else { exit 1 }"
exit /b %ERRORLEVEL%

:waitPort
set "WAIT_PORT=%1"
set "WAIT_MAX=%2"
for /L %%i in (1,1,%WAIT_MAX%) do (
    call :isListening %WAIT_PORT%
    if not errorlevel 1 exit /b 0
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Sleep -Seconds 1" >nul
)
exit /b 1
'@ | Set-Content -Path $startCmdPath -Encoding ASCII

Write-Step "Writing stop.cmd"
$stopCmdPath = Join-Path $OutputDir "stop.cmd"
@'
@echo off
setlocal

set "APP_HOME=%~dp0"
if "%APP_HOME:~-1%"=="\" set "APP_HOME=%APP_HOME:~0,-1%"

powershell -NoProfile -ExecutionPolicy Bypass -Command "$targets=@('%APP_HOME%\runtime\nginx\nginx.exe','%APP_HOME%\runtime\php\php-cgi.exe','%APP_HOME%\runtime\mysql\bin\mysqld.exe'); $procs=Get-CimInstance Win32_Process | Where-Object { $_.ExecutablePath -and ($targets -contains $_.ExecutablePath) }; foreach($p in $procs){ Stop-Process -Id $p.ProcessId -Force -ErrorAction SilentlyContinue }" >nul

call :killByPort 18080
call :killByPort 19001
call :killByPort 13306

echo [OK] Requested stop for web/php/mysql ports 18080/19001/13306.
exit /b 0

:killByPort
powershell -NoProfile -ExecutionPolicy Bypass -Command "$pids=Get-NetTCPConnection -LocalPort %1 -State Listen -ErrorAction SilentlyContinue | Select-Object -ExpandProperty OwningProcess -Unique; foreach($id in $pids){ Stop-Process -Id $id -Force -ErrorAction SilentlyContinue }" >nul
exit /b 0
'@ | Set-Content -Path $stopCmdPath -Encoding ASCII

Write-Step "Writing package notes"
$notesPath = Join-Path $OutputDir "README_PORTABLE.txt"
@'
FileCodeBox Portable
====================

1) Double click start.cmd
2) The app opens at http://127.0.0.1:18080/
3) First boot initializes MySQL and installs schema automatically

Default admin account:
- username: admin
- password: admin123456

Stop services:
- run stop.cmd
'@ | Set-Content -Path $notesPath -Encoding ASCII

Write-Step "Portable output generated at: $OutputDir"
