# UniPark · Genera dist/UniPark.zip listo para copiar a C:\xampp\htdocs
# Uso (PowerShell, desde la raíz del proyecto):
#   powershell -ExecutionPolicy Bypass -File scripts\empaquetar.ps1
#
# El ZIP contiene la carpeta UniPark/ sin configuración local, sin credenciales,
# sin logs ni respaldos y sin el bloqueo de instalación: al abrirlo en el
# navegador se muestra el instalador.

$ErrorActionPreference = 'Stop'
$raiz = Split-Path -Parent $PSScriptRoot
$dist = Join-Path $raiz 'dist'
$temporal = Join-Path ([System.IO.Path]::GetTempPath()) ('UniPark_build_' + [guid]::NewGuid().ToString('N'))
$destino = Join-Path $temporal 'UniPark'

New-Item -ItemType Directory -Force -Path $destino | Out-Null
New-Item -ItemType Directory -Force -Path $dist | Out-Null

# Copiar el proyecto sin carpetas de desarrollo.
robocopy $raiz $destino /E /NFL /NDL /NJH /NJS /NP /XD .git .claude dist vendor node_modules | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy fallo ($LASTEXITCODE)" }

# Quitar configuración local, credenciales y archivos generados.
$quitar = @('.env', 'config\env.php', 'storage\installed.lock', 'storage\push')
foreach ($f in $quitar) {
    $ruta = Join-Path $destino $f
    # storage\push guarda las claves VAPID de esta instalación: cada servidor genera las suyas.
    if (Test-Path $ruta) { Remove-Item -Recurse -Force $ruta }
}
foreach ($dir in @('storage\logs', 'storage\backups', 'storage\receipts')) {
    Get-ChildItem -Path (Join-Path $destino $dir) -File -Force |
        Where-Object { $_.Name -ne '.gitkeep' } |
        Remove-Item -Force
}

$zip = Join-Path $dist 'UniPark.zip'
if (Test-Path $zip) { Remove-Item -Force $zip }

# tar.exe (incluido en Windows 10+) genera rutas con "/" compatibles con cualquier descompresor.
$tar = Join-Path $env:SystemRoot 'System32\tar.exe'
& $tar -a -c -f $zip -C $temporal UniPark
if ($LASTEXITCODE -ne 0) { throw "tar fallo ($LASTEXITCODE)" }

Remove-Item -Recurse -Force $temporal
$tamano = [math]::Round((Get-Item $zip).Length / 1MB, 2)
Write-Host "Paquete listo: $zip ($tamano MB)"
