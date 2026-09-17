<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Ptah\Support\UserIdentity;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The `user_id` column used to be `foreignId('user_id')->constrained()`,
     * and a bare `constrained()` makes Laravel INFER the target table from the
     * column name: `users`. That was the only inferred foreign key in the
     * package — the four other `user_id` columns carry no constraint at all,
     * and every internal one names its table — and it contradicted the promise
     * written beside `user_model` in the config: "no hard-coded FK".
     *
     * A host pointing `PTAH_USER_MODEL` at its own identity model could not
     * save a preference: the insert failed against a `users` table it does not
     * use. The type was wrong too, for a model with a UUID or ULID key.
     *
     * So the identity is resolved instead of assumed. See `UserIdentity` for
     * where each piece comes from and what `ptah.preferences.foreign_key`
     * decides.
     */
    public function up(): void
    {
        $identity = UserIdentity::resolve();
        $constrain = $identity->shouldConstrain();

        Schema::create('user_preferences', function (Blueprint $table) use ($identity, $constrain) {
            $table->id();

            match ($identity->keyKind) {
                'uuid' => $table->uuid('user_id'),
                'ulid' => $table->ulid('user_id'),
                'string' => $table->string('user_id'),
                default => $table->unsignedBigInteger('user_id'),
            };

            $table->string('key');
            $table->json('value')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();

            $table->unique(['user_id', 'key']);
            $table->index('group');

            if ($constrain) {
                $table->foreign('user_id')
                    ->references($identity->keyName)
                    ->on($identity->table)
                    ->cascadeOnDelete();
            }

            // Sem a constraint, o indice composto acima ja serve a busca por
            // usuario na maioria dos motores (prefixo mais a esquerda), mas
            // nem todo planejador aproveita o prefixo — e a coluna continua
            // sendo filtrada sozinha em `UserPreference::forUser()`.
            if (! $constrain) {
                $table->index('user_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
