# Pakuje katalog do ZIP-a z separatorem "/" w nazwach wpisow.
#
# Compress-Archive w Windows PowerShell 5.1 zapisuje separatory jako "\", co
# lamie specyfikacje ZIP (APPNOTE 4.4.17 wymaga ukosnika). Windows rozpakuje
# takie archiwum poprawnie, ale unzip na Linuksie potraktuje backslash jako
# czesc nazwy pliku i zamiast drzewa katalogow wysypie do jednego folderu
# pliki nazwane "winnica-nowizny\inc\seo.php". Na serwerze konczy sie to
# motywem, ktorego WordPress nie widzi.
#
# Dlatego wpisy tworzymy recznie, zamiast ufac Compress-Archive.

param(
    [Parameter(Mandatory = $true)][string]$SourceDir,
    [Parameter(Mandatory = $true)][string]$DestinationZip
)

$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$source = (Resolve-Path -LiteralPath $SourceDir).Path.TrimEnd('\')
$rootName = Split-Path -Leaf $source

if (Test-Path -LiteralPath $DestinationZip) {
    Remove-Item -LiteralPath $DestinationZip -Force
}

$archive = [System.IO.Compression.ZipFile]::Open($DestinationZip, 'Create')
try {
    $files = Get-ChildItem -LiteralPath $source -Recurse -File -Force
    foreach ($file in $files) {
        # Sciezka wzgledem katalogu nadrzednego, zeby w archiwum zostal jeden
        # katalog glowny, a nie luzne pliki.
        $relative = $file.FullName.Substring($source.Length).TrimStart('\')
        $entryName = "$rootName/" + ($relative -replace '\\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive, $file.FullName, $entryName, 'Optimal') | Out-Null
    }
    Write-Output "spakowano wpisow: $($files.Count)"
}
finally {
    $archive.Dispose()
}
