<?php

namespace App\Policies;

use App\Models\LicenseTemplate;
use App\Models\User;

class LicenseTemplatePolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, LicenseTemplate $template): bool { return true; }
    public function create(User $user): bool { return true; }
    public function update(User $user, LicenseTemplate $template): bool { return true; }
    public function delete(User $user, LicenseTemplate $template): bool { return true; }
}
