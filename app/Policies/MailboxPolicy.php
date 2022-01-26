<?php

namespace App\Policies;

use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Enums\UserRole;

class MailboxPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Mailbox  $mailbox
     * @return mixed
     */
    public function view(User $user, Mailbox $mailbox)
    {
        if (empty($mailbox)) return false;

        if ($user->role_id === UserRole::TUTOR) {
            return isset($mailbox->tutor_id) && $mailbox->tutor_id === $user->id;
        }

        if ($user->role_id === UserRole::ADMINISTRATOR) {
            return isset($mailbox->admin_id) && $mailbox->admin_id === $user->id;
        }
        
        return isset($mailbox->student_id) && $user->id === $mailbox->student_id;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        //
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Mailbox  $mailbox
     * @return mixed
     */
    public function update(User $user, Mailbox $mailbox)
    {
        return $this->view($user, $mailbox);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Mailbox  $mailbox
     * @return mixed
     */
    public function delete(User $user, Mailbox $mailbox)
    {
        return $this->view($user, $mailbox);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Mailbox  $mailbox
     * @return mixed
     */
    public function restore(User $user, Mailbox $mailbox)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Mailbox  $mailbox
     * @return mixed
     */
    public function forceDelete(User $user, Mailbox $mailbox)
    {
        //
    }
}
