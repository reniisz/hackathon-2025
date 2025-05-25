<?php

declare(strict_types=1);

namespace App;

use App\Domain\Service\CategoryBudgetProvider;
use App\Domain\Repository\ExpenseRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Persistence\PdoExpenseRepository;
use App\Infrastructure\Persistence\PdoUserRepository;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use PDO;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Slim\Csrf\Guard;
use Slim\Middleware\MethodOverrideMiddleware;

use function DI\autowire;
use function DI\factory;

class Kernel
{
    public static function createApp(): App
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);

        $builder->addDefinitions([
            LoggerInterface::class => function () {
                $logger = new Logger('app');
                $logger->pushHandler(new StreamHandler(__DIR__ . '/../var/app.log', Level::Debug));
                return $logger;
            },

            Twig::class => function () {
                return Twig::create(__DIR__ . '/../templates', ['cache' => false]);
            },

            PDO::class => factory(function () {
                static $pdo = null;
                if ($pdo === null) {
                    $pdo = new PDO('sqlite:' . __DIR__ . '/../' . $_ENV['DB_PATH']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                }
                return $pdo;
            }),

            UserRepositoryInterface::class => autowire(PdoUserRepository::class),
            ExpenseRepositoryInterface::class => autowire(PdoExpenseRepository::class),
            CategoryBudgetProvider::class => factory(function () {
                return new CategoryBudgetProvider($_ENV['CATEGORY_BUDGETS'] ?? '{}');
            }),

            ResponseFactoryInterface::class => function () {
                return AppFactory::create()->getResponseFactory();
            },

            Guard::class => function ($c) {
                return new Guard($c->get(ResponseFactoryInterface::class));
            },
        ]);

        $container = $builder->build();

        AppFactory::setContainer($container);
        $app = AppFactory::create();

        $app->add(MethodOverrideMiddleware::class);
        $app->add(TwigMiddleware::createFromContainer($app, Twig::class));

        (require __DIR__ . '/../config/settings.php')($app);
        (require __DIR__ . '/../config/routes.php')($app);

        $twig = $container->get(Twig::class);
        $guard = $container->get(Guard::class);

        $twig->getEnvironment()->addGlobal('csrf_token_name', $guard->getTokenNameKey());
        $twig->getEnvironment()->addGlobal('csrf_token_value', $guard->getTokenValueKey());
        $twig->getEnvironment()->addGlobal('currentUserId', $_SESSION['user_id'] ?? null);

        return $app;
    }
}
