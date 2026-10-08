<?php

namespace Omnireview\Tripadvisor\Tests;

use Omnireview\Exception\InvalidKeyException;
use Omnireview\Model\Place;
use Omnireview\ReplyInterface;
use Omnireview\Tripadvisor\TripadvisorGateway;
use Omnireview\Tripadvisor\TripadvisorGatewayFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** Answers written from the Content API's OpenAPI (details, reviews): no key was used. */
final class TripadvisorGatewayTest extends TestCase
{
    /** @var list<array{string, string, array<string, mixed>}> */
    private array $calls = [];

    /** @param list<string|MockResponse> $answers */
    private function gateway(array $answers): TripadvisorGateway
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$answers): MockResponse {
            $this->calls[] = [$method, $url, $options];
            $answer = array_shift($answers) ?? throw new \LogicException('No answer left for '.$url);
            if ($answer instanceof MockResponse) {
                return $answer;
            }
            [$file, $status] = explode(':', $answer.':200');

            return new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.$file.'.json'), ['http_code' => (int) $status]);
        });
        $gateway = (new TripadvisorGatewayFactory($http))->create(['api_key' => 'ta-key', 'language' => 'fr', 'referer' => 'https://www.maison-erable.example/']);
        self::assertInstanceOf(TripadvisorGateway::class, $gateway);

        return $gateway;
    }

    public function testTheRatingWithItsBubblesAndTheLinkToWriteOneFromOneCall(): void
    {
        $gateway = $this->gateway(['details']);
        $rating = $gateway->rating(new Place('1234567'));

        self::assertSame([4.5, 312, 'https://www.tripadvisor.com/img/cdsi/img2/ratings/traveler/4.5-12345-5.svg'], [$rating->value, $rating->count, $rating->image]);
        self::assertStringStartsWith('https://www.tripadvisor.fr/Restaurant_Review', (string) $rating->url);
        self::assertSame('https://www.tripadvisor.fr/UserReview-g187147-d1234567-Maison_Erable-Paris.html', $gateway->writeUrl(new Place('1234567')));
        self::assertCount(1, $this->calls, 'the details asked once');
        self::assertSame('https://api.content.tripadvisor.com/api/v1/location/1234567/details?key=ta-key&language=fr', $this->calls[0][1]);
        self::assertStringContainsString('Referer: https://www.maison-erable.example/', implode("\n", $this->calls[0][2]['headers']));
    }

    public function testReviewsUpToFive(): void
    {
        $reviews = $this->gateway(['reviews'])->reviews(new Place('1234567'), 10);

        self::assertSame('https://api.content.tripadvisor.com/api/v1/location/1234567/reviews?key=ta-key&language=fr&limit=5', $this->calls[0][1]);
        $review = $reviews[0];
        self::assertSame(['900001', 5.0, 'Excellent', 'Un dîner parfait.', 'Camille R', 'Merci beaucoup !', '2026-09-20'], [$review->id, $review->rating, $review->title, $review->text, $review->author, $review->reply?->text, $review->publishedAt?->format('Y-m-d')]);
        self::assertStringContainsString('ShowUserReviews', (string) $review->url);
        self::assertNotNull($review->image, 'the bubbles of the review, from Tripadvisor');
    }

    public function testItsTermsAndARefusedKey(): void
    {
        $gateway = $this->gateway(['error:401']);
        self::assertTrue($gateway->terms()->noIndex);
        self::assertNull($gateway->terms()->cacheFor, 'as Tripadvisor\'s caching policy allows: not published');
        self::assertFalse($gateway->terms()->allowsCaching(1));
        self::assertNotInstanceOf(ReplyInterface::class, $gateway);

        $this->expectException(InvalidKeyException::class);
        $gateway->rating(new Place('1234567'));
    }
}
