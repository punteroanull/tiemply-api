<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Employee;
use App\Models\WorkLog;
use Illuminate\Auth\Access\Response;

class WorkLogPolicy
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
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Employee  $workLog
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Worklog $worklog)
    {        
        if ($worklog->employee) {
            // El usuario puede ver sus propios registros de trabajo
            if ($user->id === $worklog->employee->user_id) {
                return true;
            }
        }

        // Los administradores pueden ver cualquier registro de trabajo
        if ($user->isAdmin()) {
            return true;
        }

        // Los gerentes pueden ver los registros de trabajo de los empleados de sus empresas
        if ($user->isManager() && $worklog->employee) {
            $userCompanyIds = $user->companiesEloquent->pluck('id')->toArray();
            
            return in_array($worklog->employee->company_id, $userCompanyIds);
        }

        return false;
        
        //return true; //No consigo hacer funcionar la política de permisos para el modelo WorkLog
    }
    public function viewEmployeeWorkLogs(User $user, Employee $employee)
    {
        dump($employee);
        // El usuario puede ver sus propios registros de trabajo
        if ($user->id === $employee->user_id) {
            return true;
        }

        // Los administradores pueden ver cualquier registro de trabajo
        if ($user->isAdmin()) {
            return true;
        }

        // Los gerentes pueden ver los registros de trabajo de los empleados de sus empresas
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
        // Cualquier usuario autenticado puede crear registros de trabajo
        return true;
    }

    /**
     * Determine if the user can create a work log for a specific employee.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Employee  $employee
     * @return bool
     */
    public function createFor(User $user, WorkLog $worklog)
    {
        // El usuario puede crear registros para sí mismo
        if ($user->id === $worklog->employee->user_id) {
            return true;
        }
        
        // Los administradores pueden crear registros para cualquier empleado
        if ($user->isAdmin()) {
            return true;
        }
        
        // Los gerentes pueden crear registros para los empleados de sus empresas
        if ($user->isManager()) {
            $userCompanyIds = $user->companiesEloquent->pluck('id')->toArray();
            
            return in_array($worklog->employee->company_id, $userCompanyIds);
        }
        
        return false;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\WorkLog  $workLog
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, WorkLog $worklog)
    {
        // Los administradores pueden actualizar cualquier registro de trabajo
        if ($user->isAdmin()) {
            return true;
        }

        // Los gerentes pueden actualizar los registros de trabajo de los empleados de sus empresas
        if ($user->isManager() && $worklog->employee) {
            $userCompanyIds = $user->companiesEloquent->pluck('id')->toArray();
            
            return in_array($worklog->employee->company_id, $userCompanyIds);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\WorkLog  $workLog
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, WorkLog $worklog)
    {
        // Los administradores pueden eliminar cualquier registro de trabajo
        if ($user->isAdmin()) {
            return true;
        }

        // Los gerentes pueden eliminar los registros de trabajo de los empleados de sus empresas
        if ($user->isManager() && $worklog->employee) {
            $userCompanyIds = $user->companiesEloquent->pluck('id')->toArray();
            
            return in_array($worklog->employee->company_id, $userCompanyIds);
        }

        return false;
    }
}
