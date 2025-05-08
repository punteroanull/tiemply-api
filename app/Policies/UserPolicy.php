<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
     /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        // Solo los administradores pueden ver todos los usuarios
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\User  $model
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, User $model)
    {
        // El usuario puede ver su propio perfil
        if ($user->id === $model->id) {
            return true;
        }

        // Los administradores pueden ver cualquier usuario
        if ($user->isAdmin()) {
            return true;
        }

        // Los gerentes pueden ver a los usuarios que pertenecen a sus empresas
        if ($user->isManager()) {
            $userCompanyIds = $user->companies->pluck('id')->toArray();
            $modelCompanyIds = $model->companies->pluck('id')->toArray();
            
            // Si hay alguna coincidencia entre las empresas
            return count(array_intersect($userCompanyIds, $modelCompanyIds)) > 0;
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
        // Solo los administradores pueden crear usuarios
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\User  $model
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, User $model)
    {
        // El usuario puede actualizar su propio perfil
        if ($user->id === $model->id) {
            return true;
        }

        // Los administradores pueden actualizar cualquier usuario
        if ($user->isAdmin()) {
            return true;
        }

        // Los gerentes pueden actualizar a los usuarios que pertenecen a sus empresas y que no son administradores
        if ($user->isManager() && !$model->isAdmin()) {
            $userCompanyIds = $user->companies->pluck('id')->toArray();
            $modelCompanyIds = $model->companies->pluck('id')->toArray();
            
            // Si hay alguna coincidencia entre las empresas
            return count(array_intersect($userCompanyIds, $modelCompanyIds)) > 0;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\User  $model
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, User $model)
    {
        // Los usuarios no pueden eliminarse a sí mismos
        if ($user->id === $model->id) {
            return false;
        }

        // Solo los administradores pueden eliminar usuarios
        return $user->isAdmin();
    }
}
