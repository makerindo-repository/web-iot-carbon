<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ImageService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public static function image_intervention($image, $path, $ratio = false): string
    {
        $clientExtension = ($image->extension() == null && $image->hashName() == '') ? 'png' : $image->extension();
        $name = strtoupper(Str::random(5)) . '-' . time() . '.' . $clientExtension;

        $imageResize = Image::read($image->getPathName());

        if ($ratio != false) {
           $originalWidth = $imageResize->width();
            $originalHeight = $imageResize->height();

            if ($originalWidth / $originalHeight > $ratio) {
                $height = $originalHeight;
                $width = floor($height * $ratio);
                $x = floor(($originalWidth - $width) / 2);
                $y = 0;
            } else {
                $width = $originalWidth;
                $height = floor($width / $ratio);
                $x = 0;
                $y = floor(($originalHeight - $height) / 2);
            }

            // Crop the image to the desired ratio
            $imageResize = $imageResize->crop($width, $height, $x, $y);
        }

        if (!File::exists(public_path($path))) {
            File::makeDirectory(public_path($path), 0755, true, true);
        }

        // $final_path = public_path($path . $name);
        $imageResize->save(public_path($path . $name));

        return $path . $name;
    }

    public static function deleteImage(?string $path) : bool {
        if(!$path) return false;

        if (!File::exists(public_path($path))) {
            return false;
        }

        File::delete(public_path($path));

        return true;
    }
}