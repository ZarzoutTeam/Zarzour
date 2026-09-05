<?php

namespace App\Filament\Resources\Concerns;

trait RedirectsToPreviousListPage
{
    protected function getRedirectUrl(): ?string
    {
        $resource = static::getResource();
        $indexUrl = $resource::getUrl('index');

        if (
            filled($this->previousUrl)
            && ($this->previousUrl === $indexUrl || str_starts_with($this->previousUrl, $indexUrl.'?'))
        ) {
            return $this->previousUrl;
        }

        return $indexUrl;
    }
}
