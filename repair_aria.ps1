param(
    [string]$DataDir = 'D:\D_Work\b213\xampp\mysql\data',
    [string]$AriaChk = 'D:\D_Work\b213\xampp\mysql\bin\aria_chk.exe'
)

$ts = Get-Date -Format 'yyyyMMdd_HHmm'
$backupLogs = Join-Path $DataDir "aria_logs_old_$ts"
New-Item -ItemType Directory -Path $backupLogs -Force | Out-Null

Write-Host "Backup logs directory: $backupLogs"

# 1) Stop mysqld processes if any
Write-Host "Stopping mysqld processes (if running)..."
$procs = Get-Process -Name mysqld -ErrorAction SilentlyContinue
if ($procs) {
    foreach ($p in $procs) {
        try {
            Stop-Process -Id $p.Id -Force -ErrorAction Stop
            Write-Host "Stopped mysqld pid $($p.Id)"
        } catch {
            Write-Host "Failed to stop pid $($p.Id): $_"
        }
    }
} else {
    Write-Host "No mysqld process found."
}

# 2) List MAI/MAD files
Write-Host "Scanning for .MAI/.MAD files under $DataDir ..."
$maiFiles = Get-ChildItem -Path $DataDir -Recurse -Include '*.MAI','*.MAD' -ErrorAction SilentlyContinue
if ($maiFiles -and $maiFiles.Count -gt 0) {
    Write-Host "Found MAI/MAD files:"
    $maiFiles | Select-Object FullName,Length | Format-Table -AutoSize
} else {
    Write-Host "No .MAI/.MAD files found."
}

# 3) Run aria_chk -r on each MAI (only on .MAI entries)
if (Test-Path $AriaChk) {
    $maiOnly = $maiFiles | Where-Object { $_.Extension -ieq '.MAI' }
    if ($maiOnly -and $maiOnly.Count -gt 0) {
        foreach ($f in $maiOnly) {
            $base = Join-Path $f.DirectoryName $f.BaseName
            Write-Host "Running aria_chk -r on: $base"
            try {
                & $AriaChk -r --datadir=$DataDir $base 2>&1 | Tee-Object -FilePath (Join-Path $backupLogs ($f.BaseName + '.aria_chk.log'))
            } catch {
                Write-Host "aria_chk failed for $base: $_"
            }
        }
    } else {
        Write-Host "No .MAI files to repair."
    }
} else {
    Write-Host "aria_chk not found at $AriaChk"
}

# 4) Move aria_log files to backup folder
Write-Host "Moving aria_log files to $backupLogs"
Get-ChildItem -Path $DataDir -Filter 'aria_log*' -ErrorAction SilentlyContinue | ForEach-Object {
    try {
        Move-Item -Path $_.FullName -Destination $backupLogs -Force
        Write-Host "Moved $($_.Name)"
    } catch {
        Write-Host "Failed to move $($_.Name): $_"
    }
}

# 5) Attempt to start mysql service
Write-Host "Attempting to start MySQL service..."
try {
    net start mysql | Write-Host
} catch {
    Write-Host "Failed to start mysql service: $_"
}

Start-Sleep -Seconds 3
Write-Host '--- last 200 lines of mysql_error.log ---'
if (Test-Path (Join-Path $DataDir 'mysql_error.log')) {
    Get-Content -Path (Join-Path $DataDir 'mysql_error.log') -Tail 200 -ErrorAction SilentlyContinue | ForEach-Object { Write-Host $_ }
} else {
    Write-Host 'mysql_error.log not found.'
}

Write-Host 'repair_aria.ps1 finished.'
