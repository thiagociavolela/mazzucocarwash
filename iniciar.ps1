$projectRoot=$PSScriptRoot
$phpBinary='C:/Sites/php82/php.exe'
if(!(Test-Path -LiteralPath $phpBinary)) { $phpBinary=(Get-Command php -ErrorAction Stop).Source }
Set-Location -LiteralPath $projectRoot
Write-Host 'Mazzuco CarWash: http://127.0.0.1:8088'
Write-Host 'Administracao: http://127.0.0.1:8088/admin'
& $phpBinary -S 127.0.0.1:8088 router.php
