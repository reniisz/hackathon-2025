<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use App\Domain\Service\CategoryBudgetProvider;

class AlertGenerator
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
        private readonly CategoryBudgetProvider $budgetProvider,
    ) {}

    public function generate(User $user, int $year, int $month): array
    {
        $alerts = [];

        $criteria = [
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
        ];

        $totals = $this->expenses->sumAmountsByCategory($criteria); // value is in cents

        //debug
        //var_dump($totals);
        //exit;

        foreach ($totals as $category => $data) {
            $amountInEuros = $data['value'] / 100;
            $budget = $this->budgetProvider->getBudgetForCategory(strtolower($category));

            if ($budget !== null && $amountInEuros > $budget) {
                $diff = number_format($amountInEuros - $budget, 2, '.', ',');
                $alerts[] = "⚠ {$category} budget exceeded by {$diff} €";
            }
        }

        return $alerts;
    }
}
