param(
    [string]$MySqlDump = "",
    [string]$Database = "befit_app",
    [string]$HostName = "127.0.0.1",
    [int]$Port = 3306,
    [string]$User = "root",
    [string]$Password = "",
    [string]$OutputDirectory = "$PSScriptRoot\..\storage\backups",
    [int]$RetentionDays = 30
)

if ([string]::IsNullOrWhiteSpace($MySqlDump)) {
    $candidate = Get-ChildItem "C:\laragon\bin\mysql" -Filter "mysqldump.exe" -Recurse -ErrorAction SilentlyContinue |
        Select-Object -First 1
    if ($candidate) { $MySqlDump = $candidate.FullName }
}

if ([string]::IsNullOrWhiteSpace($MySqlDump) -or -not (Test-Path $MySqlDump)) {
    throw "mysqldump.exe was not found. Pass -MySqlDump with its full Laragon/MySQL path."
}

New-Item -ItemType Directory -Force -Path $OutputDirectory | Out-Null
$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$out = Join-Path $OutputDirectory "$Database-$stamp.sql"

$args = @(
    "--single-transaction",
    "--routines",
    "--triggers",
    "--host=$HostName",
    "--port=$Port",
    "--user=$User"
)
if ($Password -ne "") { $args += "--password=$Password" }
$args += $Database

# cmd.exe redirection preserves mysqldump bytes without PowerShell text re-encoding.
$quotedArgs = ($args | ForEach-Object { '"' + ($_ -replace '"','\"') + '"' }) -join ' '
$command = '"' + $MySqlDump + '" ' + $quotedArgs + ' > "' + $out + '"'
cmd.exe /d /s /c $command
if ($LASTEXITCODE -ne 0) {
    if (Test-Path $out) { Remove-Item -Force $out }
    throw "mysqldump failed with exit code $LASTEXITCODE"
}

if ($RetentionDays -gt 0) {
    Get-ChildItem $OutputDirectory -Filter "$Database-*.sql" |
        Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$RetentionDays) } |
        Remove-Item -Force
}

Write-Host "Backup created: $out"
