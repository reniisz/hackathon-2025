<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Psr\Http\Message\UploadedFileInterface;

class ExpenseService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
    ) {}

    public function list(User $user, int $year, int $month, int $pageNumber, int $pageSize): array
    {
        // TODO: implement this and call from controller to obtain paginated list of expenses
        $offset = ($pageNumber - 1) * $pageSize;
        return $this->expenses->listByMonth($user->id, $year, $month, $pageSize, $offset);
    }

    public function create(
        User $user,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): void {
        // TODO: implement this to create a new expense entity, perform validation, and persist

        // TODO: here is a code sample to start with
        $expense = new Expense(null, $user->id, $date, $category, (int)$amount, $description);
        $this->expenses->save($expense);
    }

    public function update(
        Expense $expense,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): void {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Amount must be greater than 0.");
        }

        if (trim($description) === '') {
            throw new \InvalidArgumentException("Description cannot be empty.");
        }

        $now = new \DateTimeImmutable();
        if ($date > $now) {
            throw new \InvalidArgumentException("Date must not be in the future.");
        }

        if (trim($category) === '') {
            throw new \InvalidArgumentException("Category must be selected.");
        }

        // Mutate the expense entity
        $expense->amountCents = (int)round($amount * 100);
        $expense->description = $description;
        $expense->date = $date;
        $expense->category = $category;

        $this->expenses->save($expense);
    }

    public function importFromCsv(User $user, UploadedFileInterface $csvFile): int
    {
        // TODO: process rows in file stream, create and persist entities
        // TODO: for extra points wrap the whole import in a transaction and rollback only in case writing to DB fails

        return 0; // number of imported rows
    }

    public function listExpenditureYears(User $user): array
    {
        return $this->expenses->listExpenditureYears($user);
    }

    public function find(int $id): ?Expense
    {
        return $this->expenses->find($id);
    }

    public function delete(int $id): void
    {
        $this->expenses->delete($id);
    }

    public function countByMonth(int $userId, int $year, int $month): int
    {
        return $this->expenses->countByMonth($userId, $year, $month);
    }
}
