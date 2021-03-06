<?php

declare(strict_types = 1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SimpleXMLElement;

use function json_decode;
use function array_flip;
use function array_walk;

class XmlEncoderMiddleware implements MiddlewareInterface
{
    /**
     * Process an incoming server request.
     *
     * Processes an incoming server request in order to produce a response.
     * If unable to produce the response itself, it may delegate to the provided
     * request handler to do so.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $response = $handler->handle($request);

        if ($request->getHeader('Accept')[0] === 'application/xml') {
            $body = json_decode((string) $response->getBody(), true);
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><root/>');

            array_walk($body, function ($item, $key) use ($xml) {
                if (is_array($item)) {
                    $child = $xml->addChild('student');
                    $item = array_flip($item);
                    array_walk($item, [$child, 'addChild']);
                } else {
                    $xml->addChild($key, (string) $item);
                }
            });

            $response->getBody()->rewind();
            $response->getBody()->write($xml->asXML());

            return $response->withHeader('Content-Type', 'application/xml');
        }

        return $response;
    }
}
