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
        $originalDir = $this->appConfig['original_upload_dir'] ?? ($targetDir . '/originals');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }
        if (!is_dir($originalDir)) {
            mkdir($originalDir, 0775, true);
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid('photo_', true) . ($extension ? '.' . $extension : '');
        $originalDestination = rtrim($originalDir, '/') . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $originalDestination)) {
            throw new RuntimeException('Upload fehlgeschlagen.');
        }

        $normalizedTarget = str_replace('\\', '/', rtrim(realpath($targetDir) ?: $targetDir, '/'));
        $normalizedOriginal = str_replace('\\', '/', rtrim(realpath($originalDir) ?: $originalDir, '/'));
        $originalRelativeDir = '';
        if ($normalizedTarget !== '' && $normalizedOriginal !== '' && strpos($normalizedOriginal, $normalizedTarget) === 0) {
            $suffix = trim(substr($normalizedOriginal, strlen($normalizedTarget)), '/');
            $originalRelativeDir = $suffix;
        }
        $originalRelativePath = $originalRelativeDir !== '' ? $originalRelativeDir . '/' . $filename : $filename;

        $destination = rtrim($targetDir, '/') . '/' . $filename;
        $this->createDisplayVersion($originalDestination, $destination);

        $metadata = $this->extractMetadata($originalDestination);
        $takenAt = $metadata['taken_at'] ?? null;

        $photoId = $this->photos->create([
            'user_id' => $userId,
            'title' => $input['title'] ?? null,
            'description' => $input['description'] ?? null,
            'file_path' => $filename,
            'original_file_path' => $originalRelativePath,
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
            'original_file_path' => $originalRelativePath,
            'latitude' => $metadata['latitude'],
            'longitude' => $metadata['longitude'],
            'taken_at' => $takenAt,
            'categories' => [],
            'category_ids' => $categoryIds,
            'category_details' => [],
            'primary_color' => null,
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

    private function createDisplayVersion(string $source, string $destination): void
    {
        $maxDimension = $this->appConfig['display_max_dimension'] ?? 1600;
        if (!is_numeric($maxDimension) || (float)$maxDimension <= 0) {
            copy($source, $destination);
            return;
        }
        $maxDimension = (float)$maxDimension;

        if (!extension_loaded('gd')) {
            copy($source, $destination);
            return;
        }

        $imageInfo = @getimagesize($source);
        if ($imageInfo === false) {
            copy($source, $destination);
            return;
        }

        [$width, $height, $type] = $imageInfo;

        if ($width <= $maxDimension && $height <= $maxDimension) {
            copy($source, $destination);
            return;
        }

        $ratio = min($maxDimension / $width, $maxDimension / $height);
        $newWidth = (int)max(1, round($width * $ratio));
        $newHeight = (int)max(1, round($height * $ratio));

        switch ($type) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($source);
                $output = $this->resampleImage($sourceImage, $width, $height, $newWidth, $newHeight);
                if ($output && imagejpeg($output, $destination, 85)) {
                    imagedestroy($output);
                } else {
                    copy($source, $destination);
                    if ($output) {
                        imagedestroy($output);
                    }
                }
                if ($sourceImage) {
                    imagedestroy($sourceImage);
                }
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($source);
                if ($sourceImage === false) {
                    copy($source, $destination);
                    break;
                }
                $output = imagecreatetruecolor($newWidth, $newHeight);
                if ($output === false) {
                    imagedestroy($sourceImage);
                    copy($source, $destination);
                    break;
                }
                imagealphablending($output, false);
                imagesavealpha($output, true);
                $resampled = imagecopyresampled($output, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                if ($resampled && imagepng($output, $destination, 6)) {
                    // success
                } else {
                    copy($source, $destination);
                }
                imagedestroy($sourceImage);
                imagedestroy($output);
                break;
            default:
                copy($source, $destination);
        }
    }

    private function resampleImage($sourceImage, int $width, int $height, int $newWidth, int $newHeight)
    {
        if ($sourceImage === false) {
            return null;
        }
        $output = imagecreatetruecolor($newWidth, $newHeight);
        if ($output === false) {
            return null;
        }
        $resampled = imagecopyresampled($output, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        if ($resampled === false) {
            imagedestroy($output);
            return null;
        }
        return $output;
    }
}
