<?php namespace GLGeorgiev\LaravelOAuth2Server;

use Auth;
use Config;
use DB;
use Event;
use Exception;
use Request;

use Guzzle\Http\Client as GuzzleHttpClient;
use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
//use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Entity\AccessTokenEntity;

/**
 * Class OAuthServerServiceProvider
 * @author Georgi Georgiev georgi.georgiev@delta.bg
 * @package GLGeorgiev\LaravelOAuth2Server\OAuthServerServiceProvider
 */
class OAuthServerServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $configPath = __DIR__ . '/../config/laravel-oauth2-server.php';
        $this->mergeConfigFrom($configPath, 'laravel-oauth2-server');

        $this->app['command.oauth.client'] = $this->app->share(function() {
            return new Console\Commands\ClientCommand();
        });
        $this->commands('command.oauth.client');
    }

    /**
     * Bootstrap application services.
     *
     * @param Router $router
     */
    public function boot(Router $router)
    {
        $configPath = __DIR__ . '/../config/laravel-oauth2-server.php';
        $this->publishes([$configPath => config_path('laravel-oauth2-server.php')], 'config');
        $migrationPath = __DIR__ . '/../database/migrations/';
        $this->publishes([$migrationPath => database_path('migrations/')], 'migrations');

        $authorizationServer = new AuthorizationServer();
        $authorizationServer->setSessionStorage(new Storage\SessionStorage());
        $authorizationServer->setAccessTokenStorage(new Storage\AccessTokenStorage());
        $authorizationServer->setRefreshTokenStorage(new Storage\RefreshTokenStorage());
        $authorizationServer->setClientStorage(new Storage\ClientStorage());
        $authorizationServer->setScopeStorage(new Storage\ScopeStorage());
//        $authorizationServer->setAuthCodeStorage(new Storage\AuthCodeStorage());

//        $authCodeGrant = new AuthCodeGrant();
//        $authorizationServer->addGrantType($authCodeGrant);

        $clientCredentials = new ClientCredentialsGrant();
        $authorizationServer->addGrantType($clientCredentials);

        $refreshTokenGrant = new RefreshTokenGrant();
        $authorizationServer->addGrantType($refreshTokenGrant);

//        $resourceServer = new ResourceServer(
//            new Storage\SessionStorage(),
//            new Storage\AccessTokenStorage(),
//            new Storage\ClientStorage(),
//            new Storage\ScopeStorage()
//        );

//        $this->authorizeRoute($router, $authorizationServer);

        $this->accessTokenRoute($router, $authorizationServer);

//        $this->userDetailsRoute($router, $resourceServer);

        Event::listen('auth.login', function() {
            $results = DB::table('oauth_clients')
                ->select(['oauth_clients.id', 'oauth_clients.name', 'oauth_client_redirect_uris.redirect_uri'])
                ->join('oauth_client_redirect_uris', 'oauth_clients.id', '=', 'oauth_client_redirect_uris.client_id')
                ->get();

            $guzzleHttpClient = new GuzzleHttpClient();
            $requests = [];
            foreach ($results as $result) {
                $requests[] = $guzzleHttpClient->get($result->redirect_uri);
            }
            $guzzleHttpClient->send($requests);
        });

        Event::listen('auth.logout', function() {
            $results = DB::table('oauth_clients')
                ->select(['oauth_clients.id', 'oauth_clients.name', 'oauth_client_redirect_uris.logout_uri'])
                ->join('oauth_client_redirect_uris', 'oauth_clients.id', '=', 'oauth_client_redirect_uris.client_id')
                ->get();

            $guzzleHttpClient = new GuzzleHttpClient();
            $requests = [];
            foreach ($results as $result) {
                $requests[] = $guzzleHttpClient->get($result->logout_uri);
            }
            $guzzleHttpClient->send($requests);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['command.oauth.client'];
    }

    /**
     * The route responsible for giving auth code
     *
     * @param Router $router
     * @param AuthorizationServer $authorizationServer
     */
    private function authorizeRoute(Router $router, AuthorizationServer $authorizationServer)
    {
        $router->get(Config::get('laravel-oauth2-server.authorize_path'), function() use($authorizationServer) {
            try {
                $authParams = $authorizationServer->getGrantType('authorization_code')->checkAuthorizeParams();
                if (Auth::check()) {
                    $redirectUri = $authorizationServer->getGrantType('authorization_code')
                        ->newAuthorizeRequest('user', Auth::id(), $authParams);
                    return redirect($redirectUri);
                }
                if (Config::get('laravel-oauth2-server.login_is_route')) {
                    return redirect(route(Config::get('laravel-oauth2-server.login_route')));
                }
                return redirect(Config::get('laravel-oauth2-server.login_path'));
            } catch (Exception $e) {
                die('Wrong authorize parameters!');
            }
        });
    }

    /**
     * The route for issuing access token
     *
     * @param Router $router
     * @param AuthorizationServer $authorizationServer
     */
    private function accessTokenRoute(Router $router, AuthorizationServer $authorizationServer)
    {
        $router->post(Config::get('laravel-oauth2-server.access_token_path'), function () use ($authorizationServer) {
            try {
                $response = $authorizationServer->issueAccessToken();
                return response(json_encode($response));
            } catch (Exception $e) {
                die('Could not issue access token!');
            }
        });
    }

    /**
     * The route responsible for giving user information
     *
     * @param Router $router
     * @param ResourceServer $resourceServer
     */
    private function userDetailsRoute(Router $router, ResourceServer $resourceServer)
    {
        $router->get(Config::get('laravel-oauth2-server.user_details_path'), function () use ($resourceServer) {
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

}