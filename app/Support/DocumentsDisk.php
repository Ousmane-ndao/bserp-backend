<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

trait DocumentsDisk
{
    protected function documentsDiskName(): string
    {
        return env('DOCUMENTS_DISK', config('filesystems.default', 'local'));
    }

    protected function documentsDisk(): Filesystem
    {
        return Storage::disk($this->documentsDiskName());
    }
}
