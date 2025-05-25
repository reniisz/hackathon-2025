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
        $this->ensureCsrfToken();
        $this->logger->info('Register page requested');

        return $this->render($response, 'auth/register.twig', [
            'csrf_token' => $_SESSION['csrf_token'],
        ]);
    }

    public function register(Request $request, Response $response): Response
    {
        // TODO: call corresponding service to perform user registration
        $data = (array) $request->getParsedBody();

        // CSRF validation - bonus
        if (!isset($_SESSION['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
            return $this->render($response, 'auth/register.twig', [
                'errors' => ['csrf' => 'Invalid CSRF token'],
                'username' => $data['username'] ?? '',
                'csrf_token' => $_SESSION['csrf_token'],
            ]);
        }

        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $passwordAgain = $data['password_again'] ?? '';
        $errors = [];

        // check password match
        if ($password !== $passwordAgain) {
            $errors['password_again'] = 'Passwords do not match.';
        }

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
                'csrf_token' => $_SESSION['csrf_token'],
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
                'csrf_token' => $_SESSION['csrf_token'],
            ]);
        }
    }

    public function showLogin(Request $request, Response $response): Response
    {
        $this->ensureCsrfToken();

        return $this->render($response, 'auth/login.twig', [
            'csrf_token' => $_SESSION['csrf_token'],
        ]);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        if (!isset($_SESSION['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
            return $this->render($response, 'auth/login.twig', [
                'errors' => ['csrf' => 'Invalid CSRF token'],
                'username' => $data['username'] ?? '',
                'csrf_token' => $_SESSION['csrf_token'],
            ]);
        }

        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $errors = [];

        if (!$this->authService->attempt($username, $password)) {
            $this->logger->warning("Login failed for $username");

            return $this->render($response, 'auth/login.twig', [
                'username' => $username,
                'errors' => ['login' => 'Invalid username or password'],
                'csrf_token' => $_SESSION['csrf_token'],
            ]);
        }

        $user = $this->authService->getByUsername($username);
        $_SESSION['user_id'] = $user->getId();

        $this->logger->info("User logged in: $username");
        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function logout(Request $request, Response $response): Response
    {
        // TODO: handle logout by clearing session data and destroying session
        // clear session data
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];
        session_destroy();
        session_start();

        $this->logger->info('User logged out');

        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
