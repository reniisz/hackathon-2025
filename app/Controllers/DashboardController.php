<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class DashboardController extends BaseController
{
    public function __construct(
        Twig $view,
        private readonly AuthService $authService,
        private readonly ExpenseService $expenseService,
        private readonly MonthlySummaryService $monthlySummaryService,
        private readonly AlertGenerator $alertGenerator,
    )
    {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = $this->authService->getById($userId);

        $query = $request->getQueryParams();
        $year = (int)($query['year'] ?? (new \DateTimeImmutable())->format('Y'));
        $month = (int)($query['month'] ?? (new \DateTimeImmutable())->format('m'));

        $years = $this->expenseService->listExpenditureYears($user);

        $total = $this->monthlySummaryService->computeTotalExpenditure($user, $year, $month);
        $totals = $this->monthlySummaryService->computePerCategoryTotals($user, $year, $month);
        $averages = $this->monthlySummaryService->computePerCategoryAverages($user, $year, $month);

        $alerts = [];
        if ($year === (int)(new \DateTimeImmutable())->format('Y') &&
            $month === (int)(new \DateTimeImmutable())->format('m')) {
            $alerts = $this->alertGenerator->generate($totals);
        }

        return $this->render($response, 'dashboard.twig', [
            'alerts'                => $alerts,
            'totalForMonth'         => $total,
            'totalsForCategories'   => $totals,
            'averagesForCategories' => $averages,
            'years'                 => $years,
            'selectedYear'          => $year,
            'selectedMonth'         => $month,
        ]);
    }
}
