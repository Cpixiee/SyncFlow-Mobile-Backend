# SyncFlow API - Windows Server Setup Script
# Version: 3.0.0 (Docker-first setup)
# Save this file with UTF-8 encoding (without BOM)

$ErrorActionPreference = "Stop"

# Configuration
$PROJECT_PATH = $PSScriptRoot
$PHP_INSTALL_DIR = "C:\php"
$COMPOSER_INSTALL_DIR = "C:\ProgramData\ComposerSetup"
$PHP_VERSION = "8.3.13"

# Check Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "ERROR: This script requires Administrator privileges!" -ForegroundColor Red
    Write-Host "Please run PowerShell as Administrator" -ForegroundColor Yellow
    pause
    exit 1
}

Write-Host ""
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "  SyncFlow API - Windows Setup Script v3.0.0 (Docker)" -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "Project: $PROJECT_PATH" -ForegroundColor Cyan
Write-Host ""

# Helper function to add to PATH
function AddToPath($newPath) {
    if (Test-Path $newPath) {
        $currentPath = [Environment]::GetEnvironmentVariable("Path", "Machine")
        if ($currentPath -notlike "*$newPath*") {
            [Environment]::SetEnvironmentVariable("Path", "$newPath;$currentPath", "Machine")
            $env:Path = "$newPath;$env:Path"
            Write-Host "Added to PATH: $newPath" -ForegroundColor Green
        }
    }
}

# STEP 1: Check/Install PHP
Write-Host ""
Write-Host "STEP 1: Checking PHP..." -ForegroundColor Magenta

$phpInstalled = $false
$phpExePath = "$PHP_INSTALL_DIR\php.exe"

if (Test-Path $phpExePath) {
    $ver = & $phpExePath -v 2>&1 | Out-String
    if ($ver -match 'PHP (\d+\.\d+)') {
        Write-Host "OK: PHP found at $PHP_INSTALL_DIR" -ForegroundColor Green
        $phpInstalled = $true
        AddToPath $PHP_INSTALL_DIR
        
        # Ensure sodium extension is enabled
        $ini = "$PHP_INSTALL_DIR\php.ini"
        if (Test-Path $ini) {
            $content = Get-Content $ini
            $hasSodium = $false
            $newContent = @()
            foreach ($line in $content) {
                if ($line -match '^\s*extension=sodium\s*$') {
                    $hasSodium = $true
                    $newContent += $line
                } elseif ($line -match '^\s*;extension=sodium\s*$') {
                    $newContent += $line -replace '^;', ''
                    $hasSodium = $true
                } else {
                    $newContent += $line
                }
            }
            if ($hasSodium) {
                $newContent | Set-Content $ini
                Write-Host "OK: Sodium extension enabled" -ForegroundColor Green
            }
        }
    }
}

if (-not $phpInstalled) {
    try {
        $ver = php -v 2>&1 | Out-String
        if ($ver -match 'PHP (\d+\.\d+)') {
            Write-Host "OK: PHP already in PATH" -ForegroundColor Green
            $phpInstalled = $true
            $phpExePath = (Get-Command php).Source
            $phpDir = Split-Path $phpExePath -Parent
            
            # Ensure sodium extension is enabled
            $ini = Join-Path $phpDir "php.ini"
            if (Test-Path $ini) {
                $content = Get-Content $ini
                $hasSodium = $false
                $newContent = @()
                foreach ($line in $content) {
                    if ($line -match '^\s*extension=sodium\s*$') {
                        $hasSodium = $true
                        $newContent += $line
                    } elseif ($line -match '^\s*;extension=sodium\s*$') {
                        $newContent += $line -replace '^;', ''
                        $hasSodium = $true
                    } else {
                        $newContent += $line
                    }
                }
                if ($hasSodium) {
                    $newContent | Set-Content $ini
                    Write-Host "OK: Sodium extension enabled" -ForegroundColor Green
                }
            }
        }
    } catch {}
}

if (-not $phpInstalled) {
    Write-Host "Installing PHP $PHP_VERSION..." -ForegroundColor Yellow
    
    if (-not (Test-Path $PHP_INSTALL_DIR)) {
        New-Item -ItemType Directory -Path $PHP_INSTALL_DIR -Force | Out-Null
    }
    
    $zipFile = "$env:TEMP\php.zip"
    
    # Try multiple URLs for PHP download
    $urls = @(
        "https://windows.php.net/downloads/releases/php-8.3.15-Win32-vs16-x64.zip",
        "https://windows.php.net/downloads/releases/php-8.3.14-Win32-vs16-x64.zip",
        "https://windows.php.net/downloads/releases/archives/php-8.3.13-Win32-vs16-x64.zip"
    )
    
    $downloadSuccess = $false
    foreach ($url in $urls) {
        try {
            Write-Host "Trying: $url" -ForegroundColor Yellow
            Invoke-WebRequest -Uri $url -OutFile $zipFile -UseBasicParsing
            $downloadSuccess = $true
            Write-Host "Download successful!" -ForegroundColor Green
            break
        } catch {
            Write-Host "Failed, trying next URL..." -ForegroundColor Yellow
        }
    }
    
    if (-not $downloadSuccess) {
        Write-Host "ERROR: Could not download PHP automatically" -ForegroundColor Red
        Write-Host "Please download PHP manually:" -ForegroundColor Yellow
        Write-Host "1. Visit: https://windows.php.net/download/" -ForegroundColor Cyan
        Write-Host "2. Download: PHP 8.3 x64 Thread Safe ZIP" -ForegroundColor Cyan
        Write-Host "3. Extract to: C:\php" -ForegroundColor Cyan
        Write-Host "4. Run this script again" -ForegroundColor Cyan
        exit 1
    }
    
    Write-Host "Extracting..." -ForegroundColor Yellow
    Expand-Archive -Path $zipFile -DestinationPath $PHP_INSTALL_DIR -Force
    Remove-Item $zipFile -Force
    
    # Configure php.ini
    $iniDev = "$PHP_INSTALL_DIR\php.ini-development"
    $ini = "$PHP_INSTALL_DIR\php.ini"
    
    if (Test-Path $iniDev) {
        Copy-Item $iniDev $ini -Force
        
        $content = Get-Content $ini
        $newContent = @()
        
        foreach ($line in $content) {
            if ($line -match '^\s*;extension=(mysqli|pdo_mysql|mbstring|openssl|curl|fileinfo|gd|zip|xml|sodium)\s*$') {
                $newContent += $line -replace '^;', ''
            } else {
                $newContent += $line
            }
        }
        
        $newContent | Set-Content $ini
    }
    
    AddToPath $PHP_INSTALL_DIR
    $phpExePath = "$PHP_INSTALL_DIR\php.exe"
    
    $ver = & $phpExePath -v 2>&1 | Out-String
    if ($ver -match 'PHP (\d+\.\d+)') {
        Write-Host "OK: PHP installed successfully" -ForegroundColor Green
    } else {
        Write-Host "ERROR: PHP installation failed" -ForegroundColor Red
        exit 1
    }
}

# STEP 2: Check/Install Composer
Write-Host ""
Write-Host "STEP 2: Checking Composer..." -ForegroundColor Magenta

$composerInstalled = $false
$composerCmd = $null

# Check if composer command exists
try {
    $ver = composer --version 2>&1 | Out-String
    if ($ver -match 'Composer') {
        Write-Host "OK: Composer already installed" -ForegroundColor Green
        $composerInstalled = $true
        $composerCmd = "composer"
    }
} catch {}

if (-not $composerInstalled) {
    Write-Host "Installing Composer..." -ForegroundColor Yellow
    
    if (-not (Test-Path $COMPOSER_INSTALL_DIR)) {
        New-Item -ItemType Directory -Path $COMPOSER_INSTALL_DIR -Force | Out-Null
    }
    
    $setup = "$env:TEMP\composer-setup.php"
    Invoke-WebRequest -Uri "https://getcomposer.org/installer" -OutFile $setup -UseBasicParsing
    
    & $phpExePath $setup --install-dir="$COMPOSER_INSTALL_DIR" --filename=composer.phar
    
    $phar = "$COMPOSER_INSTALL_DIR\composer.phar"
    $bat = "$COMPOSER_INSTALL_DIR\composer.bat"
    
    # Create batch wrapper
    "@echo off`r`n`"$phpExePath`" `"$phar`" %*" | Set-Content $bat -Encoding ASCII
    
    Remove-Item $setup -Force -ErrorAction SilentlyContinue
    
    # Verify installation
    if (Test-Path $phar) {
        Write-Host "OK: Composer installed at $phar" -ForegroundColor Green
        $composerInstalled = $true
        $composerCmd = "$phpExePath `"$phar`""
        
        # Add to PATH for future use
        AddToPath $COMPOSER_INSTALL_DIR
        
        # Verify it works (suppress non-critical output)
        try {
            $testVer = & $phpExePath $phar --version 2>&1 | Where-Object { $_ -match 'Composer' }
            if ($testVer) {
                Write-Host "Verified: Composer is working correctly" -ForegroundColor Green
            } else {
                Write-Host "Verified: Composer installed" -ForegroundColor Green
            }
        } catch {
            Write-Host "Verified: Composer installed" -ForegroundColor Green
        }
    } else {
        Write-Host "ERROR: Composer installation failed" -ForegroundColor Red
        Write-Host "Please install Composer manually from https://getcomposer.org" -ForegroundColor Yellow
        exit 1
    }
}

# STEP 3: Setup Docker and MySQL Container
Write-Host ""
Write-Host "STEP 3: Setting up Docker and MySQL..." -ForegroundColor Magenta

$dockerAvailable = $false
$dockerComposeFile = Join-Path $PROJECT_PATH "docker-compose.yml"

# Check if Docker is installed
try {
    $dockerVersion = docker --version 2>&1
    if ($dockerVersion -match 'Docker version') {
        Write-Host "OK: Docker detected" -ForegroundColor Green
        $dockerAvailable = $true
    }
} catch {}

# Auto-install Docker if not available
if (-not $dockerAvailable) {
    Write-Host "Docker not found. Installing Docker Desktop..." -ForegroundColor Yellow
    
    # Check if winget is available
    $wingetAvailable = $false
    try {
        $wingetVersion = winget --version 2>&1
        if ($wingetVersion) {
            $wingetAvailable = $true
        }
    } catch {}
    
    if ($wingetAvailable) {
        Write-Host "Installing Docker Desktop via winget..." -ForegroundColor Yellow
        Write-Host "This may take several minutes. Please wait..." -ForegroundColor Yellow
        
        try {
            winget install --id Docker.DockerDesktop --silent --accept-package-agreements --accept-source-agreements
            Write-Host "OK: Docker Desktop installed successfully" -ForegroundColor Green
            Write-Host ""
            Write-Host "IMPORTANT: Docker Desktop needs to be started manually for the first time" -ForegroundColor Yellow
            Write-Host ""
            
            # Try to find and start Docker Desktop
            $dockerPaths = @(
                "${env:ProgramFiles}\Docker\Docker\Docker Desktop.exe",
                "${env:ProgramFiles(x86)}\Docker\Docker\Docker Desktop.exe",
                "${env:LOCALAPPDATA}\Programs\Docker\Docker\Docker Desktop.exe"
            )
            
            $dockerExe = $null
            foreach ($path in $dockerPaths) {
                if (Test-Path $path) {
                    $dockerExe = $path
                    break
                }
            }
            
            if ($dockerExe) {
                Write-Host "Attempting to start Docker Desktop..." -ForegroundColor Yellow
                try {
                    Start-Process -FilePath $dockerExe -WindowStyle Minimized
                    Write-Host "Docker Desktop is starting..." -ForegroundColor Green
                    Write-Host "Please wait while Docker initializes (this may take 1-2 minutes)" -ForegroundColor Cyan
                } catch {
                    Write-Host "Could not auto-start Docker Desktop. Please start it manually." -ForegroundColor Yellow
                }
            } else {
                Write-Host "Please start Docker Desktop from Start Menu" -ForegroundColor Yellow
            }
            
            Write-Host ""
            Write-Host "Waiting for Docker Desktop to be ready..." -ForegroundColor Yellow
            Write-Host "(Docker Desktop will show a system tray icon when ready)" -ForegroundColor Gray
            
            # Wait for Docker to be ready (max 3 minutes)
            $maxWait = 180
            $waited = 0
            $dockerReady = $false
            
            while ($waited -lt $maxWait) {
                Start-Sleep -Seconds 5
                $waited += 5
                
                # Check if Docker command is available and daemon is running
                try {
                    $dockerInfo = docker info 2>&1
                    if ($dockerInfo -notmatch 'Cannot connect' -and $dockerInfo -notmatch 'error' -and $dockerInfo -notmatch 'ERROR') {
                        Write-Host "OK: Docker Desktop is ready!" -ForegroundColor Green
                        $dockerAvailable = $true
                        $dockerReady = $true
                        break
                    }
                } catch {}
                
                # Show progress every 15 seconds
                if ($waited % 15 -eq 0) {
                    $minutes = [math]::Floor($waited / 60)
                    $seconds = $waited % 60
                    Write-Host "Still waiting... ($minutes min $seconds sec)" -ForegroundColor Yellow
                    if ($waited -ge 60) {
                        Write-Host "Tip: Check if Docker Desktop icon appears in system tray" -ForegroundColor Cyan
                    }
                }
            }
            
            if (-not $dockerReady) {
                Write-Host ""
                Write-Host "TIMEOUT: Docker Desktop is taking longer than expected" -ForegroundColor Yellow
                Write-Host ""
                
                # Check for virtualization error
                $dockerError = $false
                try {
                    # Try to detect virtualization error from Docker logs or process
                    $dockerProcess = Get-Process "Docker Desktop" -ErrorAction SilentlyContinue
                    if ($dockerProcess) {
                        Write-Host "Docker Desktop is running but daemon may not be ready" -ForegroundColor Yellow
                    }
                } catch {}
                
                Write-Host "Possible issues:" -ForegroundColor Cyan
                Write-Host "1. Virtualization not enabled in BIOS/UEFI" -ForegroundColor White
                Write-Host "2. Hyper-V not enabled or not available" -ForegroundColor White
                Write-Host "3. Docker Desktop needs manual configuration" -ForegroundColor White
                Write-Host ""
                Write-Host "Options:" -ForegroundColor Cyan
                Write-Host "A. Enable virtualization and use Docker (Recommended)" -ForegroundColor White
                Write-Host "B. Use local MySQL instead (Skip Docker)" -ForegroundColor White
                Write-Host ""
                $choice = Read-Host "Choose option (A/B) or (y) to continue waiting for Docker"
                
                if ($choice -eq "B" -or $choice -eq "b") {
                    Write-Host ""
                    Write-Host "Switching to local MySQL setup..." -ForegroundColor Yellow
                    Write-Host "Please ensure MySQL is installed and running" -ForegroundColor Cyan
                    $dockerAvailable = $false
                    $useDocker = $false
                } elseif ($choice -eq "y" -or $choice -eq "Y") {
                    Write-Host "Continuing to wait for Docker..." -ForegroundColor Yellow
                    # Continue waiting or check one more time
                    try {
                        $dockerInfo = docker info 2>&1
                        if ($dockerInfo -match 'Cannot connect' -or $dockerInfo -match 'error' -or $dockerInfo -match 'ERROR' -or $dockerInfo -match 'virtualization') {
                            Write-Host ""
                            Write-Host "ERROR: Docker requires virtualization support" -ForegroundColor Red
                            Write-Host ""
                            Write-Host "To enable virtualization:" -ForegroundColor Cyan
                            Write-Host "1. Restart computer and enter BIOS/UEFI settings" -ForegroundColor White
                            Write-Host "2. Enable 'Virtualization Technology' or 'Intel VT-x' / 'AMD-V'" -ForegroundColor White
                            Write-Host "3. Enable 'Hyper-V' in Windows Features (if available)" -ForegroundColor White
                            Write-Host "4. Restart and try Docker Desktop again" -ForegroundColor White
                            Write-Host ""
                            Write-Host "Alternatively, use option B to use local MySQL" -ForegroundColor Yellow
                            $continue = Read-Host "Continue with local MySQL? (y/n)"
                            if ($continue -ne "y") {
                                exit 1
                            }
                            $dockerAvailable = $false
                            $useDocker = $false
                        } else {
                            Write-Host "OK: Docker is now ready" -ForegroundColor Green
                            $dockerAvailable = $true
                        }
                    } catch {
                        Write-Host "ERROR: Cannot verify Docker" -ForegroundColor Red
                        $continue = Read-Host "Continue with local MySQL? (y/n)"
                        if ($continue -ne "y") {
                            exit 1
                        }
                        $dockerAvailable = $false
                        $useDocker = $false
                    }
                } else {
                    Write-Host "Please enable virtualization and restart Docker Desktop" -ForegroundColor Yellow
                    exit 1
                }
            }
        } catch {
            Write-Host "ERROR: Could not install Docker via winget" -ForegroundColor Red
            Write-Host "Please install Docker Desktop manually:" -ForegroundColor Yellow
            Write-Host "1. Download from: https://www.docker.com/products/docker-desktop" -ForegroundColor Cyan
            Write-Host "2. Install and start Docker Desktop" -ForegroundColor Cyan
            Write-Host "3. Run this script again" -ForegroundColor Cyan
            exit 1
        }
    } else {
        Write-Host "ERROR: winget not available. Please install Docker Desktop manually:" -ForegroundColor Red
        Write-Host "1. Download from: https://www.docker.com/products/docker-desktop" -ForegroundColor Cyan
        Write-Host "2. Install and start Docker Desktop" -ForegroundColor Cyan
        Write-Host "3. Run this script again" -ForegroundColor Cyan
        exit 1
    }
}

# Verify Docker daemon is running
if ($dockerAvailable) {
    Write-Host ""
    Write-Host "Verifying Docker daemon..." -ForegroundColor Cyan
    $maxRetries = 6
    $retryCount = 0
    $dockerRunning = $false
    
    while ($retryCount -lt $maxRetries) {
        try {
            $dockerInfo = docker info 2>&1 | Out-String
            if ($dockerInfo -notmatch 'Cannot connect' -and $dockerInfo -notmatch 'error' -and $dockerInfo -notmatch 'ERROR') {
                $dockerRunning = $true
                Write-Host "OK: Docker daemon is running" -ForegroundColor Green
                break
            }
        } catch {}
        
        if (-not $dockerRunning) {
            $retryCount++
            if ($retryCount -lt $maxRetries) {
                Write-Host "Waiting for Docker daemon... ($retryCount/$maxRetries)" -ForegroundColor Yellow
                Start-Sleep -Seconds 5
            }
        }
    }
    
    if (-not $dockerRunning) {
        Write-Host ""
        Write-Host "WARNING: Docker Desktop is installed but the daemon is not running" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "This might be due to virtualization not being enabled." -ForegroundColor Cyan
        Write-Host ""
        Write-Host "Options:" -ForegroundColor Cyan
        Write-Host "A. Enable virtualization and restart Docker Desktop" -ForegroundColor White
        Write-Host "B. Use local MySQL instead (Skip Docker)" -ForegroundColor White
        Write-Host ""
        $choice = Read-Host "Choose option (A/B)"
        
        if ($choice -eq "B" -or $choice -eq "b") {
            Write-Host "Switching to local MySQL setup..." -ForegroundColor Yellow
            $dockerAvailable = $false
            $useDocker = $false
        } else {
            Write-Host "Please enable virtualization in BIOS and restart Docker Desktop" -ForegroundColor Yellow
            Write-Host "Then run this script again" -ForegroundColor Yellow
            exit 1
        }
    }
}

# Setup MySQL: Docker or Local
$useDocker = $dockerAvailable

if ($useDocker) {
    # Check if docker-compose.yml exists
    if (-not (Test-Path $dockerComposeFile)) {
        Write-Host "ERROR: docker-compose.yml not found!" -ForegroundColor Red
        Write-Host "Please ensure docker-compose.yml exists in the project directory" -ForegroundColor Yellow
        Write-Host "Switching to local MySQL..." -ForegroundColor Yellow
        $useDocker = $false
    } else {
        # Start Docker containers
        Write-Host ""
        Write-Host "Starting Docker containers..." -ForegroundColor Yellow
        Set-Location $PROJECT_PATH

        try {
            # Start MySQL container first
            Write-Host "Starting MySQL container..." -ForegroundColor Cyan
            docker-compose up -d syncflow-db 2>&1 | Out-Null
            
            Write-Host "Waiting for MySQL container to be ready..." -ForegroundColor Yellow
            Start-Sleep -Seconds 10
            
            # Wait for MySQL to be ready (max 90 seconds)
            $maxWait = 90
            $waited = 0
            $mysqlReady = $false
            
            while ($waited -lt $maxWait) {
                try {
                    $containerStatus = docker ps --filter "name=syncflow-mysql" --format "{{.Status}}" 2>&1
                    if ($containerStatus -match 'Up' -and ($containerStatus -match 'healthy' -or $containerStatus -match 'starting')) {
                        # Test MySQL connection
                        $testConnection = docker exec syncflow-mysql mysqladmin ping -h localhost -u root -pRootSyncFlow2024# 2>&1
                        if ($testConnection -match 'mysqld is alive') {
                            Write-Host "OK: MySQL container is running and ready" -ForegroundColor Green
                            $mysqlReady = $true
                            break
                        }
                    }
                } catch {}
                
                Start-Sleep -Seconds 5
                $waited += 5
                
                if ($waited % 15 -eq 0) {
                    Write-Host "Waiting for MySQL... ($waited/$maxWait seconds)" -ForegroundColor Yellow
                }
            }
            
            if (-not $mysqlReady) {
                Write-Host "WARNING: MySQL container may not be fully ready yet" -ForegroundColor Yellow
                Write-Host "Continuing anyway. Migrations will be attempted later." -ForegroundColor Yellow
            }
            
            Write-Host "OK: Docker containers started" -ForegroundColor Green
        } catch {
            Write-Host "ERROR: Could not start Docker containers: $_" -ForegroundColor Red
            Write-Host "Switching to local MySQL setup..." -ForegroundColor Yellow
            $useDocker = $false
            $dockerAvailable = $false
        }
    }
} else {
    # Use Local MySQL
    Write-Host ""
    Write-Host "Using local MySQL instead of Docker" -ForegroundColor Yellow
    Write-Host ""
    
    $mysqlInstalled = $false
    
    # Check for MySQL command
    try {
        $ver = mysql --version 2>&1 | Out-String
        if ($ver -match 'mysql') {
            Write-Host "OK: MySQL command found" -ForegroundColor Green
            $mysqlInstalled = $true
        }
    } catch {}
    
    # Check for MySQL service
    if (-not $mysqlInstalled) {
        $services = Get-Service -ErrorAction SilentlyContinue | Where-Object { $_.Name -like "*mysql*" }
        if ($services) {
            Write-Host "OK: MySQL service detected: $($services[0].Name)" -ForegroundColor Green
            $mysqlInstalled = $true
            
            # Try to start MySQL service if not running
            $mysqlService = $services[0]
            if ($mysqlService.Status -ne 'Running') {
                Write-Host "Starting MySQL service..." -ForegroundColor Yellow
                try {
                    Start-Service -Name $mysqlService.Name
                    Start-Sleep -Seconds 5
                    Write-Host "OK: MySQL service started" -ForegroundColor Green
                } catch {
                    Write-Host "WARNING: Could not start MySQL service automatically" -ForegroundColor Yellow
                    Write-Host "Please start MySQL service manually" -ForegroundColor Yellow
                }
            }
        }
    }
    
    if (-not $mysqlInstalled) {
        Write-Host "WARNING: MySQL not detected on this system" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "Please install MySQL manually:" -ForegroundColor Cyan
        Write-Host "1. Download from: https://dev.mysql.com/downloads/installer/" -ForegroundColor White
        Write-Host "2. Or use winget: winget install Oracle.MySQL" -ForegroundColor White
        Write-Host "3. Create database 'syncflow' manually" -ForegroundColor White
        Write-Host "4. Run this script again" -ForegroundColor White
        Write-Host ""
        $continue = Read-Host "Continue anyway? (Make sure MySQL is running and database exists) (y/n)"
        if ($continue -ne "y") {
            exit 1
        }
    } else {
        Write-Host "OK: MySQL is ready for use" -ForegroundColor Green
    }
}

# STEP 4: Setup .env
Write-Host ""
Write-Host "STEP 4: Configuring environment..." -ForegroundColor Magenta

Set-Location $PROJECT_PATH

if (Test-Path ".env") {
    $backup = ".env.backup_$(Get-Date -Format 'yyyyMMddHHmmss')"
    Copy-Item ".env" $backup
    Write-Host "Backed up .env to $backup" -ForegroundColor Yellow
}

if (-not (Test-Path ".env")) {
    if (Test-Path ".env.example") {
        Copy-Item ".env.example" ".env"
        Write-Host "Created .env from .env.example" -ForegroundColor Green
    } else {
        Write-Host "ERROR: .env.example not found" -ForegroundColor Red
        exit 1
    }
}

# Auto-configure MySQL settings (Docker or Local)
if ($useDocker) {
    Write-Host "Configuring .env for Docker MySQL..." -ForegroundColor Yellow
    $dbHost = "127.0.0.1"
    $dbPort = "33061"
    $dbUser = "syncflow_user"
    $dbPass = "SyncFlow2024#Secure"
} else {
    Write-Host "Configuring .env for local MySQL..." -ForegroundColor Yellow
    $dbHost = "127.0.0.1"
    $dbPort = "3306"
    $dbUser = "root"
    $dbPass = ""
}

$envContent = Get-Content ".env"
$newEnvContent = @()

foreach ($line in $envContent) {
    if ($line -match '^DB_CONNECTION=') {
        $newEnvContent += "DB_CONNECTION=mysql"
    } elseif ($line -match '^DB_HOST=') {
        $newEnvContent += "DB_HOST=$dbHost"
    } elseif ($line -match '^DB_PORT=') {
        $newEnvContent += "DB_PORT=$dbPort"
    } elseif ($line -match '^DB_DATABASE=') {
        $newEnvContent += "DB_DATABASE=syncflow"
    } elseif ($line -match '^DB_USERNAME=') {
        $newEnvContent += "DB_USERNAME=$dbUser"
    } elseif ($line -match '^DB_PASSWORD=') {
        $newEnvContent += "DB_PASSWORD=$dbPass"
    } else {
        $newEnvContent += $line
    }
}

$newEnvContent | Set-Content ".env" -Encoding UTF8

if ($useDocker) {
    Write-Host "OK: .env configured for Docker MySQL" -ForegroundColor Green
    Write-Host "   DB_HOST=$dbHost, DB_PORT=$dbPort" -ForegroundColor Cyan
    Write-Host "   DB_DATABASE=syncflow, DB_USERNAME=$dbUser" -ForegroundColor Cyan
} else {
    Write-Host "OK: .env configured for local MySQL" -ForegroundColor Green
    Write-Host "   DB_HOST=$dbHost, DB_PORT=$dbPort" -ForegroundColor Cyan
    Write-Host "   DB_DATABASE=syncflow, DB_USERNAME=$dbUser" -ForegroundColor Cyan
    Write-Host "   NOTE: Please set DB_PASSWORD in .env if MySQL requires password" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Opening .env for review (optional)..." -ForegroundColor Yellow
Write-Host "Press Enter to continue, or edit .env if needed..." -ForegroundColor Cyan
Start-Process notepad.exe -ArgumentList ".env"
Start-Sleep -Seconds 2
$continue = Read-Host "Press Enter after reviewing .env to continue"

# STEP 5: Install dependencies
Write-Host ""
Write-Host "STEP 5: Installing dependencies..." -ForegroundColor Magenta

# Determine how to run composer
if ($composerCmd -eq "composer") {
    Write-Host "Using composer command" -ForegroundColor Cyan
    try {
        composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | Out-Null
        if ($LASTEXITCODE -eq 0) {
            Write-Host "OK: Dependencies installed" -ForegroundColor Green
        } else {
            throw "Composer install failed"
        }
    } catch {
        Write-Host "Trying with --ignore-platform-req=ext-sodium..." -ForegroundColor Yellow
        composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=ext-sodium 2>&1 | Out-Null
        Write-Host "OK: Dependencies installed (sodium requirement ignored)" -ForegroundColor Green
    }
    composer dump-autoload --optimize
} else {
    # Use php composer.phar directly
    $pharPath = "$COMPOSER_INSTALL_DIR\composer.phar"
    Write-Host "Using: php $pharPath" -ForegroundColor Cyan
    
    try {
        & $phpExePath $pharPath install --no-dev --optimize-autoloader --no-interaction 2>&1 | Out-Null
        if ($LASTEXITCODE -eq 0) {
            Write-Host "OK: Dependencies installed" -ForegroundColor Green
        } else {
            throw "Composer install failed"
        }
    } catch {
        Write-Host "Trying with --ignore-platform-req=ext-sodium..." -ForegroundColor Yellow
        & $phpExePath $pharPath install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=ext-sodium 2>&1 | Out-Null
        Write-Host "OK: Dependencies installed (sodium requirement ignored)" -ForegroundColor Green
    }
    
    & $phpExePath $pharPath dump-autoload --optimize
}

# STEP 6: Generate keys
Write-Host ""
Write-Host "STEP 6: Generating keys..." -ForegroundColor Magenta

& $phpExePath artisan key:generate --force
Write-Host "OK: App key generated" -ForegroundColor Green

try {
    & $phpExePath artisan jwt:secret --force
    Write-Host "OK: JWT secret generated" -ForegroundColor Green
} catch {
    Write-Host "SKIP: JWT secret" -ForegroundColor Yellow
}

# STEP 7: Clear caches
Write-Host ""
Write-Host "STEP 7: Clearing caches..." -ForegroundColor Magenta

try {
    & $phpExePath artisan config:clear 2>&1 | Out-Null
    Write-Host "OK: Config cache cleared" -ForegroundColor Green
} catch {
    Write-Host "SKIP: Config cache (may require DB connection)" -ForegroundColor Yellow
}

try {
    & $phpExePath artisan cache:clear 2>&1 | Out-Null
    Write-Host "OK: Application cache cleared" -ForegroundColor Green
} catch {
    Write-Host "SKIP: Application cache (may require DB connection)" -ForegroundColor Yellow
}

try {
    & $phpExePath artisan route:clear 2>&1 | Out-Null
    Write-Host "OK: Route cache cleared" -ForegroundColor Green
} catch {
    Write-Host "SKIP: Route cache" -ForegroundColor Yellow
}

try {
    & $phpExePath artisan view:clear 2>&1 | Out-Null
    Write-Host "OK: View cache cleared" -ForegroundColor Green
} catch {
    Write-Host "SKIP: View cache" -ForegroundColor Yellow
}

try {
    & $phpExePath artisan package:discover --ansi 2>&1 | Out-Null
    Write-Host "OK: Package discovery completed" -ForegroundColor Green
} catch {
    Write-Host "SKIP: Package discovery" -ForegroundColor Yellow
}

# STEP 8: Run migrations
Write-Host ""
Write-Host "STEP 8: Database migration..." -ForegroundColor Magenta
Write-Host "Make sure MySQL is running and database exists" -ForegroundColor Yellow
pause

try {
    & $phpExePath artisan migrate --force
    Write-Host "OK: Migrations completed" -ForegroundColor Green
} catch {
    Write-Host "ERROR: Migration failed" -ForegroundColor Red
    Write-Host "Check database connection in .env" -ForegroundColor Yellow
    
    $retry = Read-Host "Edit .env and retry? (y/n)"
    if ($retry -eq "y") {
        Start-Process notepad.exe -ArgumentList ".env" -Wait
        & $phpExePath artisan migrate --force
    } else {
        exit 1
    }
}

# STEP 9: Seed database
Write-Host ""
Write-Host "STEP 9: Seeding database..." -ForegroundColor Magenta

$seeders = @(
    "QuarterSeeder",
    "ProductCategorySeeder",
    "MeasurementInstrumentSeeder",
    "ToolSeeder",
    "SuperAdminSeeder",
    "LoginUserSeeder"
)

foreach ($seeder in $seeders) {
    try {
        & $phpExePath artisan db:seed --class=$seeder --force 2>&1 | Out-Null
        Write-Host "OK: $seeder" -ForegroundColor Green
    } catch {
        Write-Host "SKIP: $seeder" -ForegroundColor Yellow
    }
}

# STEP 10: Activate quarter
Write-Host ""
Write-Host "STEP 10: Activating quarter..." -ForegroundColor Magenta

# Use absolute path for the project directory
$projectDir = $PROJECT_PATH.Replace('\', '/')
$script = @"
<?php
chdir('$projectDir');
require '$projectDir/vendor/autoload.php';
`$app = require_once '$projectDir/bootstrap/app.php';
`$kernel = `$app->make(Illuminate\Contracts\Console\Kernel::class);
`$kernel->bootstrap();
`$quarter = App\Models\Quarter::where('year', 2024)->where('name', 'Q4')->first();
if (`$quarter) {
    `$quarter->setAsActive();
    echo 'Q4 2024 activated';
} else {
    `$first = App\Models\Quarter::first();
    if (`$first) {
        `$first->setAsActive();
        echo 'Activated: ' . `$first->year . ' ' . `$first->name;
    } else {
        echo 'No quarters found';
    }
}
"@

$tempFile = "$env:TEMP\activate.php"
$script | Out-File -FilePath $tempFile -Encoding UTF8

try {
    $result = & $phpExePath $tempFile 2>&1 | Where-Object { $_ -notmatch 'PHP Warning' -and $_ -notmatch 'Failed to open' }
    if ($result) {
        Write-Host "OK: $result" -ForegroundColor Green
    } else {
        Write-Host "SKIP: Quarter activation (may need to run manually)" -ForegroundColor Yellow
    }
} catch {
    Write-Host "SKIP: Quarter activation" -ForegroundColor Yellow
}

Remove-Item $tempFile -Force -ErrorAction SilentlyContinue

# STEP 11: Storage setup
Write-Host ""
Write-Host "STEP 11: Storage setup..." -ForegroundColor Magenta

try {
    & $phpExePath artisan storage:link 2>&1 | Out-Null
    Write-Host "OK: Storage linked" -ForegroundColor Green
} catch {}

$dirs = @(
    "storage\app\private\reports\master_files",
    "storage\app\temp",
    "storage\framework\cache\data",
    "storage\framework\sessions",
    "storage\framework\views",
    "storage\logs"
)

foreach ($dir in $dirs) {
    $fullPath = Join-Path $PROJECT_PATH $dir
    if (-not (Test-Path $fullPath)) {
        New-Item -ItemType Directory -Path $fullPath -Force | Out-Null
    }
}

Write-Host "OK: Directories created" -ForegroundColor Green

# STEP 12: Optimization
Write-Host ""
Write-Host "STEP 12: Optimization..." -ForegroundColor Magenta

$isProd = Read-Host "Production environment? (y/n)"

if ($isProd -eq "y") {
    $envContent = Get-Content ".env"
    $envContent = $envContent -replace '^APP_DEBUG=.*', 'APP_DEBUG=false'
    $envContent = $envContent -replace '^APP_ENV=.*', 'APP_ENV=production'
    $envContent | Set-Content ".env"
    
    & $phpExePath artisan config:cache
    & $phpExePath artisan route:cache
    & $phpExePath artisan view:cache
    
    Write-Host "OK: Production optimized" -ForegroundColor Green
} else {
    Write-Host "SKIP: Development mode" -ForegroundColor Yellow
}

# Summary
Write-Host ""
Write-Host "========================================================" -ForegroundColor Green
Write-Host "  Setup Completed Successfully!" -ForegroundColor Green
Write-Host "========================================================" -ForegroundColor Green
Write-Host ""

Write-Host "Docker Containers Status:" -ForegroundColor Cyan
try {
    $containers = docker ps --filter "name=syncflow" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" 2>&1
    Write-Host $containers -ForegroundColor White
} catch {
    Write-Host "Use 'docker ps' to check container status" -ForegroundColor Yellow
}
Write-Host ""

Write-Host "Next Steps:" -ForegroundColor Cyan
Write-Host "1. MySQL is running in Docker (Port 33061)" -ForegroundColor White
Write-Host "2. Start Laravel app:" -ForegroundColor White
Write-Host "   Option A (Docker): docker-compose up -d syncflow-app" -ForegroundColor Cyan
Write-Host "      API URL: http://localhost:2020/api/v1" -ForegroundColor Cyan
Write-Host "   Option B (PHP): php artisan serve" -ForegroundColor Cyan
Write-Host "      API URL: http://localhost:8000/api/v1" -ForegroundColor Cyan
Write-Host "3. phpMyAdmin: http://localhost:8081" -ForegroundColor White
Write-Host "   Login: root / RootSyncFlow2024#" -ForegroundColor Gray
Write-Host "4. Default API login:" -ForegroundColor White
Write-Host "   Username: superadmin" -ForegroundColor White
Write-Host "   Password: admin#1234" -ForegroundColor White
Write-Host ""
Write-Host "Docker Commands:" -ForegroundColor Cyan
Write-Host "  Start all: docker-compose up -d" -ForegroundColor Gray
Write-Host "  Stop all: docker-compose down" -ForegroundColor Gray
Write-Host "  View logs: docker-compose logs -f" -ForegroundColor Gray
Write-Host "  MySQL console: docker exec -it syncflow-mysql mysql -u root -p" -ForegroundColor Gray
Write-Host ""
Write-Host "IMPORTANT:" -ForegroundColor Yellow
Write-Host "- Change default passwords" -ForegroundColor White
Write-Host "- Configure SSL for production" -ForegroundColor White
Write-Host "- Review .env security settings" -ForegroundColor White
Write-Host "- Keep Docker Desktop running for MySQL" -ForegroundColor White
Write-Host ""

$start = Read-Host "Start PHP development server now? (y/n)"
if ($start -eq "y") {
    Write-Host "Starting server at http://localhost:8000..." -ForegroundColor Cyan
    Write-Host "Press Ctrl+C to stop" -ForegroundColor Yellow
    Write-Host "Note: MySQL is running in Docker container" -ForegroundColor Gray
    & $phpExePath artisan serve
}