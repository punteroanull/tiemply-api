<?php

namespace App\Policies;

use App\Models\AbsenceRequest;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Auth\Access\Response;

class AbsenceRequestPolicy
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
     * Determine whether the user can view solicitudes for a specific company.
     *
     * @param  \App\Models\User  $user
     * @param  string  $companyId
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAnyForCompany(User $user, $companyId)
    {
        // Administradores pueden ver solicitudes de cualquier empresa
        if ($user->isAdmin()) {
            return true;
        }
        
        // Gerentes pueden ver solicitudes de sus empresas
        if ($user->isManager() && $user->belongsToCompany($companyId)) {
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\AbsenceRequest  $absenceRequest
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, AbsenceRequest $absenceRequest)
    {
        // Verificar que la solicitud tiene un empleado asociado
        if ($absenceRequest->employee) {
            // El usuario puede ver sus propias solicitudes
            if ($user->id === $absenceRequest->employee->user_id) {
                return true;
            }
        }

        // Los administradores pueden ver cualquier solicitud
        if ($user->isAdmin()) {
            return true;
        }

        // Los gerentes pueden ver las solicitudes de los empleados de sus empresas
        if ($user->isManager() && $absenceRequest->employee) {
            $userCompanyIds = $user->companiesEloquent->pluck('id')->toArray();
            
            return in_array($absenceRequest->employee->company_id, $userCompanyIds);
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
        // Cualquier usuario autenticado puede crear solicitudes
        return true;
    }

    /**
     * Determine if the user can create a request for a specific employee.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Employee  $employee
     * @return bool
     */
    public function createFor(User $user, Employee $employee)
    {
        // El usuario puede crear solicitudes para sí mismo
        if ($user->id === $employee->user_id) {
            return true;
        }
        
        // Los administradores pueden crear solicitudes para cualquier empleado
        if ($user->isAdmin()) {
            return true;
        }
        
        // Los gerentes pueden crear solicitudes para los empleados de sus empresas
        if ($user->isManager()) {
            $userCompanyIds = $user->companiesEloquent->pluck('id')->toArray();
            
            return in_array($employee->company_id, $userCompanyIds);
        }
        
        return false;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\AbsenceRequest  $absenceRequest
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, AbsenceRequest $absenceRequest)
    {
        // Las solicitudes aprobadas o rechazadas no se pueden actualizar
        if (!$absenceRequest->isPending()) {
            return false;
        }
        
        // Verificar que la solicitud tiene un empleado asociado
        if ($absenceRequest->employee) {
            // El usuario puede actualizar sus propias solicitudes pendientes
            if ($user->id === $absenceRequest->employee->user_id) {
                return true;
            }
        }
        
        // Los administradores pueden actualizar cualquier solicitud
        if ($user->isAdmin()) {
            return true;
        }

        // Los managers pueden editar registros de empleados de sus empresas
        if ($user->hasRole('Manager')) {
            return $user->companies()->pluck('id')->contains($absenceRequest->employee->company_id);
        }

        return false;
    }

    /**
     * Determine whether the user can review the absence request.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\AbsenceRequest  $absenceRequest
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function review(User $user, AbsenceRequest $absenceRequest)
    {
        // Las solicitudes que no están pendientes no se pueden revisar
        if (!$absenceRequest->isPending()) {
            return false;
        }

        // Los administradores pueden revisar cualquier solicitud
        if ($user->isAdmin()) {
            return true;
        }
        
        // Verificar que la solicitud tiene un empleado asociado
        if ($absenceRequest->employee) {
            // El usuario no puede revisar sus propias solicitudes
            if ($user->id === $absenceRequest->employee->user_id) {
                return false;
            }
        }
        
        // Los gerentes pueden revisar las solicitudes de los empleados de sus empresas
        if ($user->isManager() && $absenceRequest->employee) {
            $userCompanyIds = $user->companiesEloquent->pluck('id')->toArray();
            
            return in_array($absenceRequest->employee->company_id, $userCompanyIds);
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\AbsenceRequest  $absenceRequest
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, AbsenceRequest $absenceRequest)
    {
        // Las solicitudes aprobadas o rechazadas no se pueden eliminar
        if (!$absenceRequest->isPending()) {
            return false;
        }
        
        // Verificar que la solicitud tiene un empleado asociado
        if ($absenceRequest->employee) {
            // El usuario puede eliminar sus propias solicitudes pendientes
            if ($user->id === $absenceRequest->employee->user_id) {
                return true;
            }
        }
        
        // Los administradores pueden eliminar cualquier solicitud pendiente
        if ($user->isAdmin()) {
            return true;
        }
        
        return false;
    }
}
