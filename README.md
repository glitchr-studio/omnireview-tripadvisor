# omnireview/tripadvisor

**Tripadvisor** for [glitchr/omnireview](https://github.com/glitchr-studio/omnireview): a
location's rating, its bubbles, its number of reviews, up to five reviews and the link to write
one, through the Tripadvisor Content API.

```php
use Omnireview\Model\Place;
use Omnireview\Tripadvisor\TripadvisorGatewayFactory;

$gateway = (new TripadvisorGatewayFactory($httpClient))->create(['api_key' => getenv('TRIPADVISOR_KEY'), 'language' => 'fr']);

$location = new Place('1234567');                     // the location ID: it may be kept
$gateway->rating($location);                          // 4.5 from 312, the bubbles' image, the location's page
$gateway->reviews($location);                         // up to five
$gateway->writeUrl($location);
```

Written from the Content API's reference and display requirements, read on 2026-10-08. **Not
verified in real: no key.**

[Documentation](docs/index.md): the options, the calls, Tripadvisor's display rules, what was
verified.

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
