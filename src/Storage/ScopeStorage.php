<?php namespace GLGeorgiev\LaravelOAuth2Server\Storage;

use DB;
use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Storage\AbstractStorage;
use League\OAuth2\Server\Storage\ScopeInterface;

class ScopeStorage extends AbstractStorage implements ScopeInterface
{
    /**
     * {@inheritdoc}
     */
    public function get($scope, $grantType = null, $clientId = null)
    {
        $result = DB::table('oauth_scopes')
                                ->where('id', $scope)
                                ->get();

        if (count($result) === 0) {
            return null;
        }

        return (new ScopeEntity($this->server))->hydrate([
            'id'            =>  $result[0]->id,
            'description'   =>  $result[0]->description,
        ]);
    }
}
