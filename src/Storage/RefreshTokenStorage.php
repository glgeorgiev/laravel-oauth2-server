<?php namespace GLGeorgiev\LaravelOAuth2Server\Storage;

use DB;
use League\OAuth2\Server\Entity\RefreshTokenEntity;
use League\OAuth2\Server\Storage\AbstractStorage;
use League\OAuth2\Server\Storage\RefreshTokenInterface;

class RefreshTokenStorage extends AbstractStorage implements RefreshTokenInterface
{
    /**
     * {@inheritdoc}
     */
    public function get($token)
    {
        $result = DB::table('oauth_refresh_tokens')
                            ->where('refresh_token', $token)
                            ->get();

        if (count($result) === 1) {
            $token = (new RefreshTokenEntity($this->server))
                        ->setId($result[0]->refresh_token)
                        ->setExpireTime($result[0]->expire_time)
                        ->setAccessTokenId($result[0]->access_token);

            return $token;
        }

        return;
    }

    /**
     * {@inheritdoc}
     */
    public function create($token, $expireTime, $accessToken)
    {
        DB::table('oauth_refresh_tokens')
                    ->insert([
                        'refresh_token'     =>  $token,
                        'access_token'    =>  $accessToken,
                        'expire_time'   =>  $expireTime,
                    ]);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(RefreshTokenEntity $token)
    {
        DB::table('oauth_refresh_tokens')
                            ->where('refresh_token', $token->getId())
                            ->delete();
    }
}
