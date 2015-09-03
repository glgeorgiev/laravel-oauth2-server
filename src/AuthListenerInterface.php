<?php namespace GLGeorgiev\LaravelOAuth2Server;

interface AuthListenerInterface {
    public function userHasLoggedIn();
    public function userHasLoggedOut();
}