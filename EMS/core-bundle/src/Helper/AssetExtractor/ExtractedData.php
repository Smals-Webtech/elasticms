<?php

namespace EMS\CoreBundle\Helper\AssetExtractor;

use EMS\Helpers\Standard\Json;

class ExtractedData
{
    /**
     * @param array<string, mixed> $source
     */
    public function __construct(private array $source)
    {
    }

    public static function fromJsonString(string $json): self
    {
        return new self(Json::decode($json));
    }

    public static function fromMetaString(string $metaString): self
    {
        return new self(self::convertMetaStringToArray($metaString));
    }

    public function getLocale(): ?string
    {
        if (isset($this->source['language'])) {
            return \strval($this->source['language']);
        }

        return null;
    }

    public function setLocale(string $locale): void
    {
        $this->source['language'] = $locale;
    }

    public function getCreated(): ?\DateTimeImmutable
    {
        return $this->parseDate($this->source['created'] ?? $this->source['dcterms:created'] ?? '');
    }

    public function getModified(): ?\DateTimeImmutable
    {
        return $this->parseDate($this->source['modified'] ?? $this->source['dcterms:modified'] ?? '');
    }

    private function parseDate(string $date): ?\DateTimeImmutable
    {
        $parseDate = \strtotime($date);
        if (false === $parseDate) {
            return null;
        }

        return (new \DateTimeImmutable())->setTimestamp($parseDate);
    }

    public function getAuthor(): string
    {
        return \strval($this->source['author'] ?? $this->source['dc:creator'] ?? '');
    }

    public function getTitle(): string
    {
        return \strval($this->source['title'] ?? $this->source['dc:title'] ?? '');
    }

    public function hasContent(): bool
    {
        return isset($this->source['content']);
    }

    public function getContent(): string
    {
        return \strval($this->source['content'] ?? '');
    }

    public function setContent(string $content): void
    {
        $this->source['content'] = $content;
    }

    /**
     * @return mixed[]
     */
    public function getSource(): array
    {
        return $this->source;
    }

    /**
     * @return array<string, string>
     */
    private static function convertMetaStringToArray(string $data): array
    {
        if (!\mb_check_encoding($data)) {
            $data = \mb_convert_encoding($data, \mb_internal_encoding(), 'ASCII');
        }
        $cleaned = \preg_replace("/\r/", '', $data);
        if (null === $cleaned) {
            throw new \RuntimeException('It was possible to parse meta information');
        }
        $matches = [];
        \preg_match_all(
            '/^(.*): (.*)$/m',
            $cleaned,
            $matches,
            PREG_PATTERN_ORDER
        );

        return \array_combine($matches[1], $matches[2]);
    }
}
