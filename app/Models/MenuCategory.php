<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuCategory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'display_order'
    ];

    /**
     * Get the menu items for this category.
     */
    public function menuItems()
    {
        return $this->hasMany(MenuItem::class, 'category', 'name');
    }
}