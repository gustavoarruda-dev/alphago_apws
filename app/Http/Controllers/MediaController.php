<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerUuidRequest;
use App\Http\Services\ApwsQueryService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    public function __construct(private readonly ApwsQueryService $service)
    {
    }

    public function show(CustomerUuidRequest $request, string $creativeCode): Response
    {
        $campaign = $this->service->campaign($request->customerUuid(), $creativeCode);
        if ($campaign === null) {
            return response('Media not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $disk = trim((string) ($campaign->stored_media_disk ?? ''));
        $path = trim((string) ($campaign->stored_media_path ?? ''));
        if ($disk === '' || $path === '') {
            return response('Media not available.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $storage = Storage::disk($disk);
        if (!$storage->exists($path)) {
            return response('Media not available.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $mime = trim((string) ($campaign->stored_media_mime ?? ''));
        $headers = [
            'Cache-Control' => 'public, max-age=86400',
        ];
        if ($mime !== '') {
            $headers['Content-Type'] = $mime;
        }

        $filename = basename($path);

        return $storage->response($path, $filename, $headers, 'inline');
    }
}

