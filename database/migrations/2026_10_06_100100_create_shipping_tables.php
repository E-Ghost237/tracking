<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Geography, carriers, pricing and shipping tables (spec sections 8.2 and 8.3).
     */
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('ascii_name', 120)->index();
            $table->char('country', 2)->index();
            $table->string('region', 120)->nullable();
            $table->decimal('lat', 9, 6);
            $table->decimal('lon', 9, 6);
            $table->unsignedInteger('population')->default(0);
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('type', 20);
            $table->string('line1')->nullable();
            $table->string('city', 120);
            $table->char('country', 2);
            $table->decimal('lat', 9, 6);
            $table->decimal('lon', 9, 6);
            $table->string('phone', 40)->nullable();
            $table->json('opening_hours')->nullable();
            $table->json('modes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->json('number_patterns');
            $table->string('tracking_url_template')->nullable();
            $table->string('region', 60)->nullable();
            $table->text('api_config')->nullable();
            $table->boolean('is_own')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->json('countries');
            $table->timestamps();
        });

        Schema::create('transport_modes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_en');
            $table->string('name_fr');
            $table->decimal('multiplier', 6, 3)->default(1);
            $table->unsignedInteger('volumetric_divisor')->default(5000);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rate_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->string('name');
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->boolean('is_active')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rate_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rate_card_id')->constrained()->cascadeOnDelete();
            $table->string('zone_from', 20);
            $table->string('zone_to', 20);
            $table->string('mode', 20);
            $table->unsignedInteger('base_fee');
            $table->decimal('weight_from_kg', 8, 2);
            $table->decimal('weight_to_kg', 8, 2);
            $table->unsignedInteger('price_per_kg');
            $table->unsignedSmallInteger('transit_min_days');
            $table->unsignedSmallInteger('transit_max_days');
            $table->timestamps();
            $table->index(['rate_card_id', 'zone_from', 'zone_to', 'mode']);
        });

        Schema::create('surcharges', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('type', 10);
            $table->decimal('value', 10, 2);
            $table->string('basis', 20)->default('freight');
            $table->string('applies_to', 20)->default('all');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('reference', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('origin');
            $table->json('destination');
            $table->json('packages');
            $table->string('mode', 20);
            $table->boolean('insurance')->default(false);
            $table->unsignedBigInteger('declared_value')->default(0);
            $table->decimal('chargeable_weight_kg', 10, 2);
            $table->unsignedInteger('distance_km');
            $table->json('price_breakdown');
            $table->unsignedBigInteger('total');
            $table->char('currency', 3);
            $table->unsignedSmallInteger('transit_min_days');
            $table->unsignedSmallInteger('transit_max_days');
            $table->foreignId('rate_card_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('booked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('shipment_drafts', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('step')->default(1);
            $table->json('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_drafts');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('surcharges');
        Schema::dropIfExists('rate_lines');
        Schema::dropIfExists('rate_cards');
        Schema::dropIfExists('transport_modes');
        Schema::dropIfExists('zones');
        Schema::dropIfExists('carriers');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('cities');
    }
};
