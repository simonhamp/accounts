<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;

class ImportFailedException extends Exception
{
    public function __construct(
        string $message,
        public ?Model $model = null
    ) {
        parent::__construct($message);
    }

    public static function missingFilePath(Model $model): self
    {
        $modelClass = class_basename($model);

        return new self("No original file path specified for {$modelClass} #{$model->id}", $model);
    }

    public static function fileNotFound(Model $model, string $path): self
    {
        $modelClass = class_basename($model);

        return new self("Original PDF file not found for {$modelClass} #{$model->id}: {$path}", $model);
    }
}
