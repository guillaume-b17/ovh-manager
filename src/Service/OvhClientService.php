<?php

namespace App\Service;

use Ovh\Api;

class OvhClientService
{
    private Api $client;

    public function __construct(string $appKey, string $appSecret, string $consumerKey, string $endpoint)
    {
        $this->client = new Api(
            $appKey,
            $appSecret,
            $endpoint,
            $consumerKey
        );
    }

    public function getClient(): Api
    {
        return $this->client;
    }

    public function getAccountInfo(): array
    {
        return $this->client->get('/me');
    }
}
