<?php namespace BABA\Favoritebutton\Updates;

use October\Rain\Database\Updates\Migration;
use Schema;

class CreateFavoriteItems extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('baba_favorites_groups')) {
            Schema::create('baba_favorites_groups', function ($table) {
                $table->engine = 'InnoDB';
                $table->increments('id');
                $table->integer('user_id')->nullable()->index();
                $table->string('owner_key', 64)->index();
                $table->string('name', 60);
                $table->string('color', 7)->default('#0f766e');
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['owner_key', 'name'], 'baba_favorite_group_owner_name');
            });
        }

        if (!Schema::hasTable('baba_favorites_tags')) {
            Schema::create('baba_favorites_tags', function ($table) {
                $table->engine = 'InnoDB';
                $table->increments('id');
                $table->integer('user_id')->nullable()->index();
                $table->string('owner_key', 64)->index();
                $table->string('name', 40);
                $table->timestamps();
                $table->unique(['owner_key', 'name'], 'baba_favorite_tag_owner_name');
            });
        }

        if (!Schema::hasTable('baba_favorites_items')) {
            Schema::create('baba_favorites_items', function ($table) {
                $table->engine = 'InnoDB';
                $table->increments('id');
                $table->integer('user_id')->nullable()->index();
                $table->string('owner_key', 64)->index();
                $table->string('item_key', 64);
                $table->string('target_type', 191)->nullable();
                $table->integer('target_id')->nullable();
                $table->string('title', 255);
                $table->string('url', 1024)->nullable();
                $table->text('excerpt')->nullable();
                $table->string('image_url', 1024)->nullable();
                $table->integer('group_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['owner_key', 'item_key'], 'baba_favorite_owner_item');
            });
        }

        if (!Schema::hasTable('baba_favorites_favorite_tag')) {
            Schema::create('baba_favorites_favorite_tag', function ($table) {
                $table->engine = 'InnoDB';
                $table->integer('favorite_id')->unsigned();
                $table->integer('tag_id')->unsigned();
                $table->primary(['favorite_id', 'tag_id']);
            });
        }

        $this->copyLegacyFavorites();
    }

    protected function copyLegacyFavorites()
    {
        if (!Schema::hasTable('baba_favorites_favorites')) {
            return;
        }

        $rows = \Db::table('baba_favorites_favorites')->get();

        foreach ($rows as $row) {
            $type = (string) ($row->favoriteable_type ?? '');
            $targetId = (int) ($row->favoriteable_id ?? 0);
            $userId = (int) ($row->user_id ?? 0);
            $owner = $userId > 0
                ? hash_hmac('sha256', 'user:' . $userId, (string) config('app.key'))
                : hash_hmac('sha256', 'legacy:' . $row->user_id . ':' . $type . ':' . $targetId, (string) config('app.key'));

            $title = class_basename($type) . ($targetId ? ' #' . $targetId : '');
            $url = '';

            if ($type && $targetId && class_exists($type)) {
                try {
                    $target = $type::find($targetId);
                    if ($target) {
                        $title = (string) ($target->title ?? $target->name ?? $title);
                        $url = (string) ($target->url ?? '');
                    }
                } catch (\Throwable $e) {
                    // Keep the legacy label if the target cannot be loaded.
                }
            }

            \Db::table('baba_favorites_items')->insertOrIgnore([
                'user_id' => $userId > 0 ? $userId : null,
                'owner_key' => $owner,
                'item_key' => sha1($type . ':' . $targetId),
                'target_type' => $type ?: null,
                'target_id' => $targetId ?: null,
                'title' => mb_substr($title, 0, 255),
                'url' => mb_substr($url, 0, 1024),
                'excerpt' => null,
                'image_url' => null,
                'group_id' => null,
                'notes' => null,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('baba_favorites_favorite_tag');
        Schema::dropIfExists('baba_favorites_items');
        Schema::dropIfExists('baba_favorites_tags');
        Schema::dropIfExists('baba_favorites_groups');
    }
}
