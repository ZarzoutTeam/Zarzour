<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

final class FakeMedia
{
    public static function mp4(string $name = 'video.mp4'): UploadedFile
    {
        $content = pack('N', 24)
            .'ftypmp42'
            .pack('N', 0)
            .'mp42isom'
            .pack('N', 8)
            .'mdat';

        return UploadedFile::fake()->createWithContent($name, $content);
    }
}
