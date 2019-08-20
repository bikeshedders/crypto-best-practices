<?php
declare(strict_types=1);

use League\CommonMark\Environment as CommonMarkEnvironment;
use League\CommonMark\Ext\Table\TableExtension;
use League\CommonMark\CommonMarkConverter;
use ParagonIE\CSPBuilder\CSPBuilder;
use Slim\{
    App,
    Container
};
use Twig\Environment;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\Loader\FilesystemLoader;

return function (App $app) {
    $container = $app->getContainer();

    $container['csp'] = function (Container $c): CSPBuilder {
        return CSPBuilder::fromFile(__DIR__ . '/csp.json');
    };

    $container['markdown'] = function (Container $c): CommonMarkConverter {
        $environment = CommonMarkEnvironment::createCommonMarkEnvironment();
        $environment->addExtension(new TableExtension());
        return new CommonMarkConverter([], $environment);
    };

    $container['purifier'] = function (Container $c): HTMLPurifier {
        return new HTMLPurifier();
    };

    $container['twig'] = function (Container $c): Environment {
        $settings = $c->get('settings')['twig'];
        $env = new Environment(
            new FilesystemLoader($settings['basedir'])
        );
        $env->addFilter(new TwigFilter('ucfirst', 'ucfirst'));
        $env->addFilter(
            new TwigFilter(
                'Markdown',
                function (string $in) use ($c): string {
                    /** @var CommonMarkConverter $markdown */
                    $markdown = $c['markdown'];
                    return $markdown->convertToHtml($in);
                }
            )
        );
        $env->addFilter(
            new TwigFilter(
                'purify',
                function (string $in) use ($c): string {
                    /** @var HTMLPurifier $purifier */
                    $purifier = $c['purifier'];
                    return $purifier->purify($in);
                },
                ['is_safe' => ['html']]
            )
        );
        $env->addFunction(
            new TwigFunction(
                'csp_nonce',
                function (string $dir = 'script-src') use ($c) {
                    /** @var CSPBuilder $csp */
                    $csp = $c['csp'];
                    return $csp->nonce($dir);
                }
            )
        );
        $env->addFunction(
            new TwigFunction(
                'markdown_file',
                function (string $file) use ($c): string {
                    $file = CRYPTO_ROOT . '/doc/contents/' . $file;
                    if (!is_readable($file)) {
                        return '';
                    }
                    /** @var HTMLPurifier $purifier */
                    $purifier = $c['purifier'];

                    /** @var CommonMarkConverter $markdown */
                    $markdown = $c['markdown'];

                    return $purifier->purify(
                        $markdown->convertToHtml(file_get_contents($file))
                    );
                },
                ['is_safe' => ['html']]
            )
        );
        return $env;
    };
};
