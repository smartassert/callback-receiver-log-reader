<?php

declare(strict_types=1);

namespace SmartAssert\CallbackReceiverLogReader\Tests\Integration;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SmartAssert\CallbackReceiverLogReader\Parser;
use Symfony\Component\Process\Process;

class ParserTest extends TestCase
{
    /**
     * @param RequestInterface[] $requests
     * @param array<mixed>       $expectedRequestDataCollection
     */
    #[DataProvider('parseDataProvider')]
    public function testParse(
        array $requests,
        array $expectedRequestDataCollection,
    ): void {
        $expectedRequestCount = count($requests);

        $client = new Client();

        foreach ($requests as $requestToSend) {
            $client->send($requestToSend);
        }

        $process = Process::fromShellCommandline('docker logs callback-receiver');
        $process->run();

        $output = $process->getOutput();

        $parser = new Parser();
        $loggedRequests = $parser->parse($output, $expectedRequestCount);

        self::assertCount($expectedRequestCount, $loggedRequests);

        foreach ($loggedRequests as $requestIndex => $loggedRequest) {
            $expectedRequestData = $expectedRequestDataCollection[$requestIndex] ?? [];
            self::assertIsArray($expectedRequestData);

            self::assertSame($expectedRequestData['method'], $loggedRequest->getMethod());
            self::assertSame($expectedRequestData['uri'], (string) $loggedRequest->getUri());

            $loggedRequestHeaders = $loggedRequest->getHeaders();
            unset(
                $loggedRequestHeaders['host'],
                $loggedRequestHeaders['user-agent'],
                $loggedRequestHeaders['content-length'],
            );

            $expectedRequestHeaders = $expectedRequestData['headers'] ?? [];
            $expectedRequestHeaders = is_array($expectedRequestHeaders) ? $expectedRequestHeaders : [];

            foreach (array_keys($loggedRequestHeaders) as $headerName) {
                self::assertSame(
                    $expectedRequestHeaders[$headerName],
                    $loggedRequest->getHeaderLine($headerName)
                );
            }

            self::assertSame($expectedRequestData['body'], (string) $loggedRequest->getBody());
        }
    }

    /**
     * @return array<mixed>
     */
    public static function parseDataProvider(): array
    {
        $baseUrl = 'http://localhost:8080';

        $getRequestWithoutHeaders = new Request('GET', $baseUrl . '/');

        $getRequestWithoutHeadersData = [
            'method' => 'GET',
            'uri' => '/',
            'headers' => [],
            'body' => '',
        ];

        $getRequestWithHeaders = new Request(
            'GET',
            $baseUrl . '/',
            [
                'X-Foo-1' => 'bar_1',
                'X-Foo-2' => 'bar_2',
                'X-Foo-3' => 'bar_3',
            ],
        );
        $getRequestWithHeadersData = [
            'method' => 'GET',
            'uri' => '/',
            'headers' => [
                'x-foo-1' => 'bar_1',
                'x-foo-2' => 'bar_2',
                'x-foo-3' => 'bar_3',
            ],
            'body' => '',
        ];

        $postRequestWithJsonBody = new Request(
            'POST',
            $baseUrl . '/',
            [
                'Content-Type' => 'application/json',
            ],
            (string) json_encode([
                'foo1' => 'bar1',
                'foo2' => 'bar2',
                'foo3' => 'bar3',
            ])
        );

        $postRequestWithJsonBodyData = [
            'method' => 'POST',
            'uri' => '/',
            'headers' => [
                'content-type' => 'application/json',
            ],
            'body' => (string) json_encode([
                'foo1' => 'bar1',
                'foo2' => 'bar2',
                'foo3' => 'bar3',
            ]),
        ];

        return [
            'empty' => [
                'requests' => [],
                'expectedRequestDataCollection' => [],
            ],
            'single GET request, no headers' => [
                'requests' => [
                    $getRequestWithoutHeaders,
                ],
                'expectedRequestDataCollection' => [
                    $getRequestWithoutHeadersData,
                ],
            ],
            'single GET request with headers' => [
                'requests' => [
                    $getRequestWithHeaders,
                ],
                'expectedRequestDataCollection' => [
                    $getRequestWithHeadersData,
                ],
            ],
            'single POST request with JSON body' => [
                'requests' => [
                    $postRequestWithJsonBody,
                ],
                'expectedRequestDataCollection' => [
                    $postRequestWithJsonBodyData,
                ],
            ],
            'multiple requests' => [
                'requests' => [
                    $getRequestWithoutHeaders,
                    $getRequestWithHeaders,
                    $postRequestWithJsonBody,
                ],
                'expectedRequestDataCollection' => [
                    $getRequestWithoutHeadersData,
                    $getRequestWithHeadersData,
                    $postRequestWithJsonBodyData,
                ],
            ],
        ];
    }
}
