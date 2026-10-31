<?php

namespace App\Http\Controllers;

use App\Services\ImageManipulation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImageController extends Controller
{
    /**
     * Display the image based on the provided path.
     *
     * @return BinaryFileResponse
     */
    public function manipulation(Request $request, string $imgPath)
    {
        // validate the path
        if (! preg_match('/^([a-zA-Z0-9\/\._-]+)$/', $imgPath) || str_contains($imgPath, '../')) {
            abort(404, 'Invalid image path');
        }

        $img = new ImageManipulation($imgPath);

        return response()->file(
            $img->getModifiedPath(request()->except(['expires', 'signature']))
        );
    }
}
