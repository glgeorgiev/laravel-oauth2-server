<?php namespace GLGeorgiev\LaravelOAuth2Server;

interface AuthListener {
    public function userHasLoggedIn();
    public function userHasLoggedOut();
}