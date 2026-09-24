<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;

class BlogImages
{
    private const array SIZES = ['thumbnail' => 400, 'small' => 800, 'large' => 1600];

    public function store(UploadedFile $file): string
    {
        $path = 'posts/'.Str::uuid().'.'.$file->extension();
        try {
            if (! Storage::disk('public')->putFileAs('posts', $file, basename($path))) {
                throw new \RuntimeException('The original image could not be stored.');
            }
            $this->generateVariants($path);
        } catch (\Throwable $exception) {
            $this->delete($path);
            throw $exception;
        }

        return $path;
    }

    public function generateVariants(string $path): void
    {
        $disk = Storage::disk('public');
        $image = null;
        $created = [];
        try {
            foreach (self::SIZES as $size => $width) {
                $variant = $this->variantPath($path, $size);
                if ($disk->exists($variant)) {
                    continue;
                }
                $image ??= ImageManager::usingDriver(Driver::class)->decode($disk->get($path));
                $resized = (clone $image)->scaleDown(width: $width, height: $width);
                $created[] = $variant;
                if (! $disk->put($variant, (string) $resized->encodeUsingFormat(Format::WEBP, quality: 65))) {
                    throw new \RuntimeException('The resized image could not be stored.');
                }
            }
        } catch (\Throwable $exception) {
            $disk->delete($created);
            throw $exception;
        }
    }

    public function variantPath(string $path, string $size): string
    {
        if (! array_key_exists($size, self::SIZES)) {
            throw new \InvalidArgumentException('Unknown image size.');
        }

        return dirname($path).'/'.$size.'-'.pathinfo($path, PATHINFO_FILENAME).'.webp';
    }

    public function url(string $path, string $size): string
    {
        $disk = Storage::disk('public');
        $variant = $this->variantPath($path, $size);

        return $disk->url($disk->exists($variant) ? $variant : $path);
    }

    public function delete(string $path): void
    {
        $paths = [$path];
        foreach (array_keys(self::SIZES) as $size) {
            $paths[] = $this->variantPath($path, $size);
        }
        Storage::disk('public')->delete($paths);
    }
}
