<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Exception;
use PDO;

class PdoExpenseRepository implements ExpenseRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * @throws Exception
     */
    public function find(int $id): ?Expense
    {
        $query = 'SELECT * FROM expenses WHERE id = :id';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['id' => $id]);
        $data = $statement->fetch();
        if (false === $data) {
            return null;
        }

        return $this->createExpenseFromData($data);
    }

    public function save(Expense $expense): void
    {
        if ($expense->id === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO expenses (user_id, date, category, amount_cents, description)
                 VALUES (:user_id, :date, :category, :amount_cents, :description)'
            );
            $stmt->execute([
                ':user_id' => $expense->userId,
                ':date' => $expense->date->format('Y-m-d'),
                ':category' => $expense->category,
                ':amount_cents' => $expense->amountCents,
                ':description' => $expense->description,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE expenses
                 SET date = :date, category = :category, amount_cents = :amount_cents, description = :description
                 WHERE id = :id AND user_id = :user_id'
            );
            $stmt->execute([
                ':id' => $expense->id,
                ':user_id' => $expense->userId,
                ':date' => $expense->date->format('Y-m-d'),
                ':category' => $expense->category,
                ':amount_cents' => $expense->amountCents,
                ':description' => $expense->description,
            ]);
        }
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM expenses WHERE id=?');
        $statement->execute([$id]);
    }

    /**
     * @throws Exception
     */
    public function listByMonth(int $userId, int $year, int $month, int $limit, int $offset): array
    {
        $datePrefix = sprintf('%04d-%02d', $year, $month);

        $query = 'SELECT * FROM expenses
                  WHERE user_id = :user_id
                  AND strftime(\'%Y-%m\', date) = :date_prefix
                  ORDER BY date DESC
                  LIMIT :limit OFFSET :offset';

        $statement = $this->pdo->prepare($query);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':date_prefix', $datePrefix, PDO::PARAM_STR);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll();
        return array_map([$this, 'createExpenseFromData'], $rows);
    }

    public function countByMonth(int $userId, int $year, int $month): int
    {
        $datePrefix = sprintf('%04d-%02d', $year, $month);

        $query = 'SELECT COUNT(*) FROM expenses
                  WHERE user_id = :user_id
                  AND strftime(\'%Y-%m\', date) = :date_prefix';

        $statement = $this->pdo->prepare($query);
        $statement->execute([
            ':user_id' => $userId,
            ':date_prefix' => $datePrefix,
        ]);

        return (int)$statement->fetchColumn();
    }

    public function findBy(array $criteria, int $from, int $limit): array
    {
        $query = 'SELECT * FROM expenses WHERE 1=1';
        $params = [];

        foreach ($criteria as $key => $value) {
            $query .= " AND $key = :$key";
            $params[":$key"] = $value;
        }

        $query .= ' ORDER BY date DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $from, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'createExpenseFromData'], $stmt->fetchAll());
    }

    public function countBy(array $criteria): int
    {
        $query = 'SELECT COUNT(*) FROM expenses WHERE 1=1';
        $params = [];

        foreach ($criteria as $key => $value) {
            $query .= " AND $key = :$key";
            $params[":$key"] = $value;
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function listExpenditureYears(User $user): array
    {
        $query = 'SELECT DISTINCT strftime(\'%Y\', date) as year FROM expenses WHERE user_id = :uid ORDER BY year DESC';
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':uid' => $user->id]);
        $years = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $currentYear = (new \DateTimeImmutable())->format('Y');
        if (!in_array($currentYear, $years)) {
            array_unshift($years, $currentYear);
        }

        return $years;
    }

    public function sumAmountsByCategory(array $criteria): array
    {
        $query = 'SELECT category, SUM(amount_cents) as value FROM expenses
                  WHERE user_id = :user_id AND strftime(\'%Y-%m\', date) = :date_prefix
                  GROUP BY category';
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            ':user_id' => $criteria['user_id'],
            ':date_prefix' => sprintf('%04d-%02d', $criteria['year'], $criteria['month']),
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = array_sum(array_column($rows, 'value'));

        $result = [];
        foreach ($rows as $row) {
            $result[$row['category']] = [
                'value' => (int)$row['value'],
                'percentage' => $total > 0 ? round(($row['value'] / $total) * 100, 2) : 0,
            ];
        }

        return $result;
    }

    public function averageAmountsByCategory(array $criteria): array
    {
        $query = 'SELECT category, AVG(amount_cents) as average FROM expenses
                  WHERE user_id = :user_id AND strftime(\'%Y-%m\', date) = :date_prefix
                  GROUP BY category';
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            ':user_id' => $criteria['user_id'],
            ':date_prefix' => sprintf('%04d-%02d', $criteria['year'], $criteria['month']),
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = array_sum(array_column($rows, 'average'));

        $result = [];
        foreach ($rows as $row) {
            $raw = (float)$row['average'];
            $result[$row['category']] = [
                'value' => (int) round($raw), // still in cents
                'percentage' => $total > 0 ? round(($raw / $total) * 100, 2) : 0,
            ];
        }
        return $result;
    }

    public function sumAmounts(array $criteria): float
    {
        $query = 'SELECT SUM(amount_cents) FROM expenses
                  WHERE user_id = :user_id AND strftime(\'%Y-%m\', date) = :date_prefix';
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            ':user_id' => $criteria['user_id'],
            ':date_prefix' => sprintf('%04d-%02d', $criteria['year'], $criteria['month']),
        ]);

        return (float)$stmt->fetchColumn();
    }

    /**
     * @throws Exception
     */
    private function createExpenseFromData(mixed $data): Expense
    {
        return new Expense(
            $data['id'],
            $data['user_id'],
            new DateTimeImmutable($data['date']),
            $data['category'],
            $data['amount_cents'],
            $data['description'],
        );
    }
}
