<?php

declare(strict_types=1);

namespace App\Domain\Service;

class CategoryBudgetProvider
{
    private array $budgets;

    public function __construct(string $budgetJson)
    {
        $decoded = json_decode($budgetJson, true);
        $this->budgets = is_array($decoded) ? $decoded : [];
    }

    public function getBudgetForCategory(string $category): ?float
    {
        return $this->budgets[$category] ?? null;
    }

    public function getAllBudgets(): array
    {
        return $this->budgets;
    }
}
