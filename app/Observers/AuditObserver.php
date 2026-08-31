<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Logs every create/update/delete on audited models with millisecond precision.
 */
class AuditObserver
{
    public function created(Model $model): void
    {
        ActivityLog::record('created', $model, $this->label($model), $this->montant($model), [
            'attributes' => $model->getAttributes(),
        ]);
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        ActivityLog::record('updated', $model, $this->label($model), $this->montant($model), [
            'changes' => $changes,
            'old' => array_intersect_key($model->getOriginal(), $changes),
        ]);
    }

    public function deleted(Model $model): void
    {
        ActivityLog::record('deleted', $model, $this->label($model), $this->montant($model), [
            'attributes' => $model->getOriginal(),
        ]);
    }

    private function label(Model $model): string
    {
        return class_basename($model).' #'.$model->getKey();
    }

    private function montant(Model $model): ?float
    {
        foreach (['montant', 'total_ttc'] as $field) {
            if (isset($model->{$field})) {
                return (float) $model->{$field};
            }
        }

        return null;
    }
}
