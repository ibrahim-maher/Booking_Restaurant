<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\MenuItem;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            if (!Schema::hasColumn('menu_items', 'slug')) {
                $table->string('slug', 100)->after('name')->nullable();
            }
        });

        // Generate slugs for existing menu items
        $menuItems = MenuItem::all();
        foreach ($menuItems as $menuItem) {
            $menuItem->slug = Str::slug($menuItem->name);
            $menuItem->save();
        }

        // Make slug not nullable after updating
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('slug', 100)->nullable(false)->change();
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};