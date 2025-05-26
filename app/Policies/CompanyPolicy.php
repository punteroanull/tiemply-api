<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CompanyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user)
    {
        // Cualquier usuario autenticado puede ver empresas
        if ($user->isAdmin() || $user->hasRole('Manager')) {
            return true;
        } 
        return false;
    }

    /**
     * Determine whether the user can view.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Company  $company
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Company $company)
    {
        // Los administradores pueden ver cualquier empresa
        if ($user->isAdmin()) {
            return true;
        }

        // Los usuarios pueden ver las empresas a las que pertenecen
        return $user->belongsToCompany($company->id);
    }

    /**
     * Determine whether the user can create.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        // Solo los administradores pueden crear empresas
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Company  $company
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Company $company)
    {
        // Los administradores pueden actualizar cualquier empresa
        if ($user->isAdmin()) {
            return true;
        }

        // Los gerentes pueden actualizar las empresas a las que pertenecen
        if ($user->isManager() && $user->belongsToCompany($company->id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Company  $company
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Company $company)
    {
        // Solo los administradores pueden eliminar empresas
        return $user->isAdmin();
    }
}
