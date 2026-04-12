<?php

namespace App\Service;

use App\Helpers\GetCurrentUserHelper;
use App\Helpers\ResponseHelper;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use OwenIt\Auditing\Models\Audit;
use App\QueryBuilders\AuditLogQueryBuilder;

class AuditLoggerService
{
    protected GetCurrentUserHelper $getCurrentUserHelper;
    protected AuditLogQueryBuilder $auditLogQueryBuilder;

    public function __construct(GetCurrentUserHelper $getCurrentUserHelper, AuditLogQueryBuilder $auditLogQueryBuilder)
    {
        $this->getCurrentUserHelper = $getCurrentUserHelper;
        $this->auditLogQueryBuilder = $auditLogQueryBuilder;
    }


    // Get all audit logs
    public function getAllAudits(Request $request)
    {
        try {
            return $this->auditLogQueryBuilder->auditBuilder($request);
        }catch (Exception $e) {
            return ResponseHelper::error('Failed to fetch audit logs: ', 500, $e->getMessage());
        }
    }

    
    public function getAuditById(int $id)
    {
        try {
            $audit = Audit::with(['user'])->findOrFail($id);
            return ResponseHelper::success($audit, 'Audit log fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Audit log not found', 404, $e->getMessage());
        }
    }



    public function logChange(
        string $event,
        string $auditableType,
        int $auditableId,
        array $old,
        array $new,
        ?int $userId = null,
        array $meta = []
    ): void {
        $resolvedUserId = $userId ?? $this->resolveUserId();
        $auditModelClass = config('audit.implementation', Audit::class);

        /** @var \Illuminate\Database\Eloquent\Model $audit */
        $audit = new $auditModelClass();
        $audit->forceFill([
            'event' => $event,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $old,
            'new_values' => $new,
            'url' => request()?->fullUrl(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'tags' => $this->buildTags($meta),
            'user_type' => $resolvedUserId ? config('auth.providers.users.model') : null,
            'user_id' => $resolvedUserId,
        ]);

        $audit->save();
    }

    public function logDiff(
        string $event,
        string $auditableType,
        int $auditableId,
        array $oldSnapshot,
        array $newSnapshot,
        ?int $userId = null,
        array $meta = []
    ): void {
        [$oldDiff, $newDiff] = $this->extractDiff($oldSnapshot, $newSnapshot);
        $this->logChange($event, $auditableType, $auditableId, $oldDiff, $newDiff, $userId, $meta);
    }

    public function extractDiff(array $old, array $new): array
    {
        $oldDiff = [];
        $newDiff = [];

        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($keys as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            if ($oldValue != $newValue) {
                $oldDiff[$key] = $oldValue;
                $newDiff[$key] = $newValue;
            }
        }

        return [$oldDiff, $newDiff];
    }

    public function snapshotModel(Model|array|null $source, array $only = []): array
    {
        if ($source === null) {
            return [];
        }

        $data = is_array($source) ? $source : $source->toArray();

        if (!empty($only)) {
            $data = array_intersect_key($data, array_flip($only));
        }

        return $this->normalizeValues($data);
    }

    protected function normalizeValues(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->normalizeValues($value);
                continue;
            }

            if (is_object($value) && method_exists($value, 'value')) {
                $values[$key] = $value->value;
            }
        }

        return $values;
    }

    protected function resolveUserId(): ?int
    {
        $userId = $this->getCurrentUserHelper->getUserId();
        return $userId !== null ? (int) $userId : null;
    }

    protected function buildTags(array $meta): ?string
    {
        if (empty($meta)) {
            return null;
        }

        return json_encode($meta, JSON_UNESCAPED_UNICODE);
    }
}
