<?php

namespace App\Policies;

use App\Models\Absence;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AbsencePolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        // Los administradores pueden actualizar cualquier ausencia
        if ($user->isAdmin()) {
            return true;
        }

        // Los gerentes pueden actualizar las ausencias de sus empresas
        if ($user->isManager()) {
            return $user->companiesEloquent()->exists();
        }

    }

    /**
     * Determine whether the user can view absences for a specific company.
     *
     * @param  \App\Models\User  $user
     * @param  string  $companyId
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAnyForCompany(User $user, $companyId)
    {
        // Administradores pueden ver ausencias de cualquier empresa
        if ($user->isAdmin()) {
            return true;
        }
        
        // Gerentes pueden ver ausencias de sus empresas
        if ($user->isManager() && $user->belongsToCompany($companyId)) {
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Absence  $absence
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Absence $absence)
    {
        // Verificar que la ausencia tiene un empleado asociado
        if ($absence->employee) {
            // El usuario puede ver sus propias ausencias
            if ($user->id === $absence->employee->user_id) {
                return true;
            }
        }

        // Los administradores pueden ver cualquier ausencia
        if ($user->isAdmin()) {
            return true;
        }

        // Los gerentes pueden ver las ausencias de los empleados de sus empresas
        if ($user->isManager() && $absence->employee) {
            $userCompanyIds = $user->companiesEloquent->pluck('id')->toArray();
            
            return in_array($absence->employee->company_id, $userCompanyIds);
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        // Los administradores y gerentes pueden crear ausencias
        return $user->isAdmin() || $user->isManager();
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Absence  $absence
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Absence $absence)
    {
        // No se pueden actualizar ausencias vinculadas a solicitudes
        /*
        if ($absence->request_id) {
            return false;
        }
        */
        // Los administradores pueden actualizar cualquier ausencia
        if ($user->isAdmin()) {
            return true;
        }

        // Los gerentes pueden actualizar las ausencias de los empleados de sus empresas
        if ($user->isManager()) {
            $userCompanyIds = $user->companiesEloquent->pluck('id')->toArray();
            
            return in_array($absence->employee->company_id, $userCompanyIds);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Absence  $absence
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Absence $absence)
    {
        // No se pueden eliminar ausencias vinculadas a solicitudes
        if ($absence->request_id) {
            return false;
        }
        
        // Los administradores pueden eliminar cualquier ausencia
        if ($user->isAdmin()) {
            return true;
        }

        // Los gerentes pueden eliminar las ausencias de los empleados de sus empresas
        if ($user->isManager() && $absence->employee) {
            $userCompanyIds = $user->companiesEloquent->pluck('id')->toArray();
            
            return in_array($absence->employee->company_id, $userCompanyIds);
        }

        return false;
    }
}
