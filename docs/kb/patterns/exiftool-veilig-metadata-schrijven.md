---
title: Exiftool — veilig metadata schrijven naar gebruikersbestanden
type: pattern
scope: havuncore
last_check: 2026-07-15
---

# Exiftool — veilig metadata schrijven naar gebruikersbestanden

> **Herkomst:** Vusista (fotoalbum-desktop-app), 15 juli 2026.
> Toepasbaar op elk project dat metadata naar bestanden van de gebruiker schrijft.

## Probleem

Metadata (tags, bijschriften, rating, datum, GPS) terugschrijven naar foto's/video's van
de gebruiker is riskant: het zijn onvervangbare bestanden. Drie concrete valkuilen:

1. **Windows mangelt unicode-bestandsnamen** die via de command line aan een proces
   worden meegegeven (`café.jpg` → onvindbaar).
2. **Je kunt niet blind vertrouwen** dat een metadata-write de beelddata niet raakt.
3. **Per bestand een proces starten** is traag (exiftool = Perl, ~200ms startup).

## Oplossing

### 1. Argumenten via een UTF-8 argfile, nooit via de command line

```php
$argFile = tempnam(sys_get_temp_dir(), 'exiftool-');
file_put_contents($argFile, implode("\n", [
    '-charset', 'filename=UTF8',
    '-api', 'largefilesupport=1',
    ...$arguments,
]));

$result = Process::timeout(600)->run([$binary, '-@', $argFile]);
@unlink($argFile);
```

`-charset filename=UTF8` laat exiftool de wide-char file-API's gebruiken; de argfile
houdt de bytes rauw UTF-8 in plaats van door de ANSI-codepage.

### 2. Pixelgarantie via ImageDataHash + exiftools eigen backup

Laat `-overwrite_original` weg: exiftool bewaart dan automatisch `<bestand>_original`.
Vergelijk na de write de **ImageDataHash** (hash van uitsluitend de beelddata, niet de
metadata) van het nieuwe bestand met die van de backup — in één exiftool-aanroep:

```php
$output = $exifTool->run(['-json', '-ImageDataHash', $path, $backup], allowErrors: true);
// hashes verschillen → rollback:
@unlink($path);
rename($backup, $path);
throw new RuntimeException('Image data changed during metadata write');
```

Bij gelijkheid: `@unlink($backup)`. Zo is "pixels nooit aanraken" een **geverifieerde**
belofte in plaats van een aanname, en is de rollback gratis meegeleverd.

### 3. Eén schrijver per bestand + lezen in batches

- Schrijven: `WithoutOverlapping("xmp-write-{$id}")` op de queue-job — nooit twee
  exiftool-processen op hetzelfde bestand.
- Lezen: één proces voor een hele chunk (`-json -n file1 … fileN`) i.p.v. per bestand.
  Bij 65k bestanden scheelt dat uren aan processtart-overhead. Gebruik `allowErrors`:
  een batch exit non-zero zodra één bestand onleesbaar is, terwijl de rest prima terugkomt.
- Map de resultaten terug op de input-paden via `SourceFile` (exiftool normaliseert naar
  forward slashes — normaliseer beide kanten voor de vergelijking).

### 4. Niet-schrijfbare formaten zijn normaal, geen fout

`.avi`/`.mkv` ondersteunen geen XMP. Vang de fout af, markeer de rij (`xmp_status=failed`)
en toon in de UI dat de data alleen in de app-database staat. Nooit de queue laten
retryen op iets dat structureel niet kan.

## Gerelateerd

- Implementatie: `Vusista/app/Services/{ExifTool,XmpWriter}.php`, `app/Jobs/WriteXmpJob.php`
- Tests (DoD-bewijs): `Vusista/tests/Feature/XmpWriterIntegrationTest.php`
