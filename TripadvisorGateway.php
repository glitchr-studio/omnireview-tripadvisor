<?php

namespace Omnireview\Tripadvisor;

use Omnireview\Exception\InvalidKeyException;
use Omnireview\Exception\ProviderException;
use Omnireview\Http\Answer;
use Omnireview\Model\Capabilities;
use Omnireview\Model\Place;
use Omnireview\Model\Rating;
use Omnireview\Model\Reply;
use Omnireview\Model\Review;
use Omnireview\Model\Terms;
use Omnireview\RatingInterface;
use Omnireview\ReviewsInterface;
use Omnireview\WriteUrlInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Tripadvisor's Content API: a location's details (rating, num_reviews,
 * web_url, write_review, the bubbles' picture) and its reviews - up to five,
 * the most recent. The partner key in the query.
 */
final class TripadvisorGateway implements RatingInterface, ReviewsInterface, WriteUrlInterface
{
    public const URL = 'https://api.content.tripadvisor.com/api/v1/location/';

    /** @var array<string, array<string, mixed>> the details asked in this request, by location and language */
    private array $details = [];

    public function __construct(
        private readonly HttpClientInterface $http,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $language = 'en',
        private readonly ?string $referer = null,
    ) {
    }

    public function getName(): string
    {
        return 'tripadvisor';
    }

    public function getTitle(): string
    {
        return 'Tripadvisor';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(rating: true, reviews: true, reply: false, invite: false, writeUrl: true);
    }

    public function terms(): Terms
    {
        return new Terms(
            cacheFor: null,
            maxReviews: 5,
            attribution: 'Tripadvisor',
            authors: true,
            link: true,
            noIndex: true,
            rules: [
                'Show the Tripadvisor logo (Ollie) with a rating, at least 20 pixels high, and the bubble ratings the API gives (rating_image_url), at least 55 pixels wide, served from Tripadvisor\'s addresses - never stored nor drawn anew.',
                'Quote a traveller\'s review within quotation marks, with its date, as "a Tripadvisor traveler review".',
                'Cite the month and year of any ranking.',
                'Make every page that shows Tripadvisor\'s content non-indexable by search engines.',
                'Cache only as Tripadvisor\'s Caching Policy permits; the location ID may be kept.',
            ],
            source: 'https://tripadvisor-content-api.readme.io/reference/display-requirements',
        );
    }

    public function rating(Place $place): Rating
    {
        $details = $this->details($place, $this->language);

        return new Rating('tripadvisor', (float) ($details['rating'] ?? 0), (int) ($details['num_reviews'] ?? 0), url: $details['web_url'] ?? null, image: $details['rating_image_url'] ?? null);
    }

    public function reviews(Place $place, int $limit = 5, ?string $language = null): array
    {
        $data = $this->call($place->id.'/reviews', ['language' => $language ?? $this->language, 'limit' => max(1, min(5, $limit))]);

        return array_slice(array_map(static fn (array $r) => new Review(
            'tripadvisor',
            (string) ($r['id'] ?? ''),
            (float) ($r['rating'] ?? 0),
            isset($r['text']) ? (string) $r['text'] : null,
            isset($r['title']) ? (string) $r['title'] : null,
            $r['lang'] ?? null,
            $r['user']['username'] ?? null,
            authorPhoto: $r['user']['avatar']['small'] ?? $r['user']['avatar']['thumbnail'] ?? null,
            publishedAt: self::date($r['published_date'] ?? null),
            url: $r['url'] ?? null,
            reply: isset($r['owner_response']['text']) ? new Reply((string) $r['owner_response']['text'], self::date($r['owner_response']['published_date'] ?? null)) : null,
            translated: (bool) ($r['is_machine_translated'] ?? false),
            image: $r['rating_image_url'] ?? null,
        ), (array) ($data['data'] ?? [])), 0, $limit);
    }

    public function writeUrl(Place $place): string
    {
        return (string) ($this->details($place, $this->language)['write_review'] ?? throw new ProviderException('tripadvisor', 'The location gives no write_review link.'));
    }

    /** @return array<string, mixed> */
    private function details(Place $place, string $language): array
    {
        return $this->details[$place->id.'|'.$language] ??= $this->call($place->id.'/details', ['language' => $language]);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function call(string $path, array $query): array
    {
        $answer = Answer::send($this->http, 'tripadvisor', 'GET', self::URL.$path, ['query' => ['key' => $this->apiKey] + $query, 'headers' => array_filter(['Accept' => 'application/json', 'Referer' => $this->referer])]);
        $data = json_decode($answer->body, true);
        $error = \is_array($data) ? ($data['error'] ?? $data['Message'] ?? null) : null;
        if (\in_array($answer->status, [401, 403], true)) {
            throw new InvalidKeyException('tripadvisor', \sprintf('The key was refused (HTTP %d): %s', $answer->status, \is_array($error) ? ($error['message'] ?? '') : (string) $error), (string) $answer->status);
        }
        if ($answer->status >= 400 || null !== $error) {
            throw new ProviderException('tripadvisor', \sprintf('HTTP %d: %s', $answer->status, \is_array($error) ? ($error['message'] ?? $error['type'] ?? '') : (string) ($error ?? mb_substr(trim($answer->body), 0, 200))), \is_array($error) && isset($error['code']) ? (string) $error['code'] : null);
        }

        return $answer->json();
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        try {
            return \is_string($value) && '' !== $value ? new \DateTimeImmutable($value) : null;
        } catch (\Exception) {
            return null;
        }
    }
}
