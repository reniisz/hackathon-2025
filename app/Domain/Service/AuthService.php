<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function register(string $username, string $password): User
    {
        // check that a user with same username does not exist, create new user and persist

        if ($this->users->findByUsername($username)) {
            throw new \RuntimeException('Username already exists.');
        }

        // TODO: make sure password is not stored in plain, and proper PHP functions are used for that
        // hashed pswd
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $user = new User(null, $username, $hashedPassword, new \DateTimeImmutable());
        $this->users->save($user);

        return $user;
    }

    public function attempt(string $username, string $password): bool
    {
        $user = $this->users->findByUsername($username);

        if (!$user || !password_verify($password, $user->passwordHash)) {
            return false;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['user_id'] = $user->id;

        return true;
    }
}
