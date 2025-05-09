<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EmployeePolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        // Los administradores pueden ver cualquier empleado
        if ($user->isAdmin()) {
            return true;
        }

        // Los gerentes pueden ver a los empleados de sus empresas
        if ($user->isManager()) {
            return $user->companiesEloquent()->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Employee  $employee
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Employee $employee)
    {
        // El usuario puede ver su propio registro de empleado
        if ($user->id === $employee->user_id) {
            return true;
        }

        // Los administradores pueden ver cualquier empleado
        if ($user->isAdmin()) {
            return true;
        }

        // Los gerentes pueden ver a los empleados de sus empresas
        if ($user->isManager()) {
            $userCompanyIds = $user->companiesEloquent->pluck('id')->toArray();
            
            return in_array($employee->company_id, $userCompanyIds);
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
        // Los administradores y gerentes pueden crear empleados
        return $user->isAdmin() || $user->isManager();
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Employee  $employee
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Employee $employee)
    {
        // Los administradores pueden actualizar cualquier empleado
        if ($user->isAdmin()) {
            return true;
        }

        // Los gerentes pueden actualizar a los empleados de sus empresas
        if ($user->isManager()) {
            $userCompanyIds = $user->companiesEloquent->pluck('id')->toArray();
            
            return in_array($employee->company_id, $userCompanyIds);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Employee  $employee
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Employee $employee)
    {
        // Los administradores pueden eliminar cualquier empleado
        if ($user->isAdmin()) {
            return true;
        }

        // Los gerentes pueden eliminar a los empleados de sus empresas
        if ($user->isManager()) {
            $userCompanyIds = $user->companiesEloquent->pluck('id')->toArray();
            
            return in_array($employee->company_id, $userCompanyIds);
        }

        return false;
    }
}
