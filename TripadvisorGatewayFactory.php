<?php

namespace Omnireview\Tripadvisor;

use Omnireview\Config;
use Omnireview\GatewayFactory;
use Omnireview\GatewayInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Tripadvisor's Content API.
 *
 *   options:
 *     api_key: '%env(TRIPADVISOR_KEY)%'      # required: the partner key (restricted to the site's domains or addresses)
 *     language: fr                           # en by default
 *     referer: https://www.example.org/      # the Referer sent, for a key restricted to domains
 */
final class TripadvisorGatewayFactory extends GatewayFactory
{
    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnireview.factory_name' => 'tripadvisor',
            'omnireview.factory_title' => 'Tripadvisor',
            'omnireview.required_options' => ['api_key'],
            'language' => 'en',
            'referer' => null,
        ]);
    }

    protected function build(Config $c): GatewayInterface
    {
        return new TripadvisorGateway($this->http ?? HttpClient::create(), (string) $c['api_key'], $c->string('language') ?? 'en', $c->string('referer'));
    }
}
