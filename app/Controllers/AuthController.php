<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class AuthController extends BaseController
{
    public function __construct(
        Twig $view,
        private AuthService $authService,
        private LoggerInterface $logger,
    ) {
        parent::__construct($view);
    }

    public function showRegister(Request $request, Response $response): Response
    {
        // TODO: you also have a logger service that you can inject and use anywhere; file is var/app.log
        $this->logger->info('Register page requested');

        return $this->render($response, 'auth/register.twig');
    }

    public function register(Request $request, Response $response): Response
    {
        // TODO: call corresponding service to perform user registration
        $data = (array) $request->getParsedBody();
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $errors = [];

        // Validation
        if (strlen($username) < 4) {
            $errors['username'] = 'Username must be at least 4 characters long.';
        }

        if (strlen($password) < 8 || !preg_match('/\d/', $password)) {
            $errors['password'] = 'Password must be at least 8 characters and contain at least one number.';
        }

        if (!empty($errors)) {
            return $this->render($response, 'auth/register.twig', [
                'username' => $username,
                'errors' => $errors,
            ]);
        }

        try {
            $this->authService->register($username, $password);
            $this->logger->info("User registered: $username");

            return $response->withHeader('Location', '/login')->withStatus(302);
        } catch (\Throwable $e) {
            $this->logger->error("Registration failed for $username: " . $e->getMessage());

            return $this->render($response, 'auth/register.twig', [
                'username' => $username,
                'errors' => ['username' => 'Registration failed. Username might already exist.'],
            ]);
        }
    }

    public function showLogin(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/login.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        // TODO: call corresponding service to perform user login, handle login failures

        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function logout(Request $request, Response $response): Response
    {
        // TODO: handle logout by clearing session data and destroying session

        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
