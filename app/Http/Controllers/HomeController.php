<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }
    public function users()
    {
        $users = Client::all();
        return view('users', compact('users'));
    }
    public function user(User $id)
    {
        $user = $id;
        $user = User::find($user->id);
        return view('user', compact('user'));
    }
    public function client(Client $id)
    {
        $user = $id;
        $user = Client::find($user->id);
        return view('user', compact('user'));
    }

    public function delete(Client $id)
    {
        $user = $id;
        $user = Client::find($user->id);
        $user->delete();
        $users = Client::all();
        return view('users', compact('users'));
    }
}
