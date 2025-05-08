<?php

namespace App\Policies;

use App\Models\AbsenceType;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AbsenceTypePolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        // Cualquier usuario autenticado puede ver los tipos de ausencia
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\AbsenceType  $absenceType
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, AbsenceType $absenceType)
    {
        // Cualquier usuario autenticado puede ver los tipos de ausencia
        return true;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        // Solo los administradores pueden crear tipos de ausencia
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\AbsenceType  $absenceType
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, AbsenceType $absenceType)
    {
        // Solo los administradores pueden actualizar tipos de ausencia
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\AbsenceType  $absenceType
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, AbsenceType $absenceType)
    {
        // Solo los administradores pueden eliminar tipos de ausencia
        if (!$user->isAdmin()) {
            return false;
        }
        
        // No se pueden eliminar tipos con ausencias o solicitudes asociadas
        if ($absenceType->absences()->exists() || $absenceType->absenceRequests()->exists()) {
            return false;
        }
        
        return true;
    }
}
