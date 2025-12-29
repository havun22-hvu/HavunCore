# PDF to Image Conversion Pattern

> Convert PDF pages to JPEG images using Imagick (ImageMagick)
> **Origin:** Herdenkingsportaal (monument/gallery upload)

## Use Cases

- PDF upload waar gebruiker pagina's als afbeeldingen wil tonen
- Preview thumbnails genereren van PDF documenten
- Selectieve pagina conversie (gebruiker kiest welke pagina's)

## Requirements

- PHP Imagick extension (`extension=imagick`)
- ImageMagick met Ghostscript (voor PDF support)
- Laravel Storage facade

## Service Class

```php
<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PdfConversionService
{
    /**
     * Convert PDF pages to JPEG images
     *
     * @param UploadedFile $pdfFile The uploaded PDF file
     * @param string $outputDirectory Directory to store converted images
     * @param int $resolution DPI resolution for conversion (default 200)
     * @param int $quality JPEG quality 0-100 (default 90)
     * @return array Array of generated image filenames
     * @throws \Exception If conversion fails
     */
    public function convertToImages(
        UploadedFile $pdfFile,
        string $outputDirectory,
        int $resolution = 200,
        int $quality = 90
    ): array {
        if (!extension_loaded('imagick')) {
            throw new \Exception('Imagick extension is not installed.');
        }

        $generatedImages = [];
        $tempPdfPath = $pdfFile->getPathname();

        try {
            $imagick = new \Imagick();

            // Set resolution BEFORE reading for better quality
            $imagick->setResolution($resolution, $resolution);
            $imagick->readImage($tempPdfPath);

            $pageCount = $imagick->getNumberImages();

            // Ensure output directory exists
            if (!Storage::disk('public')->exists($outputDirectory)) {
                Storage::disk('public')->makeDirectory($outputDirectory);
            }

            $date = now()->format('Ymd-His');

            // Process each page
            for ($i = 0; $i < $pageCount; $i++) {
                $imagick->setIteratorIndex($i);
                $page = $imagick->getImage();

                // Convert CMYK to RGB (common in print PDFs)
                $page->transformImageColorspace(\Imagick::COLORSPACE_SRGB);

                // Set white background (remove transparency)
                $page->setImageBackgroundColor('white');
                $page->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);

                // Flatten layers
                $flattened = $page->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

                // Convert to JPEG
                $flattened->setImageFormat('jpeg');
                $flattened->setImageCompressionQuality($quality);

                // Generate filename
                $pageNum = $pageCount > 1 ? '-pagina' . ($i + 1) : '';
                $filename = "pdf-{$date}{$pageNum}.jpg";

                // Save to storage
                $outputPath = storage_path("app/public/{$outputDirectory}/{$filename}");
                $flattened->writeImage($outputPath);

                $generatedImages[] = [
                    'filename' => $filename,
                    'page' => $i + 1,
                    'size' => filesize($outputPath)
                ];

                // Clean up
                $page->clear();
                $page->destroy();
                $flattened->clear();
                $flattened->destroy();
            }

            $imagick->clear();
            $imagick->destroy();

            return $generatedImages;

        } catch (\ImagickException $e) {
            // Clean up partial files on error
            foreach ($generatedImages as $image) {
                $path = storage_path("app/public/{$outputDirectory}/{$image['filename']}");
                if (file_exists($path)) {
                    unlink($path);
                }
            }
            throw new \Exception('PDF conversie mislukt: ' . $e->getMessage());
        }
    }

    /**
     * Check if PDF conversion is supported
     */
    public function isSupported(): bool
    {
        return extension_loaded('imagick');
    }

    /**
     * Get page count without full conversion (fast)
     */
    public function getPageCount(UploadedFile $pdfFile): int
    {
        if (!$this->isSupported()) {
            return 0;
        }

        try {
            $imagick = new \Imagick();
            $imagick->pingImage($pdfFile->getPathname());
            $count = $imagick->getNumberImages();
            $imagick->clear();
            $imagick->destroy();
            return $count;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
```

## Controller Usage

### Simpele conversie (alle pagina's)

```php
public function uploadPdf(Request $request)
{
    $request->validate([
        'pdf' => 'required|mimes:pdf|max:15360' // 15MB
    ]);

    $service = new PdfConversionService();

    if (!$service->isSupported()) {
        return back()->with('error', 'PDF upload niet ondersteund.');
    }

    $images = $service->convertToImages(
        $request->file('pdf'),
        "uploads/{$user->id}",
        200,  // DPI
        90    // JPEG quality
    );

    // Save to database, etc.
    foreach ($images as $image) {
        Photo::create([
            'filename' => $image['filename'],
            'size' => $image['size']
        ]);
    }

    return back()->with('success', count($images) . ' pagina\'s geconverteerd.');
}
```

### Met pagina selectie (preview eerst)

```php
// Step 1: Generate low-res previews
public function getPdfPreviews(Request $request)
{
    $service = new PdfConversionService();

    $previews = $service->convertToImages(
        $request->file('pdf'),
        "temp/previews",
        150,  // Lower DPI for thumbnails
        80    // Lower quality
    );

    return response()->json([
        'previews' => $previews,
        'total_pages' => count($previews)
    ]);
}

// Step 2: Convert selected pages at full quality
public function uploadSelectedPages(Request $request)
{
    $selectedPages = $request->input('pages'); // [1, 3, 5]

    $service = new PdfConversionService();
    $allImages = $service->convertToImages($request->file('pdf'), "gallery", 200, 90);

    // Keep only selected pages
    $kept = [];
    foreach ($allImages as $image) {
        if (in_array($image['page'], $selectedPages)) {
            $kept[] = $image;
        } else {
            // Delete unwanted pages
            Storage::disk('public')->delete("gallery/{$image['filename']}");
        }
    }

    return response()->json(['images' => $kept]);
}
```

## Frontend (JavaScript)

```javascript
// Detect PDF and show page selector
async function handleFileUpload(file) {
    if (file.type === 'application/pdf') {
        const formData = new FormData();
        formData.append('pdf', file);

        const response = await fetch('/pdf-preview', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-TOKEN': csrfToken }
        });

        const data = await response.json();
        showPageSelector(data.previews);
    } else {
        // Regular image upload
        uploadImage(file);
    }
}

function showPageSelector(previews) {
    // Show modal with checkboxes for each page
    const html = previews.map((p, i) => `
        <label>
            <input type="checkbox" name="pages[]" value="${p.page}" checked>
            <img src="/storage/temp/previews/${p.filename}" alt="Page ${p.page}">
        </label>
    `).join('');

    document.getElementById('page-selector').innerHTML = html;
    document.getElementById('pdf-modal').classList.remove('hidden');
}
```

## Recommended Limits

| Setting | Value | Reason |
|---------|-------|--------|
| Max PDF size | 15 MB | Memory/processing time |
| Max pages | 10 | Prevent abuse |
| Preview DPI | 150 | Fast thumbnails |
| Final DPI | 200 | Good quality/size balance |
| JPEG quality | 90 | High quality, reasonable size |

## Server Setup (Ubuntu/Debian)

```bash
# Install ImageMagick with Ghostscript
sudo apt install imagemagick ghostscript

# Install PHP Imagick
sudo apt install php-imagick

# Edit ImageMagick policy to allow PDF
sudo nano /etc/ImageMagick-6/policy.xml
# Change: <policy domain="coder" rights="none" pattern="PDF" />
# To:     <policy domain="coder" rights="read|write" pattern="PDF" />

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

## Troubleshooting

| Problem | Solution |
|---------|----------|
| "not authorized" error | Edit ImageMagick policy.xml |
| Black backgrounds | Add `setImageBackgroundColor('white')` |
| Wrong colors (CMYK) | Add `transformImageColorspace(SRGB)` |
| Low quality output | Increase DPI before `readImage()` |
| Memory errors | Lower DPI, limit page count |

## See Also

- **Origin project:** Herdenkingsportaal (`app/Services/PdfConversionService.php`)
- **Used in:** Monument upload, Gallery upload
