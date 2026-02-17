<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class WhaleController extends Controller
{
    public function index()
    {
        return Inertia::render('Whales', [
            'whales' => [],
        ]);
    }
}
