<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'image'])]
class Categories extends Model
{
    public function subCategories(): HasMany
    {
        return $this->hasMany(SubCategories::class, 'category_id');
    }
}
