<?php

namespace App\Policies;

use App\Models\SavedLocation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SavedLocationPolicy
{
    /**
     * Determine whether the user can delete the saved location.
     *
     * Another user's location answers 404 so its existence is not revealed.
     */
    public function delete(User $user, SavedLocation $savedLocation): Response
    {
        return $savedLocation->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
