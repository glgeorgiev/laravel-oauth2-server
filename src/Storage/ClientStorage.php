<?php namespace GLGeorgiev\LaravelOAuth2Server\Storage;

use DB;
use League\OAuth2\Server\Entity\ClientEntity;
use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Storage\AbstractStorage;
use League\OAuth2\Server\Storage\ClientInterface;

class ClientStorage extends AbstractStorage implements ClientInterface
{
    /**
     * {@inheritdoc}
     */
    public function get($clientId, $clientSecret = null, $redirectUri = null, $grantType = null)
    {
        $query = DB::table('oauth_clients')
                          ->select('oauth_clients.*')
                          ->where('oauth_clients.id', $clientId);

        if ($clientSecret !== null) {
            $query->where('oauth_clients.secret', $clientSecret);
        }

        if ($redirectUri) {
            $query->join('oauth_client_redirect_uris', 'oauth_clients.id', '=', 'oauth_client_redirect_uris.client_id')
                  ->select(['oauth_clients.*', 'oauth_client_redirect_uris.*'])
                  ->where('oauth_client_redirect_uris.redirect_uri', $redirectUri);
        }

        $result = $query->get();

        if (count($result) === 1) {
            $client = new ClientEntity($this->server);
            $client->hydrate([
                'id'    =>  $result[0]->client_id,
                'name'  =>  $result[0]->name,
            ]);

            return $client;
        }

        return;
    }

    /**
     * {@inheritdoc}
     */
    public function getBySession(SessionEntity $session)
    {
        $result = DB::table('oauth_clients')
                            ->select(['oauth_clients.id', 'oauth_clients.name'])
                            ->join('oauth_sessions', 'oauth_clients.id', '=', 'oauth_sessions.client_id')
                            ->where('oauth_sessions.id', $session->getId())
                            ->get();

        if (count($result) === 1) {
            $client = new ClientEntity($this->server);
            $client->hydrate([
                'id'    =>  $result[0]->id,
                'name'  =>  $result[0]->name,
            ]);

            return $client;
        }

        return;
    }

    public function getFirstClientWhereNotIn($client_ids)
    {
        $result = DB::table('oauth_clients')
                            ->select(['oauth_clients.id', 'oauth_clients.name', 'oauth_client_redirect_uris.redirect_uri'])
                            ->join('oauth_client_redirect_uris', 'oauth_clients.id', '=', 'oauth_client_redirect_uris.client_id')
                            ->whereNotIn('oauth_clients.id', $client_ids)
                            ->limit(1)
                            ->get();

        if (count($result) === 1) {
            $client = new ClientEntity($this->server);
            $client->hydrate([
                'id'    =>  $result[0]->id,
                'name'  =>  $result[0]->name,
                'redirectUri'   => $result[0]->redirect_uri,
            ]);

            return $client;
        }
        return;
    }
}
