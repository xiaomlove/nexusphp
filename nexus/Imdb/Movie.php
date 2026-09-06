<?php

namespace Nexus\Imdb;

use Imdb\Title;

/**
 * Compatibility layer over duck7000/imdb-graphql-php Title.
 * Keeps the method names and return shapes used by NexusPHP pages.
 */
class Movie
{
    private Title $title;

    private array $titleMeta = [];

    private array $ratingVotes = [];

    private ?array $languages = null;

    private ?array $castList = null;

    public function __construct(Title $title)
    {
        $this->title = $title;
    }

    public function raw(): Title
    {
        return $this->title;
    }

    public function title(): string
    {
        return (string)($this->titleMeta()['title'] ?? '');
    }

    public function year(): string|int
    {
        return $this->titleMeta()['year'] ?? '';
    }

    public function rating(): float|string
    {
        $rating = $this->ratingVotes()['rating'] ?? 0;
        return $rating ?: '';
    }

    public function votes(): int|string
    {
        return $this->ratingVotes()['votes'] ?? 0;
    }

    public function runtime(): string|int|null
    {
        $runtimes = $this->title->runtime();
        if (empty($runtimes[0]['time'])) {
            return null;
        }
        return (int)$runtimes[0]['time'];
    }

    public function language(): string
    {
        $languages = $this->cachedLanguage();
        if (empty($languages[0]['text'])) {
            return '';
        }
        return (string)$languages[0]['text'];
    }

    public function country(): array
    {
        $countries = $this->title->country() ?: [];
        $result = [];
        foreach ($countries as $country) {
            if (!empty($country['text'])) {
                $result[] = $country['text'];
            } elseif (is_string($country)) {
                $result[] = $country;
            }
        }
        return $result;
    }

    public function genres(): array
    {
        $genres = $this->title->genre() ?: [];
        $result = [];
        foreach ($genres as $genre) {
            if (!empty($genre['mainGenre'])) {
                $result[] = $genre['mainGenre'];
            } elseif (is_string($genre)) {
                $result[] = $genre;
            }
        }
        return $result;
    }

    public function tagline(): string
    {
        $taglines = $this->title->tagline() ?: [];
        if (empty($taglines)) {
            return '';
        }
        return is_array($taglines) ? (string)($taglines[0] ?? '') : (string)$taglines;
    }

    public function plotoutline(): string
    {
        return (string)($this->title->plotoutline() ?? '');
    }

    public function plot(): array
    {
        $plots = $this->title->plot() ?: [];
        $result = [];
        foreach ($plots as $plot) {
            if (is_string($plot)) {
                $result[] = $plot;
            } elseif (!empty($plot['plot'])) {
                $result[] = $plot['plot'];
            }
        }
        return $result;
    }

    public function photo($thumb = true)
    {
        return $this->title->photo((bool)$thumb);
    }

    public function photo_localurl($thumb = true)
    {
        return $this->title->photoLocalurl((bool)$thumb);
    }

    public function releaseInfo(): array
    {
        return $this->title->releaseDate() ?: [];
    }

    public function alsoknow(): array
    {
        $akas = $this->title->alsoknow() ?: [];
        foreach ($akas as &$aka) {
            if (isset($aka['comment']) && is_array($aka['comment'])) {
                $aka['comment'] = implode(', ', $aka['comment']);
            } elseif (!isset($aka['comment'])) {
                $aka['comment'] = '';
            }
            $aka['country'] = $aka['country'] ?? '';
            $aka['title'] = $aka['title'] ?? '';
        }
        unset($aka);
        return $akas;
    }

    public function director(): array
    {
        return $this->mapPeople($this->title->director() ?: []);
    }

    public function writing(): array
    {
        return $this->mapPeople($this->title->writer() ?: []);
    }

    public function producer(): array
    {
        return $this->mapPeople($this->title->producer() ?: []);
    }

    public function composer(): array
    {
        return $this->mapPeople($this->title->composer() ?: []);
    }

    public function creator(): array
    {
        $credits = $this->title->principalCredits() ?: [];
        foreach (['Creator', 'Creators'] as $key) {
            if (!empty($credits[$key])) {
                return $this->mapPeople($credits[$key]);
            }
        }
        return [];
    }

    public function cast(): array
    {
        $cast = $this->cachedCast();
        $result = [];
        foreach ($cast as $person) {
            $role = $person['role'] ?? '';
            if ($role === '' && !empty($person['character'])) {
                $role = is_array($person['character'])
                    ? implode(' / ', $person['character'])
                    : (string)$person['character'];
            }
            $result[] = [
                'imdb' => $this->personImdbPath($person['imdb'] ?? ($person['imdbid'] ?? '')),
                'name' => $person['name'] ?? '',
                'role' => $role,
            ];
        }
        return $result;
    }

    public function __call(string $name, array $arguments)
    {
        return $this->title->$name(...$arguments);
    }

    private function titleMeta(): array
    {
        if (!$this->titleMeta) {
            $this->titleMeta = $this->title->titleYearMovietype() ?: [];
        }
        return $this->titleMeta;
    }

    private function ratingVotes(): array
    {
        if (!$this->ratingVotes) {
            $this->ratingVotes = $this->title->ratingVotes() ?: [];
        }
        return $this->ratingVotes;
    }

    private function cachedLanguage(): array
    {
        if ($this->languages === null) {
            $this->languages = $this->title->language() ?: [];
        }
        return $this->languages;
    }

    private function cachedCast(): array
    {
        if ($this->castList === null) {
            $this->castList = $this->title->cast() ?: [];
        }
        return $this->castList;
    }

    private function mapPeople(array $people): array
    {
        $result = [];
        foreach ($people as $person) {
            $result[] = [
                'imdb' => $this->personImdbPath($person['imdb'] ?? ($person['imdbid'] ?? '')),
                'name' => $person['name'] ?? '',
            ];
        }
        return $result;
    }

    private function personImdbPath($id): string
    {
        $id = preg_replace('/\D+/', '', (string)$id);
        if ($id === '') {
            return '';
        }
        return 'name/nm' . $id . '/';
    }
}
