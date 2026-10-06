<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orders, shipments and payment tables (spec sections 8.2 and 8.4).
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('number', 30)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('fee')->default(0);
            $table->unsignedBigInteger('total');
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->unsignedBigInteger('credit')->default(0);
            $table->string('payment_reference', 20)->unique();
            $table->string('status', 30);
            $table->unsignedSmallInteger('rejected_attempts')->default(0);
            $table->boolean('is_escalated')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('receipt_number', 30)->nullable()->unique();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->json('reminders_sent')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('tracking_number', 40)->nullable()->unique();
            $table->foreignId('carrier_id')->constrained()->restrictOnDelete();
            $table->foreignId('partner_carrier_id')->nullable()->constrained('carriers')->nullOnDelete();
            $table->string('partner_tracking_number', 40)->nullable()->index();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service', 40);
            $table->string('mode', 20);
            $table->json('origin');
            $table->json('destination');
            $table->json('sender');
            $table->json('recipient');
            $table->json('customs')->nullable();
            $table->string('status', 30);
            $table->unsignedInteger('weight_g')->default(0);
            $table->unsignedInteger('chargeable_weight_g')->default(0);
            $table->unsignedBigInteger('declared_value')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->boolean('insurance')->default(false);
            $table->decimal('progress', 4, 3)->default(0);
            $table->decimal('current_lat', 9, 6)->nullable();
            $table->decimal('current_lon', 9, 6)->nullable();
            $table->string('current_place')->nullable();
            $table->timestamp('eta_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'status']);
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('weight_g');
            $table->unsignedInteger('length_mm');
            $table->unsignedInteger('width_mm');
            $table->unsignedInteger('height_mm');
            $table->unsignedBigInteger('declared_value')->default(0);
            $table->string('category', 40);
            $table->timestamps();
        });

        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->string('label');
            $table->string('place')->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lon', 9, 6)->nullable();
            $table->timestamp('occurred_at');
            $table->string('source', 20);
            $table->boolean('is_public')->default(true);
            $table->text('note')->nullable();
            $table->string('provider_event_id', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['shipment_id', 'occurred_at']);
            $table->unique(['shipment_id', 'provider_event_id']);
        });

        Schema::create('shipment_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->foreignId('file_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_released')->default(false);
            $table->timestamps();
            $table->unique(['shipment_id', 'type']);
        });

        Schema::create('tracking_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('tracking_number', 40)->index();
            $table->string('email');
            $table->string('locale', 5)->default('en');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->unique(['tracking_number', 'email']);
        });

        Schema::create('tracking_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->json('payload');
            $table->timestamp('fetched_at');
            $table->unique(['carrier_id', 'number']);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name');
            $table->string('kind', 30);
            $table->foreignId('logo_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->unsignedBigInteger('min_amount')->default(0);
            $table->unsignedBigInteger('max_amount')->nullable();
            $table->decimal('fee_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('fee_fixed')->default(0);
            $table->json('countries')->nullable();
            $table->boolean('requires_transaction_id')->default(false);
            $table->boolean('proof_required')->default(true);
            $table->string('risk_level', 10)->default('low');
            $table->unsignedSmallInteger('expiry_hours')->nullable();
            $table->text('instructions_en')->nullable();
            $table->text('instructions_fr')->nullable();
            $table->json('gift_card_rules')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_method_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();
            $table->string('label_en');
            $table->string('label_fr');
            $table->text('value')->nullable();
            $table->text('pending_value')->nullable();
            $table->timestamp('pending_effective_at')->nullable();
            $table->string('type', 20)->default('text');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->text('details_snapshot');
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 14, 6)->default(1);
            $table->unsignedBigInteger('fee');
            $table->unsignedBigInteger('amount_expected');
            $table->unsignedBigInteger('amount_received')->default(0);
            $table->string('status', 30);
            $table->timestamp('selected_at');
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('rate_locked_until')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });

        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_paid');
            $table->char('currency', 3);
            $table->string('payer_name');
            $table->date('paid_on');
            $table->string('transaction_id', 120)->nullable();
            $table->text('customer_note')->nullable();
            $table->string('status', 30);
            $table->boolean('is_duplicate')->default(false);
            $table->json('duplicate_of')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('overdue_alerted_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('payment_proof_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_proof_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_id')->constrained()->restrictOnDelete();
            $table->char('sha256', 64)->index();
        });

        Schema::create('payment_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_proof_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('decision', 30);
            $table->string('reason_code', 40)->nullable();
            $table->text('note')->nullable();
            $table->json('checklist')->nullable();
            $table->unsignedBigInteger('amount_received')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('decided_at');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('gift_card_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_proof_id')->constrained()->cascadeOnDelete();
            $table->string('brand', 60);
            $table->text('code');
            $table->string('code_last4', 4);
            $table->text('pin')->nullable();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->timestamp('redeemed_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('number', 30)->unique();
            $table->string('type', 20)->default('invoice');
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->timestamp('issued_at');
            $table->foreignId('pdf_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('method', 60);
            $table->string('reference', 120)->nullable();
            $table->text('reason');
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('processed_at');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER payment_reviews_append_only
                    BEFORE UPDATE OR DELETE ON payment_reviews
                    FOR EACH ROW EXECUTE FUNCTION prevent_append_only_change();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS payment_reviews_append_only ON payment_reviews;');
        }

        Schema::dropIfExists('refunds');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('gift_card_submissions');
        Schema::dropIfExists('payment_reviews');
        Schema::dropIfExists('payment_proof_files');
        Schema::dropIfExists('payment_proofs');
        Schema::dropIfExists('order_payments');
        Schema::dropIfExists('payment_method_fields');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('tracking_cache');
        Schema::dropIfExists('tracking_subscriptions');
        Schema::dropIfExists('shipment_documents');
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('orders');
    }
};
