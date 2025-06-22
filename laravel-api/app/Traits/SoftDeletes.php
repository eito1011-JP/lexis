<?php

declare(strict_types=1);

namespace App\Traits;

use App\Consts\Flag;
use App\Scopes\SoftDeletingScope;
use Illuminate\Database\Eloquent\SoftDeletes as TimestampsSoftDelete;

/**
 * update 'is_deleted' column trait.
 */
trait SoftDeletes
{
    use TimestampsSoftDelete;

    /**
     * Invalidate initialize.
     */
    public function initializeSoftDeletes(): void {}

    /**
     * Overwrite boot process.
     */
    public static function bootSoftDeletes(): void
    {
        static::addGlobalScope(new SoftDeletingScope);
    }

    /**
     *  Overwrite getDeletedAtColumn method to get 'is_deleted' column method.
     */
    public function getDeletedAtColumn()
    {
        return 'is_deleted';
    }

    /**
     * Overwrite restore a soft-deleted instance method.
     *
     * @return null|bool
     */
    public function restore()
    {
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $this->{$this->getDeletedAtColumn()} = Flag::FALSE;

        $this->deleted_at = null;

        $this->exists = true;

        $result = $this->save();

        $this->fireModelEvent('restored', false);

        return $result;
    }

    /**
     * Overwrite trashed.
     *
     * @return bool
     */
    public function trashed()
    {
        return $this->{$this->getDeletedAtColumn()} == Flag::TRUE;
    }

    /**
     * Overwrite SoftDelete.
     */
    protected function runSoftDelete(): void
    {
        $query = $this->setKeysForSaveQuery($this->newModelQuery());

        $columns = [$this->getDeletedAtColumn() => Flag::TRUE, 'deleted_at' => now()];

        $this->{$this->getDeletedAtColumn()} = Flag::TRUE;

        $query->update($columns);
    }
}
