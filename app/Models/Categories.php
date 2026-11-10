<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'services', 'image'])]
class Categories extends Model
{
    protected function casts(): array
    {
        return [
            'services' => 'array',
        ];
    }
    
    public function subCategories(): HasMany
    {
        return $this->hasMany(SubCategories::class, 'category_id');
    }
}
