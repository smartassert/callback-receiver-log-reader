<?php

declare(strict_types=1);

namespace SmartAssert\CallbackReceiverLogReader;

use Nyholm\Psr7\Request;
use Psr\Http\Message\RequestInterface;

readonly class Parser
{
    /**
     * @return RequestInterface[]
     */
    public function parse(string $content, ?int $lastRequestCount = null): array
    {
        if (0 === $lastRequestCount) {
            return [];
        }

        $requests = [];
        $logSections = $this->getRawLogSections($content, $lastRequestCount);

        foreach ($logSections as $section) {
            $sectionJson = $this->getJsonFromLogSection($section);
            $requestData = json_decode($sectionJson, true);

            if (!is_array($requestData)) {
                $requestData = [];
            }

            $requests[] = $this->createRequestFromLoggedData($requestData);
        }

        return $requests;
    }

    /**
     * @return array<string>
     */
    private function getRawLogSections(string $content, ?int $lastRequestCount = null): array
    {
        $sections = explode('-----------------', $content);
        $sections = array_filter($sections);

        if (null === $lastRequestCount) {
            return $sections;
        }

        $sectionCount = count($sections);
        if ($lastRequestCount >= $sectionCount) {
            return $sections;
        }

        return array_slice($sections, $lastRequestCount * -1);
    }

    private function getJsonFromLogSection(string $section): string
    {
        $lines = explode("\n", trim($section));
        array_pop($lines);

        return implode("\n", $lines);
    }

    /**
     * @param array<mixed> $data
     */
    private function createRequestFromLoggedData(array $data): RequestInterface
    {
        $method = $data['method'] ?? '';
        $method = is_string($method) ? $method : '';

        $uri = $data['path'] ?? '';
        if (!is_string($uri)) {
            $uri = '';
        }

        $headers = $data['headers'] ?? [];
        $headers = is_array($headers) ? $headers : [];

        $body = $data['body'] ?? '';
        $body = is_string($body) ? $body : '';

        return new Request($method, $uri, $headers, $body);
    }
}
