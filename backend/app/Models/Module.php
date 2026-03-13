<?php

namespace App\Models;

use App\Enums\ModuleType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'key',
        'name',
        'description',
        'type',
        'language',
        'version',
        'source_url',
        'file_size',
        'is_installed',
        'is_bundled',
        'features',
        'engine',
        'driver',
        'mod_drv',
        'data_path',
        'source_type_format',
        'compress_type',
        'block_type',
        'versification',
        'encoding',
        'direction',
        'category',
        'minimum_version',
        'cipher_key',
        'about',
        'copyright',
        'global_option_filters',
        'conf_data',
        'install_size',
        'module_source_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => ModuleType::class,
            'file_size' => 'integer',
            'install_size' => 'integer',
            'is_installed' => 'boolean',
            'is_bundled' => 'boolean',
            'features' => 'array',
            'global_option_filters' => 'array',
            'conf_data' => 'array',
        ];
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function verses(): HasMany
    {
        return $this->hasMany(Verse::class);
    }

    public function moduleSource(): BelongsTo
    {
        return $this->belongsTo(ModuleSource::class);
    }
}
