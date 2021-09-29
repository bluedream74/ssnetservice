<?php
namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use Illuminate\Support\Str;

trait ImageUpload
{
    public function imageUpload($file, $uploadPath, $storage = "public") 
    {
        $time = Str::random(16);
        $filename = $uploadPath . "/{$time}." . $file->getClientOriginalExtension();
        Storage::disk($storage)->put($filename, file_get_contents($file));

        return [
            'path' => $filename,
            'file' => $file->getClientOriginalName() //$image_full_name
        ]; 
    }

    public function imageUploadWithoutThumb($uploadPath, $file, $storage = "public")
    {
        $time = Str::random(16);
        $filename = $uploadPath . "/{$time}." . $file->getClientOriginalExtension();
        Storage::disk($storage)->put($filename, file_get_contents($file));

        return $filename; 
    }

    public function imageUpdateWithThumb($uploadPath, $file, $size = 500, $storage = "public")
    {
        $time = Str::random(16);
        $filename = $uploadPath . "/{$time}." . $file->getClientOriginalExtension();
        Storage::disk($storage)->put($filename, file_get_contents($file));

        $thumb    = $uploadPath . "/thumbs/{$time}." . $file->getClientOriginalExtension();

        $image_resize = Image::make($file->getRealPath());              
        Storage::disk($storage)->put($thumb, $this->resizeImage($image_resize, $size));

        return $thumb;
    }

    private function resizeImage($image, $requiredSize) {
        $image->orientate();
        $width = $image->width();
        $height = $image->height();
    
        // Check if image resize is required or not
        if ($requiredSize >= $width && $requiredSize >= $height) return $image->stream();
    
        $newWidth;
        $newHeight;
    
        $aspectRatio = $width / $height;
        if ($aspectRatio >= 1.0) {
            $newWidth = $requiredSize;
            $newHeight = $requiredSize / $aspectRatio;
        } else {
            $newWidth = $requiredSize * $aspectRatio;
            $newHeight = $requiredSize;
        }
    
        $image->resize($newWidth, $newHeight)->stream();

        return $image;
    }

    public function getImageFilePath($file, $storage = "public")
    {
        return Storage::disk($storage)->url($file);
    }

    public function deleteOriginFile($file, $storage = "public")
    {
        try {
            Storage::disk($storage)->delete($file);
        } catch (\Throwable $e) {
        }

        try {
            Storage::disk($storage)->delete(str_replace('thumbs/', '', $file));
        } catch (\Throwable $e) {
        }
    }
}