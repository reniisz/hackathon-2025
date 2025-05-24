<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use DateTimeImmutable;
use Exception;
use PDO;

class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * @throws Exception
     */
    public function findById(int $id): ?User
    {
        $query = 'SELECT * FROM users WHERE id = :id';
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        if (false === $data) {
            return null;
        }

        return new User(
            (int)$data['id'],
            $data['username'],
            $data['password_hash'],
            new DateTimeImmutable($data['created_at'])
        );
    }

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return new User(
            (int)$data['id'],
            $data['username'],
            $data['password_hash'],
            new DateTimeImmutable($data['created_at'])
        );
    }

    public function save(User $user): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, password_hash, created_at)
             VALUES (:username, :password_hash, :created_at)'
        );

        $stmt->execute([
            'username'      => $user->username,
            'password_hash' => $user->passwordHash,
            'created_at'    => $user->createdAt->format('Y-m-d H:i:s'),
        ]);

        $user->id = (int)$this->pdo->lastInsertId();
    }
}
