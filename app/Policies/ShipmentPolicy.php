<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Shipment $shipment): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        // A customer login with no customer record attached sees nothing.
        return $user->customer_id !== null && $user->customer_id === $shipment->customer_id;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin();
    }

    /** Uploading and removing documents. Customers can only read. */
    public function manageDocuments(User $user, Shipment $shipment): bool
    {
        return $user->isStaff();
    }

    public function viewCharges(User $user, Shipment $shipment): bool
    {
        return $user->isStaff() || $this->view($user, $shipment);
    }

    public function manageCharges(User $user, Shipment $shipment): bool
    {
        return $user->isStaff();
    }
}
