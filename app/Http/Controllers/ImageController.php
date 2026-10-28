<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Services\ImageManipulation;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    /**
     * Display the image based on the provided path.
     *
     * @param string $imgPath
     * @return BinaryFileResponse
     */
    public function manipulation(Request $request, string $imgPath)
    {
        // validate the path
        if (!preg_match('/^([a-zA-Z0-9\/\._-]+)$/', $imgPath) || str_contains($imgPath, '../')) {
            abort(404, 'Invalid image path');
        }

        $img = new ImageManipulation($imgPath);
        return response()->file(
            $img->getModifiedPath(request()->except(['expires', 'signature']))
        );
    }
}
