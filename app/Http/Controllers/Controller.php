<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Autorise $this->authorize(...) dans les contrôleurs (policies).
    use AuthorizesRequests;
}
