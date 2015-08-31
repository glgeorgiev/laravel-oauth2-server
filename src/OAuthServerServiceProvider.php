<?php namespace GLGeorgiev\LaravelOAuth2Server;

use Auth;
use Config;
use DB;
use Event;
use Exception;
use League\OAuth2\Server\Exception\AccessDeniedException;
use League\OAuth2\Server\Exception\InvalidRequestException;
use Request;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Entity\AccessTokenEntity;

/**
 * Class OAuthServerServiceProvider
 * @author Georgi Georgiev georgi.georgiev@delta.bg
 * @package GLGeorgiev\LaravelOAuth2Server\OAuthServerServiceProvider
 */
class OAuthServerServiceProvider extends ServiceProvider
{

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

        $this->authorizeRoute($router, $authorizationServer);

        $this->accessTokenRoute($router, $authorizationServer);

        $this->userDetailsRoute($router, $resourceServer);
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
     * @return \Response
     */
    private function authorizeRoute(Router $router, AuthorizationServer $authorizationServer)
    {
        $router->get(Config::get('laravel-oauth2-server.authorize_path'), function() use($authorizationServer) {
            try {
                $authParams = $authorizationServer->getGrantType('authorization_code')->checkAuthorizeParams();
                if (Auth::check()) {
                    $redirectUri = $authorizationServer->getGrantType('authorization_code')
                        ->newAuthorizeRequest('user', Auth::id(), $authParams);
                    if (Request::input('target_url')) {
                        $redirectUri .= '&target_url=' . Request::input('target_url');
                    }
                    return redirect($redirectUri);
                }
                if (Request::input('auth_checkup') && Request::input('target_url')) {
                    return redirect(Request::input('target_url'));
                }
                if (Config::get('laravel-oauth2-server.login_is_route')) {
                    return redirect(route(Config::get('laravel-oauth2-server.login_route')) .
                        '?target_url=' . Request::input('target_url'));
                }
                return redirect(Config::get('laravel-oauth2-server.login_path') .
                    '?target_url=' . Request::input('target_url'));
            } catch (Exception $e) {
                die('Wrong authorize parameters!');
            }
        });
    }

    /**
     * The route responsible for issuing access token
     *
     * @param Router $router
     * @param AuthorizationServer $authorizationServer
     * @return \Response
     */
    private function accessTokenRoute(Router $router, AuthorizationServer $authorizationServer)
    {
        $router->post(Config::get('laravel-oauth2-server.access_token_path'), function () use ($authorizationServer) {
            try {
                $response = $authorizationServer->issueAccessToken();
                return response()->json($response);
            } catch (Exception $e) {
                return response()->json([
                    'error'     => $e->getCode(),
                    'message'   => $e->getMessage(),
                ], 500);
            }
        });
    }

    /**
     * The route responsible for giving user information
     *
     * @param Router $router
     * @param ResourceServer $resourceServer
     * @return \Response
     */
    private function userDetailsRoute(Router $router, ResourceServer $resourceServer)
    {
        $router->get(Config::get('laravel-oauth2-server.user_details_path'), function () use ($resourceServer) {
            try {
                $accessToken = new AccessTokenEntity($resourceServer);
                $accessToken->setId(Request::input('access_token'));

                $resourceServer->isValidRequest(false, $accessToken);

                $session = $resourceServer->getSessionStorage()->getByAccessToken($accessToken);

                if (! ($session->getOwnerType() === 'user' &&
                    $resourceServer->getAccessToken()->hasScope('uid'))) {
                    throw new AccessDeniedException();
                }

                return response()->json(['id' => $session->getOwnerId()]);
            } catch (InvalidRequestException $ire) {
                return response()->json([
                    'error'     => $ire->getCode(),
                    'message'   => $ire->getMessage(),
                ], $ire->httpStatusCode);
            } catch (AccessDeniedException $acd) {
                return response()->json([
                    'error'     => $acd->getCode(),
                    'message'   => $acd->getMessage(),
                ], $acd->httpStatusCode);
            } catch (Exception $e) {
                return response()->json([
                    'error'     => $e->getCode(),
                    'message'   => $e->getMessage(),
                ], 500);
            }
        });
    }

}