<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use PDO;

class ExpenseService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
        private readonly LoggerInterface $logger,
        private readonly PDO $pdo,
    ) {}

    public function list(User $user, int $year, int $month, int $pageNumber, int $pageSize): array
    {
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
        $amountCents = (int) round($amount * 100);
        $expense = new Expense(null, $user->id, $date, $category, $amountCents, $description);
        $this->expenses->save($expense);
    }

    public function update(
        Expense $expense,
        float $amountEuros,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): void {
        if ($amountEuros <= 0) {
            throw new \InvalidArgumentException("Amount must be greater than 0.");
        }

        if (trim($description) === '') {
            throw new \InvalidArgumentException("Description cannot be empty.");
        }

        $now = new DateTimeImmutable();
        if ($date > $now) {
            throw new \InvalidArgumentException("Date must not be in the future.");
        }

        if (trim($category) === '') {
            throw new \InvalidArgumentException("Category must be selected.");
        }

        $expense->amountCents = (int)round($amountEuros * 100);
        $expense->description = $description;
        $expense->date = $date;
        $expense->category = $category;

        $this->expenses->save($expense);
    }

    public function importFromCsv(User $user, UploadedFileInterface $csvFile): int
    {
        $stream = $csvFile->getStream()->detach();
        $imported = 0;
        $skipped = 0;
        $knownCategories = ['groceries', 'transport', 'utilities', 'entertainment', 'health'];

        $this->pdo->beginTransaction();

        try {
            while (($line = fgets($stream)) !== false) {
                $fields = str_getcsv(trim($line));
                if (count($fields) !== 4) {
                    $this->logger->warning("Skipped row (invalid column count): $line");
                    $skipped++;
                    continue;
                }

                [$dateStr, $amountStr, $description, $category] = $fields;
                $description = trim($description);
                $category = strtolower(trim($category));

                if (trim($description) === '') {
                    $this->logger->warning("Skipped row (empty description): $line");
                    $skipped++;
                    continue;
                }

                if (!in_array($category, $knownCategories)) {
                    $this->logger->warning("Skipped row (unknown category): $line");
                    $skipped++;
                    continue;
                }

                try {
                    $date = new DateTimeImmutable($dateStr);
                    $amountCents = (int) round(((float) $amountStr) * 100);
                } catch (\Exception) {
                    $this->logger->warning("Skipped row (invalid data): $line");
                    $skipped++;
                    continue;
                }

                $criteria = [
                    'user_id' => $user->id,
                    'date' => $date->format('Y-m-d'),
                    'description' => $description,
                    'amount_cents' => $amountCents,
                    'category' => $category,
                ];

                $duplicates = $this->expenses->findBy($criteria, 0, 1);
                if (!empty($duplicates)) {
                    $this->logger->info("Skipped row (duplicate): $line");
                    $skipped++;
                    continue;
                }

                $expense = new Expense(null, $user->id, $date, $category, $amountCents, $description);
                $this->expenses->save($expense);
                $imported++;
            }

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error("Import failed: " . $e->getMessage());
        } finally {
            fclose($stream);
        }

        $this->logger->info("CSV import completed. Imported: $imported, Skipped: $skipped");

        return $imported;
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