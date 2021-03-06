<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\User;
use Cartalyst\Sentinel\Laravel\Facades\Activation;
use Exception;
use Laravel\Socialite\Facades\Socialite;
use Redirect;
use Sentinel;

class FacebookAuthController extends Controller
{
    public function redirectToProvider()
    {
        info('provider');
        return Socialite::driver('facebook')->redirect();
    }

    public function handleProviderCallback()
    {
        info('callback');
        try {
            info('try');
            $user = Socialite::driver('facebook')->user();

        } catch (Exception $e) {
            info('catch :' . $e);
            info($e->getMessage());
            return $this->sendFailedResponse($e->getMessage());
        }
        info($user->email);
        $array = User::withTrashed()->where([
            ['email', '=', $user->email],
            ['deleted_at', '!=', null]
        ])->get();
        return $array->isEmpty()
            ? $this->findOrCreateUser($user, 'facebook')
            : $this->sendFailedResponse("You are banned.");
    }

    protected function sendFailedResponse($msg = null)
    {
        info('failed');
        return redirect('login')->with(['msg' => $msg ?: 'Unable to login, try with another provider to login.']);
    }

    public function findOrCreateUser($providerUser, $provider)
    {
        $name = $providerUser->name;
        $splitName = explode(' ', $name);
        $first_name = '';
        $last_name = $splitName[count($splitName) - 1];
        for ($i = 0; $i <= count($splitName) - 2; $i++) {
            $first_name = $first_name . $splitName[$i] . ' ';
        }
        // check for already has account
        $user = User::where('email', $providerUser->email)->first();

        // if user already found
        if (!$user) {
            $user = User::create([
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $providerUser->email,
                'pic' => $providerUser->avatar,
                'gender' => $providerUser->user['gender'],
                'provider' => $provider,
                'provider_id' => $providerUser->id
            ]);
        }

        if (Activation::completed($user) == false) {

            $activation = Activation::create($user);
            Activation::complete($user, $activation->code);

        }
        Sentinel::login($user, true);

        if (Sentinel::authenticate($user)) {
            return $this->sendSuccessResponse();
        }


    }

    protected function sendSuccessResponse()
    {
        return Redirect::route('my-account')->withInput()->with('success', 'Please update Password');

    }
}