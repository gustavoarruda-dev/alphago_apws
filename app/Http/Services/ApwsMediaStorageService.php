<?php

namespace App\Http\Services;

use App\Models\ApwsCreative;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ApwsMediaStorageService
{
    /**
     * @return array{
     *   stored_media_disk: string,
     *   stored_media_path: string,
     *   stored_media_source_url: string,
     *   stored_media_mime: string|null,
     *   stored_media_size: int,
     *   stored_media_downloaded_at: string
     * }|null
     */
    public function resolveAndStore(string $customerUuid, array $payload): ?array
    {
        if (!(bool) config('services.apws.media_storage.enabled', true)) {
            return null;
        }

        $creativeCode = trim((string) ($payload['criativo'] ?? ''));
        if ($creativeCode === '') {
            return null;
        }

        $sourceUrl = $this->resolveMediaSourceUrl($payload);
        if ($sourceUrl === null) {
            return null;
        }

        $disk = trim((string) config('services.apws.media_storage.disk', 'public'));
        if ($disk === '') {
            $disk = 'public';
        }

        /** @var ApwsCreative|null $existing */
        $existing = ApwsCreative::query()
            ->select([
                'id',
                'stored_media_disk',
                'stored_media_path',
                'stored_media_source_url',
                'stored_media_mime',
                'stored_media_size',
                'stored_media_downloaded_at',
            ])
            ->where('customer_uuid', $customerUuid)
            ->where('creative_code', $creativeCode)
            ->first();

        if (
            $existing !== null &&
            trim((string) $existing->stored_media_source_url) === $sourceUrl &&
            trim((string) $existing->stored_media_disk) !== '' &&
            trim((string) $existing->stored_media_path) !== ''
        ) {
            $existingDisk = trim((string) $existing->stored_media_disk);
            $existingPath = trim((string) $existing->stored_media_path);

            if (Storage::disk($existingDisk)->exists($existingPath)) {
                return [
                    'stored_media_disk' => $existingDisk,
                    'stored_media_path' => $existingPath,
                    'stored_media_source_url' => $sourceUrl,
                    'stored_media_mime' => $existing->stored_media_mime !== null ? (string) $existing->stored_media_mime : null,
                    'stored_media_size' => (int) ($existing->stored_media_size ?? 0),
                    'stored_media_downloaded_at' => optional($existing->stored_media_downloaded_at)->toDateTimeString() ?? Carbon::now()->toDateTimeString(),
                ];
            }
        }

        $media = $this->downloadMedia($sourceUrl);
        if ($media === null) {
            return null;
        }

        if ($media['kind'] === 'unknown') {
            return null;
        }

        $safeCustomer = preg_replace('/[^a-zA-Z0-9_-]/', '-', $customerUuid) ?: 'customer';
        $safeCreative = preg_replace('/[^a-zA-Z0-9_-]/', '-', $creativeCode) ?: 'creative';
        $extension = $this->resolveExtension($sourceUrl, $media['content_type'], $media['kind']);
        $targetPath = "apws/{$safeCustomer}/{$safeCreative}.{$extension}";

        $saved = Storage::disk($disk)->put($targetPath, $media['body'], ['visibility' => 'public']);
        if (!$saved) {
            return null;
        }

        $oldDisk = trim((string) ($existing?->stored_media_disk ?? ''));
        $oldPath = trim((string) ($existing?->stored_media_path ?? ''));
        if ($oldDisk !== '' && $oldPath !== '' && ($oldDisk !== $disk || $oldPath !== $targetPath)) {
            try {
                Storage::disk($oldDisk)->delete($oldPath);
            } catch (\Throwable $exception) {
                Log::warning('APWS media cleanup failed.', [
                    'customer_uuid' => $customerUuid,
                    'creative_code' => $creativeCode,
                    'disk' => $oldDisk,
                    'path' => $oldPath,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'stored_media_disk' => $disk,
            'stored_media_path' => $targetPath,
            'stored_media_source_url' => $sourceUrl,
            'stored_media_mime' => $media['content_type'] !== '' ? $media['content_type'] : null,
            'stored_media_size' => strlen($media['body']),
            'stored_media_downloaded_at' => Carbon::now()->toDateTimeString(),
        ];
    }

    private function resolveMediaSourceUrl(array $payload): ?string
    {
        $downloads = $payload['downloads'] ?? [];
        $downloadCandidates = [];

        if (is_array($downloads)) {
            $downloadCandidates = $downloads;
        } elseif (is_string($downloads) && trim($downloads) !== '') {
            $downloadCandidates = [$downloads];
        }

        $directCandidates = [
            $payload['imagem'] ?? null,
            $payload['image'] ?? null,
            $payload['miniatura'] ?? null,
            $payload['thumbnail'] ?? null,
            $payload['thumb'] ?? null,
        ];

        $allCandidates = array_merge($downloadCandidates, $directCandidates);
        foreach ($allCandidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $normalized = trim($candidate);
            if ($normalized === '' || !preg_match('/^https?:\/\//i', $normalized)) {
                continue;
            }

            return $normalized;
        }

        return null;
    }

    /**
     * @return array{body: string, content_type: string, kind: 'image'|'video'|'audio'|'unknown'}|null
     */
    private function downloadMedia(string $sourceUrl): ?array
    {
        $timeout = max(10, (int) config('services.apws.media_storage.timeout', 90));
        $retries = max(1, (int) config('services.apws.media_storage.retries', 2));
        $retrySleepMs = max(0, (int) config('services.apws.media_storage.retry_sleep_ms', 1500));
        $maxBytes = max(1, (int) config('services.apws.media_storage.max_bytes', 150 * 1024 * 1024));

        try {
            $response = Http::timeout($timeout)
                ->retry($retries, $retrySleepMs)
                ->withHeaders([
                    'User-Agent' => 'AlphaGO-APWS-MediaSync/1.0',
                ])
                ->get($sourceUrl);
        } catch (\Throwable $exception) {
            Log::warning('APWS media download request failed.', [
                'url' => $sourceUrl,
                'error' => $exception->getMessage(),
            ]);
            return null;
        }

        if ($response->failed()) {
            Log::warning('APWS media download returned non-success status.', [
                'url' => $sourceUrl,
                'status' => $response->status(),
            ]);
            return null;
        }

        $contentType = strtolower(trim((string) $response->header('Content-Type', '')));
        if (($pos = strpos($contentType, ';')) !== false) {
            $contentType = trim(substr($contentType, 0, $pos));
        }

        $lengthHeader = trim((string) $response->header('Content-Length', ''));
        if ($lengthHeader !== '' && is_numeric($lengthHeader) && (int) $lengthHeader > $maxBytes) {
            Log::warning('APWS media download exceeded configured max bytes by Content-Length.', [
                'url' => $sourceUrl,
                'max_bytes' => $maxBytes,
                'content_length' => (int) $lengthHeader,
            ]);
            return null;
        }

        $body = (string) $response->body();
        if ($body === '') {
            return null;
        }

        $size = strlen($body);
        if ($size > $maxBytes) {
            Log::warning('APWS media download exceeded configured max bytes by payload size.', [
                'url' => $sourceUrl,
                'max_bytes' => $maxBytes,
                'payload_size' => $size,
            ]);
            return null;
        }

        return [
            'body' => $body,
            'content_type' => $contentType,
            'kind' => $this->detectKind($sourceUrl, $contentType),
        ];
    }

    private function detectKind(string $sourceUrl, string $contentType): string
    {
        if (str_starts_with($contentType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($contentType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($contentType, 'audio/')) {
            return 'audio';
        }

        $path = parse_url($sourceUrl, PHP_URL_PATH);
        $extension = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg', 'avif' => 'image',
            'mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv' => 'video',
            'mp3', 'wav', 'm4a', 'ogg', 'aac', 'flac' => 'audio',
            default => 'unknown',
        };
    }

    private function resolveExtension(string $sourceUrl, string $contentType, string $kind): string
    {
        $path = parse_url($sourceUrl, PHP_URL_PATH);
        $fromUrl = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));
        if ($fromUrl !== '') {
            return preg_replace('/[^a-z0-9]/', '', $fromUrl) ?: $this->defaultExtensionForKind($kind);
        }

        $fromMime = match ($contentType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/svg+xml' => 'svg',
            'image/avif' => 'avif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/mp4' => 'm4a',
            'audio/aac' => 'aac',
            'audio/ogg' => 'ogg',
            default => '',
        };

        if ($fromMime !== '') {
            return $fromMime;
        }

        return $this->defaultExtensionForKind($kind);
    }

    private function defaultExtensionForKind(string $kind): string
    {
        return match ($kind) {
            'video' => 'mp4',
            'audio' => 'mp3',
            default => 'jpg',
        };
    }
}

