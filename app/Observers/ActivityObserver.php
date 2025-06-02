<?php

namespace App\Observers;

use App\Jobs\SendActivityToElasticsearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityObserver
{
    public function created(Model $model)
    {
        $this->logActivity('created', $model);
    }

    public function updated(Model $model)
    {
        $this->logActivity('updated', $model);
    }

    public function deleted(Model $model)
    {
        $this->logActivity('deleted', $model);
    }
    public function restored(Model $model): void
    {
        $this->logActivity('restored', $model);
    }

    /**
     * Log model activity to Elasticsearch via queue
     */
    protected function logActivity(string $event, Model $model): void
    {
        if ($this->shouldSkipLogging($model, $event)) {
            return;
        }

        $activityData = $this->buildActivityData($event, $model);
        $job = (new SendActivityToElasticsearch($activityData))
            ->onQueue(config('activity_logging.queue'));

        dispatch($job);
    }

    /**
     * Determine if logging should be skipped
     */
    private function shouldSkipLogging(Model $model, string $event): bool
    {
        // Skip if running in console and not explicitly enabled
        if (app()->runningInConsole() && !config('activity_logging.log_console_events', false)) {
            return true;
        }

        // Skip for certain model types or events
        return false;
    }

    private function buildActivityData(string $event, Model $model): array
    {
        return [
            'id' => $this->generateActivityId($event, $model),
            'model' => get_class($model),
            'model_id' => (string) $model->getKey(),
            'event' => $event,
            'user' => $this->getAuthenticatedUserInfo(),
            'attributes' => $this->sanitizeAttributes($model->getAttributes()),
            'changes' => $this->getModelChanges($model, $event),
            'context' => $this->getRequestContext(),
            'metadata' => $this->getMetadata(),
            'tags' => $this->generateTags($event, $model),
            'timestamp' => now()->toIso8601String()
        ];
    }
    private function generateActivityId(string $event, Model $model): string
    {
        return Str::uuid()->toString();
    }

    private function sanitizeAttributes(array $attributes): array
    {
        $sensitiveFields = config('activity_logging.sensitive_fields', [
            'password', 'password_confirmation', 'remember_token', 'api_token',
            'secret', 'private_key', 'credit_card_number', 'ssn'
        ]);

        foreach ($attributes as $key => $value) {
            // Handle UUID objects
            if ($value instanceof \Ramsey\Uuid\UuidInterface) {
                $attributes[$key] = $value->toString();
            }

            // Sanitize sensitive fields
            if (in_array($key, $sensitiveFields)) {
                $attributes[$key] = '[REDACTED]';
            }

            // Handle large text fields
            if (is_string($value) && strlen($value) > 10000) {
                $attributes[$key] = substr($value, 0, 1000) . '... [TRUNCATED]';
            }
        }

        return $attributes;
    }

    private function getModelChanges(Model $model, string $event): array
    {
        // Make ignore fields configurable
        $ignoreFields =  config('activity_logging.ignore_fields', []);

        switch ($event) {
            case 'created':
                return $this->getCreatedChanges($model, $ignoreFields);
            case 'deleted':
                return $this->getDeletedChanges($model, $ignoreFields);
            case 'updated':
                return $this->getUpdatedChanges($model, $ignoreFields);
            default:
                return [];
        }
    }

    private function getCreatedChanges(Model $model, array $ignoreFields): array
    {
        return collect($this->sanitizeAttributes($model->getAttributes()))
            ->reject(fn($value, $field) => $this->shouldIgnoreField($field, $ignoreFields))
            ->map(fn($value, $field) => [
                'field' => $field,
                'before' => null,
                'after' => $this->formatValue($value),
            ])
            ->values()
            ->toArray();
    }

    private function getDeletedChanges(Model $model, array $ignoreFields): array
    {
        return collect($this->sanitizeAttributes($model->getOriginal()))
            ->reject(fn($value, $field) => $this->shouldIgnoreField($field, $ignoreFields))
            ->map(fn($value, $field) => [
                'field' => $field,
                'before' => $this->formatValue($value),
                'after' => null,
            ])
            ->values()
            ->toArray();
    }

    private function getUpdatedChanges(Model $model, array $ignoreFields): array
    {
        $changes = $model->getChanges();
        $original = $model->getOriginal();

        return collect($changes)
            ->reject(fn($value, $field) => $this->shouldIgnoreField($field, $ignoreFields))
            ->map(fn($newValue, $field) => [
                'field' => $field,
                'before' => $this->formatValue($original[$field] ?? null),
                'after' => $this->formatValue($newValue),
            ])
            ->values()
            ->toArray();
    }

    private function shouldIgnoreField(string $field, array $ignoreFields): bool
    {
        return in_array($field, $ignoreFields) ||
            str_contains($field, 'password') ||
            str_contains($field, 'token');
    }

    private function formatValue($value)
    {
        // Handle different data types consistently
        if (is_null($value)) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        // Truncate very long strings
        if (is_string($value) && strlen($value) > 1000) {
            return substr($value, 0, 1000) . '...';
        }

        return $value;
    }

    private function getRequestContext(): array
    {
        if (!request()) {
            return [
                'source' => 'console',
                'command' => $_SERVER['argv'][1] ?? 'unknown'
            ];
        }

        return [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'request_id' => request()->header('X-Request-ID') ?? Str::uuid()->toString(),
            'session_id' => request()->hasSession() ? request()->session()->getId() : null,
            'route' => request()->route()?->getName(),
            'method' => request()->method(),
            'url' => request()->fullUrl(),
            'referrer' => request()->header('referer')
        ];
    }

    /**
     * Get application metadata
     */
    private function getMetadata(): array
    {
        return [
            'environment' => config('app.env'),
            'application_version' => config('app.version', '1.0.0'),
            'database_transaction_id' => $this->getCurrentTransactionId(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version()
        ];
    }
    private function generateTags(string $event, Model $model): array
    {
        $tags = [
            config('app.env'),
            $event,
            'model:' . strtolower(class_basename($model)),
        ];

        // Add time-based tags
        $now = now();
        $tags[] = 'hour:' . $now->format('H');
        $tags[] = 'day:' . $now->format('N'); // 1-7 for Monday-Sunday

        // Add user type tag if available
        $user = $this->getAuthenticatedUserInfo();
        if ($user) {
            $tags[] = 'user_type:' . $user['type'];
        } else {
            $tags[] = 'anonymous';
        }

        // Add source tag
        $tags[] = app()->runningInConsole() ? 'console' : 'web';

        return array_unique($tags);
    }

    /**
     * Get current database transaction ID if available
     */
    private function getCurrentTransactionId(): ?string
    {
        // This is a simplified example - implement based on your database setup
        return null;
    }

    /**
     * Here, you can modify this to use your specific authentication guards or retrieve different user types.    */
    private function getAuthenticatedUserInfo() {
        $guards = [
            'vendors' => function($user) {
                return [
                    'id' => (string) $user->id,
                    'type' => 'vendor',
                    'company' => $user->company_name,
                    'contact' => $user->contact_email
                ];
            },
            'moderators' => function($user) {
                return [
                    'id' => (string) $user->id,
                    'type' => 'moderator',
                    'nickname' => $user->nickname,
                    'level' => $user->moderator_level
                ];
            },
            'support_agents' => function($user) {
                return [
                    'id' => (string) $user->id,
                    'type' => 'support_agent',
                    'department' => $user->department,
                    'shift' => $user->shift
                ];
            },
        ];

        foreach ($guards as $guard => $formatter) {
            if (auth($guard)->check()) {
                return $formatter(auth($guard)->user());
            }
        }
        return null;
    }

}