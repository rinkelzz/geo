<?php

class PhotoService
{
    private PhotoRepository $photos;
    private CategoryRepository $categories;
    private array $appConfig;

    public function __construct(PhotoRepository $photos, CategoryRepository $categories, array $appConfig)
    {
        $this->photos = $photos;
        $this->categories = $categories;
        $this->appConfig = $appConfig;
    }

    public function handleUpload(array $file, array $input, int $userId, array $categoryIds = []): array
    {
        $this->validateUpload($file);

        $targetDir = $this->appConfig['upload_dir'];
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $filename = uniqid('photo_', true) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $destination = rtrim($targetDir, '/') . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('Upload fehlgeschlagen.');
        }

        $metadata = $this->extractMetadata($destination);
        $takenAt = $metadata['taken_at'] ?? null;

        $photoId = $this->photos->create([
            'user_id' => $userId,
            'title' => $input['title'] ?? null,
            'description' => $input['description'] ?? null,
            'file_path' => $filename,
            'latitude' => $metadata['latitude'],
            'longitude' => $metadata['longitude'],
            'taken_at' => $takenAt,
        ]);

        $this->categories->syncPhotoCategories($photoId, $userId, $categoryIds);

        $photos = $this->photos->findByUser($userId);
        $created = null;
        foreach ($photos as $row) {
            if ((int)$row['id'] === $photoId) {
                $created = $row;
                break;
            }
        }

        return $created ?? [
            'id' => $photoId,
            'file_path' => $filename,
            'latitude' => $metadata['latitude'],
            'longitude' => $metadata['longitude'],
            'taken_at' => $takenAt,
            'categories' => [],
            'category_ids' => $categoryIds,
        ];
    }

    private function validateUpload(array $file): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Ungültiger Upload: ' . $file['error']);
        }
        if ($file['size'] > $this->appConfig['max_upload_size']) {
            throw new RuntimeException('Datei zu groß.');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $this->appConfig['allowed_types'])) {
            throw new RuntimeException('Dateityp nicht erlaubt.');
        }
    }

    private function extractMetadata(string $filePath): array
    {
        $latitude = null;
        $longitude = null;
        $takenAt = null;

        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($filePath, 0, true);
            if ($exif && isset($exif['GPS'])) {
                $latitude = $this->gpsToDecimal($exif['GPS']['GPSLatitude'] ?? null, $exif['GPS']['GPSLatitudeRef'] ?? null);
                $longitude = $this->gpsToDecimal($exif['GPS']['GPSLongitude'] ?? null, $exif['GPS']['GPSLongitudeRef'] ?? null);
            }
            if ($exif && isset($exif['EXIF']['DateTimeOriginal'])) {
                $takenAt = date('Y-m-d H:i:s', strtotime($exif['EXIF']['DateTimeOriginal']));
            }
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'taken_at' => $takenAt,
        ];
    }

    private function gpsToDecimal(?array $coordinate, ?string $hemisphere): ?float
    {
        if (!$coordinate || !$hemisphere) {
            return null;
        }

        $degrees = $this->gpsPartToFloat($coordinate[0] ?? '0/1');
        $minutes = $this->gpsPartToFloat($coordinate[1] ?? '0/1');
        $seconds = $this->gpsPartToFloat($coordinate[2] ?? '0/1');

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);
        if (in_array(strtoupper($hemisphere), ['S', 'W'], true)) {
            $decimal *= -1;
        }
        return $decimal;
    }

    private function gpsPartToFloat(string $part): float
    {
        $components = explode('/', $part);
        if (count($components) === 2 && (float)$components[1] !== 0.0) {
            return (float)$components[0] / (float)$components[1];
        }
        return (float)$part;
    }
}
