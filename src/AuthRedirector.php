<?php namespace GLGeorgiev\LaravelOAuth2Server;

use Config;
use DB;
use Session;

class AuthRedirector implements AuthListener {

    public function userHasLoggedIn()
    {
        $redirect = Session::pull('login_redirect_url') ? : Config::get('laravel-oauth2-server.default_redirect');

        $clients = DB::table('oauth_clients')
            ->join('oauth_client_redirect_uris', 'oauth_clients.id', '=', 'oauth_client_redirect_uris.client_id')
            ->lists('redirect_uri');

        $url = $redirect;

        foreach ($clients as $uri) {
            $url = $uri . '?' . http_build_query([
                    'target_url' => $url,
                ]);
        }

        return redirect($url);
    }

    public function userHasLoggedOut()
    {
        $redirect = Session::pull('logout_redirect_url') ? : Config::get('laravel-oauth2-server.default_redirect');
        $clients = DB::table('oauth_clients')
            ->join('oauth_client_redirect_uris', 'oauth_clients.id', '=', 'oauth_client_redirect_uris.client_id')
            ->lists('logout_uri');

        $url = $redirect;

        foreach ($clients as $uri) {
            $url = $uri . '?' . http_build_query([
                    'target_url' => $url,
                ]);
        }

        return redirect($url);
    }

}