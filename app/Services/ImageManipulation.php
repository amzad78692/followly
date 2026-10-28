<?php


namespace App\Services;

use Exception;
use Spatie\Glide\GlideImage;

class ImageManipulation
{
    /**
     * The prefix for the image cache path.
     *
     * @var string
     */
    public static $pathPrefix = 'storage/.cache';

    /**
     * The path to the image that will be returned when an image is not found.
     *
     * @var string
     */
    public static $imageNotFoundPath = 'no-image.webp';

    /**
     * The array of custom image path prefixes.
     *
     * @var array
     */
    public function __construct(
        public string $filePath
    ) {
        //
    }

    /**
     * Display the image based on the provided path.
     *
     * @return string
     */
    public function getModifiedPath(array $params = []): string
    {
        try {

            $modifiedImgPath = public_path(
                rtrim(self::$pathPrefix, '/') .
                '/' .
                ltrim($this->filePath, '/') .
                (count($params) ? '__' . http_build_query($params) : '')
            );
            $pathToImage = public_path($this->filePath);

            $folder = dirname($modifiedImgPath);
            if (!is_dir($folder)) {
                mkdir($folder, 0755, true);
            }

            // dd($pathToImage, $modifiedImgPath, $params);
            GlideImage::create($pathToImage)
                ->modify($params)
                ->save($modifiedImgPath);

            return $modifiedImgPath;
        } catch (Exception $e) {
            return $this->getOriginalImagePath($this->filePath);
        }
    }

    private function getOriginalImagePath(string $path): string
    {
        if (file_exists(public_path($path))) {
            return public_path($path);
        }

        return public_path(self::$imageNotFoundPath);
    }

}
