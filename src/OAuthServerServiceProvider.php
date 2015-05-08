<?php namespace GLGeorgiev\LaravelOAuth2Server;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Entity\AccessTokenEntity;

use Auth;
use Config;
use Exception;
use Request;

class OAuthServerServiceProvider extends ServiceProvider {

    protected $defer = false;

    public function register()
    {
        $configPath = __DIR__ . '/../config/laravel-oauth2-server.php';
        $this->mergeConfigFrom($configPath, 'laravel-oauth2-server');
    }

    public function boot(Router $router)
    {
        $configPath = __DIR__ . '/../config/laravel-oauth2-server.php';
        $this->publishes([$configPath => config_path('laravel-oauth2-server.php')], 'config');

        $authorizationServer = new AuthorizationServer();
        $authorizationServer->setSessionStorage(new Storage\SessionStorage());
        $authorizationServer->setAccessTokenStorage(new Storage\AccessTokenStorage());
        $authorizationServer->setRefreshTokenStorage(new Storage\RefreshTokenStorage());
        $authorizationServer->setClientStorage(new Storage\ClientStorage());
        $authorizationServer->setScopeStorage(new Storage\ScopeStorage());
        $authorizationServer->setAuthCodeStorage(new Storage\AuthCodeStorage());

        $authCodeGrant = new AuthCodeGrant();
        $authorizationServer->addGrantType($authCodeGrant);

        $refreshTokenGrant = new RefreshTokenGrant();
        $authorizationServer->addGrantType($refreshTokenGrant);

        $resourceServer = new ResourceServer(
            new Storage\SessionStorage(),
            new Storage\AccessTokenStorage(),
            new Storage\ClientStorage(),
            new Storage\ScopeStorage()
        );

        $router->get('authorize', function() use($authorizationServer) {
            try {
                $authParams = $authorizationServer->getGrantType('authorization_code')->checkAuthorizeParams();
                if (Auth::check()) {
                    $redirectUri = $authorizationServer->getGrantType('authorization_code')
                        ->newAuthorizeRequest('user', Auth::id(), $authParams);
                    return redirect($redirectUri, 302);
                }
                if (Config::get('laravel-oauth2-server.login_is_route')) {
                    return redirect(route(Config::get('laravel-oauth2-server.login_route')));
                }
                return redirect(Config::get('laravel-oauth2-server.login_path'));    
            } catch (Exception $e) {
                die('Wrong authorize parameters!');
            }
        });

        $router->post('access_token', function () use ($authorizationServer) {
            try {
                $response = $authorizationServer->issueAccessToken();
                return response(json_encode($response), 200);
            } catch (Exception $e) {
                die('Could not issue access token!');
            }
        });

        $router->get('user_details', function () use ($resourceServer) {
            $accessTokenString = Request::input('access_token');
            $accessToken = new AccessTokenEntity($resourceServer);
            $accessToken->setId($accessTokenString);

            if (! $resourceServer->isValidRequest(false, $accessToken)) {
                die('The request is not valid!');
            }

            $session = $resourceServer->getSessionStorage()->getByAccessToken($accessToken);

            $model = Config::get('auth.model');
            $user = $model::find($session->getOwnerId());

            return response(json_encode(['uid' => $user->id, 'email' => $user->email]));
        });
    }

    public function provides()
    {
        return [];
    }

}
