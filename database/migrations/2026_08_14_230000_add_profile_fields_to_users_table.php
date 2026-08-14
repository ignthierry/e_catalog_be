<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'phone_number')) {
                $table->string('phone_number')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'address')) {
                $table->text('address')->nullable()->after('role');
            }
            if (!Schema::hasColumn('users', 'province_id')) {
                $table->string('province_id')->nullable()->after('address');
            }
            if (!Schema::hasColumn('users', 'province_name')) {
                $table->string('province_name')->nullable()->after('province_id');
            }
            if (!Schema::hasColumn('users', 'city_id')) {
                $table->string('city_id')->nullable()->after('province_name');
            }
            if (!Schema::hasColumn('users', 'city_name')) {
                $table->string('city_name')->nullable()->after('city_id');
            }
            if (!Schema::hasColumn('users', 'subdistrict_id')) {
                $table->string('subdistrict_id')->nullable()->after('city_name');
            }
            if (!Schema::hasColumn('users', 'subdistrict_name')) {
                $table->string('subdistrict_name')->nullable()->after('subdistrict_id');
            }
            if (!Schema::hasColumn('users', 'postal_code')) {
                $table->string('postal_code')->nullable()->after('subdistrict_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columnsToDrop = [
                'avatar',
                'address',
                'province_id',
                'province_name',
                'city_id',
                'city_name',
                'subdistrict_id',
                'subdistrict_name',
                'postal_code',
            ];

            foreach ($columnsToDrop as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
