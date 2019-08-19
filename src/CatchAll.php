<?php

use ParagonIE\ConstantTime\Binary;
use ParagonIE\CSPBuilder\CSPBuilder;
use Slim\Container;
use Slim\Http\{
    Headers,
    Request,
    Response,
    Stream
};

/**
 * Class CatchAll
 */
class CatchAll
{
    /**
     * @var Container $container
     */
    private $container;

    /**
     * @var bool $ignoreCache
     */
    private $ignoreCache = false;

    /**
     * CatchAll constructor.
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        if ($container['settings']['ignore-cache']) {
            $this->ignoreCache = true;
        }
    }

    /**
     * array_pop() without mutating the original array
     *
     * @param array $pieces
     * @return mixed
     */
    protected function getLastPiece(array $pieces)
    {
        return array_pop($pieces);
    }

    /**
     * @param string $template
     * @return string[]
     * @throws SodiumException
     */
    protected function getCache(string $template): array
    {
        /** @var \ParagonIE\HiddenString\HiddenString $cacheKey */
        $cacheKey = $this->container['settings']['cache-key'];
        $mtime = filemtime(CRYPTO_ROOT . '/doc/' . $template);
        $cached = sodium_crypto_shorthash(
            pack('P', $mtime) . $template,
            $cacheKey->getString()
        );
        $cacheDir = CRYPTO_ROOT . '/local/cache/' . bin2hex($cached[0]);
        $cacheFile = $cacheDir . '/' . bin2hex(Binary::safeSubstr($cached, 1));
        return [$cacheDir, $cacheFile];
    }

    /**
     * @param string $template
     * @param array $args
     * @param int $status
     * @param array $headers
     * @return Response
     *
     * @throws \SodiumException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    protected function render(
        string $template,
        array $args = [],
        int $status = 200,
        array $headers = []
    ): Response {
        // Default HTTP headers:
        if (empty($headers)) {
            $headers['Content-Type'] = 'text/html; charset=UTF-8';
        }

        /** @var CSPBuilder $csp */
        $csp = $this->container['csp'];

        /** @var \Twig\Environment $twig */
        $twig = $this->container['twig'];

        if ($template === 'markdown.twig') {
            [$cacheDir, $cacheFile] = $this->getCache($args['file']);
        } else {
            [$cacheDir, $cacheFile] = $this->getCache($template);
        }
        if (file_exists($cacheFile) && !$this->ignoreCache) {
            // We can just load from the cache...
            $fp = fopen($cacheFile, 'rb');
            $tmp = fopen('php://temp', 'wb');
            stream_copy_to_stream($fp, $tmp);
            fclose($fp);
            fseek($tmp, 0, SEEK_SET);
            $body = new Stream($tmp);
        } else {
            // We need to render then cache this template...
            $contents = $twig->render($template, $args);
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0777);
            }
            file_put_contents($cacheFile, $contents);
            $body = $this->stringToStream($contents);
        }

        $response = $csp->injectCSPHeader(
            new Response($status, new Headers($headers), $body)
        );
        if (!($response instanceof Response)) {
            throw new TypeError('Invalid type; expected Response');
        }
        return $response;
    }

    /**
     * @param array $pieces
     * @return Response
     * @throws SodiumException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    protected function servePage(array $pieces): Response
    {
        $copied = $pieces;
        $baseDir = CRYPTO_ROOT . '/doc';
        do {
            foreach (['', '/index'] as $suffix) {
                $subPath = implode('/', $copied) . $suffix;
                $realTwig = realpath($baseDir . '/contents/' . $subPath . '.twig');
                if ($realTwig) {
                    // Prevent traversals
                    if (strpos($realTwig, $baseDir) === 0 && is_readable($realTwig)) {
                        return $this->render('contents/' . $subPath . '.twig');
                    }
                }
                $realMarkdown = realpath($baseDir . '/contents/' . $subPath . '.md');
                if ($realMarkdown) {
                    // Prevent traversals
                    if (strpos($realMarkdown, $baseDir) === 0 && is_readable($realMarkdown)) {
                        return $this->render('markdown.twig', ['file' => file_get_contents($realMarkdown)]);
                    }
                }
            }

            array_pop($copied);
        } while (!empty($copied));
        return $this->render('error-404.twig');
    }

    /**
     * @param string $data
     * @return Stream
     */
    protected function stringToStream(string $data): Stream
    {
        $fp = fopen('php://temp', 'wb');
        fwrite($fp, $data);
        fseek($fp, 0, SEEK_SET);
        return new Stream($fp);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     *
     * @throws SodiumException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function __invoke(Request $request, Response $response, array $args)
    {
        $pieces = explode('/', trim($request->getRequestTarget(), '/'));
        /*
        $last = $this->getLastPiece($pieces);
        if (preg_match('#^([A-Za-z0-9\-_]+)\.zip\.sig$#', $last, $m)) {

        }
        if (preg_match('#^([A-Za-z0-9\-_]+)\.zip$#', $last, $m)) {

        }
        */
        return $this->servePage($pieces);
    }
}
