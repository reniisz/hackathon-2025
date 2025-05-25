<?php

declare(strict_types=1);

namespace App\Domain\Service;

class CategoryBudgetProvider
{
    private array $budgets;

    public function __construct(string $budgetJson)
    {
        $decoded = json_decode($budgetJson, true);
        if (is_array($decoded)) {
            // normalize keys to lowercase
            $this->budgets = [];
            foreach ($decoded as $key => $value) {
                $normalizedKey = strtolower(trim($key));
                $this->budgets[$normalizedKey] = (float) $value;
            }
        } else {
            $this->budgets = [];
        }
    }

    public function getBudgetForCategory(string $category): ?float
    {
        $key = strtolower(trim($category));
        return $this->budgets[$key] ?? null;
    }

    public function getAllBudgets(): array
    {
        return $this->budgets;
    }
}
