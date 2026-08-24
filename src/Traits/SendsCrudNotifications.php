<?php

declare(strict_types=1);

namespace Ptah\Traits;

use Illuminate\Database\Eloquent\Model;
use Ptah\Livewire\BaseCrud\CrudConfig;
use Ptah\Services\Notification\CrudNotificationDispatcher;

/**
 * Opt-in trait that turns a model's created/updated/deleted events into
 * config-driven notifications (see the CrudConfig editor's Notifications
 * tab). Without this trait, notification rules configured for a model are
 * simply never fired — {@see CrudConfig::notificationTraitMissing()}
 * warns about exactly that in the editor.
 *
 * Usage:
 *   use Ptah\Traits\SendsCrudNotifications;
 *   class Product extends Model {
 *       use SendsCrudNotifications;
 *   }
 *
 * Same "boot{TraitName}" idiom as {@see HasAuditFields}. All the actual work
 * (config lookup, placeholder resolution, queueing) lives in
 * {@see CrudNotificationDispatcher}, resolved from the container so the
 * dispatcher stays a normal, testable singleton rather than a static call.
 */
trait SendsCrudNotifications
{
    public static function bootSendsCrudNotifications(): void
    {
        static::created(function (Model $model) {
            app(CrudNotificationDispatcher::class)->dispatch($model, 'created');
        });

        static::updated(function (Model $model) {
            app(CrudNotificationDispatcher::class)->dispatch($model, 'updated');
        });

        static::deleted(function (Model $model) {
            app(CrudNotificationDispatcher::class)->dispatch($model, 'deleted');
        });
    }
}
