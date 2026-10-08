---
title: omnireview/tripadvisor
order: 1
---

# omnireview/tripadvisor

## Installation

```sh
composer require omnireview/tripadvisor
```

PHP 8.2 or later, `glitchr/omnireview` and `symfony/http-client`. A Tripadvisor Content API key
(the Tripadvisor Developer portal), restricted to the site's domains or the server's addresses.

## Options

| Option | Default | |
|---|---|---|
| `api_key` | required | |
| `language` | `en` | the content's language (`fr`, `de`, `ja`...) |
| `referer` | | the `Referer` sent: for a key restricted to domains |

## Calls

| | Route | |
|---|---|---|
| `rating()` | `GET api.content.tripadvisor.com/api/v1/location/{locationId}/details?key=&language=` | `rating`, `num_reviews`, `web_url`, `rating_image_url` (the bubbles) |
| `reviews()` | `GET .../location/{locationId}/reviews?key=&language=&limit=` | up to 5: the Content API gives no more |
| `writeUrl()` | `write_review` of `/details` | the details are asked once per gateway and place |

Replying and inviting are not in the Content API: `capabilities()` says so.

## Tripadvisor's rules, as declared (`terms()`)

From the Content API's display requirements
([tripadvisor-content-api.readme.io/reference/display-requirements](https://tripadvisor-content-api.readme.io/reference/display-requirements)),
read on 2026-10-08:

- The Tripadvisor logo (Ollie) with a rating, at least 20 pixels high; the bubbles the API gives
  (`Rating::$image`), at least 55 pixels wide, **served from Tripadvisor's addresses**, never
  stored nor redrawn. The widget shows that image when it is given, and names Tripadvisor in
  words: an override of its template adds the logo.
- A review quoted within quotation marks, with its date, as a Tripadvisor traveller's review.
- **Every page that shows Tripadvisor's content kept out of search engines** (`noIndex: true`):
  the widget adds `data-nosnippet`, the page must carry `<meta name="robots" content="noindex">`.
- Kept only as Tripadvisor's Caching Policy permits - a document not public: `cacheFor: null`, and
  the Twig functions keep nothing. The location ID may be kept.
- A ranking cited with its month and year.

## Verified, and not

| | |
|---|---|
| Against the Content API | **not verified in real: no key.** Every answer in `Tests/Fixtures` is written from the API's reference (`/details`, `/reviews`, the error body) |
| The query (`key`, `language`, `limit`), the `Referer`, the limit of five | by the tests |
| The Caching Policy | not read: Tripadvisor gives it to partners; nothing is kept until it is |
