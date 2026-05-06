<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Extract original filename from uploaded file path
        if (! empty($data['file_path'])) {
            $filePath = is_array($data['file_path'])
                ? collect($data['file_path'])->first()
                : $data['file_path'];

            if ($filePath && Storage::disk('local')->exists($filePath)) {
                // Store the original filename if not already set
                if (empty($data['original_filename'])) {
                    $data['original_filename'] = basename($filePath);
                }
            }

            $data['file_path'] = $filePath;
        }

        return $data;
    }
}
