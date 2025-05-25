<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\AuthService;
use App\Domain\Service\ExpenseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class ExpenseController extends BaseController
{
    private const PAGE_SIZE = 20;

    public function __construct(
        Twig $view,
        private readonly ExpenseService $expenseService,
        private readonly AuthService $authService
    ) {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = new \App\Domain\Entity\User($userId, '', '', new \DateTimeImmutable());

        $queryParams = $request->getQueryParams();
        $page = (int)($queryParams['page'] ?? 1);
        $pageSize = (int)($queryParams['pageSize'] ?? self::PAGE_SIZE);
        $year = (int)($queryParams['year'] ?? (new \DateTimeImmutable())->format('Y'));
        $month = (int)($queryParams['month'] ?? (new \DateTimeImmutable())->format('m'));

        $expenses = $this->expenseService->list($user, $year, $month, $page, $pageSize);
        $totalCount = $this->expenseService->countByMonth($user->id, $year, $month);
        $years = $this->expenseService->listExpenditureYears($user);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        return $this->render($response, 'expenses/index.twig', [
            'expenses'      => $expenses,
            'page'          => $page,
            'pageSize'      => $pageSize,
            'totalCount'    => $totalCount,
            'totalPages'    => (int) ceil($totalCount / $pageSize),
            'selectedYear'  => $year,
            'selectedMonth' => $month,
            'years'         => $years,
            'flash'         => $flash,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        // TODO: implement this action method to display the create expense page

        // Hints:
        // - obtain the list of available categories from configuration and pass to the view

        return $this->render($response, 'expenses/create.twig', [
            'categories' => $this->getCategories(),
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = $this->authService->getById($userId);
        $data = (array)$request->getParsedBody();
        $errors = [];

        // Extract values
        $dateStr = trim($data['date'] ?? '');
        $category = trim($data['category'] ?? '');
        $amountStr = trim($data['amount'] ?? '');
        $description = trim($data['description'] ?? '');

        // Validate date
        $date = null;
        try {
            $date = new \DateTimeImmutable($dateStr);
            if ($date > new \DateTimeImmutable()) {
                $errors['date'] = 'Date must not be in the future.';
            }
        } catch (\Exception) {
            $errors['date'] = 'Invalid date format.';
        }

        // Validate category
        if ($category === '') {
            $errors['category'] = 'Category is required.';
        }

        // Validate amount
        if (!is_numeric($amountStr) || (float)$amountStr <= 0) {
            $errors['amount'] = 'Amount must be a number greater than 0.';
        }

        // Validate description
        if ($description === '') {
            $errors['description'] = 'Description is required.';
        }

        // If there are validation errors, show the form again
        if (!empty($errors)) {
            return $this->render($response, 'expenses/create.twig', [
                'errors' => $errors,
                'values' => $data,
                'categories' => $this->getCategories(),
            ]);
        }

        $amount = (float) $amountStr;
        $this->expenseService->create($user, $amount, $description, $date, $category);

        return $response->withHeader('Location', '/expenses')->withStatus(302);
    }

    public function edit(Request $request, Response $response, array $routeParams): Response
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $expenseId = (int)($routeParams['id'] ?? 0);
        $expense = $this->expenseService->find($expenseId);

        // Verify ownership and existence
        if (!$expense || $expense->userId !== $userId) {
            return $response->withStatus(403);
        }

        $values = [
            'date' => $expense->date->format('Y-m-d'),
            'category' => $expense->category,
            'amount' => $expense->amountCents / 100,
            'description' => $expense->description,
        ];

        return $this->render($response, 'expenses/edit.twig', [
            'values' => $values,
            'expense' => $expense,
            'categories' => $this->getCategories(),
        ]);
    }

    private function validateExpenseData(array $data): array
    {
        $errors = [];

        try {
            $date = new \DateTimeImmutable(trim($data['date'] ?? ''));
            if ($date > new \DateTimeImmutable()) {
                $errors['date'] = 'Date must not be in the future.';
            }
        } catch (\Exception) {
            $errors['date'] = 'Invalid date format.';
        }

        if (empty($data['category'])) {
            $errors['category'] = 'Category is required.';
        }

        if (!is_numeric($data['amount']) || (float)$data['amount'] <= 0) {
            $errors['amount'] = 'Amount must be a number greater than 0.';
        }

        if (empty(trim($data['description'] ?? ''))) {
            $errors['description'] = 'Description is required.';
        }

        return $errors;
    }

    public function update(Request $request, Response $response, array $routeParams): Response
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $expenseId = (int)($routeParams['id'] ?? 0);
        $expense = $this->expenseService->find($expenseId);

        if (!$expense || $expense->userId !== $userId) {
            return $response->withStatus(403);
        }

        $data = (array)$request->getParsedBody();
        $errors = $this->validateExpenseData($data);

        if (!empty($errors)) {
            return $this->render($response, 'expenses/edit.twig', [
                'errors' => $errors,
                'values' => $data,
                'expense' => $expense,
                'categories' => $this->getCategories(),
            ]);
        }

        $date = new \DateTimeImmutable($data['date']);

        $amount = (float) $data['amount'];
        $this->expenseService->update($expense, $amount, trim($data['description']), $date, trim($data['category']));

        return $response->withHeader('Location', '/expenses')->withStatus(302);
    }

    public function destroy(Request $request, Response $response, array $routeParams): Response
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $expenseId = (int)($routeParams['id'] ?? 0);
        $expense = $this->expenseService->find($expenseId);

        if (!$expense || $expense->userId !== $userId) {
            $_SESSION['flash'] = '❌ Failed to delete expense.';
            return $response->withHeader('Location', '/expenses')->withStatus(302);
        }

        $this->expenseService->delete($expenseId);
        $_SESSION['flash'] = '✅ Expense deleted successfully.';

        return $response->withHeader('Location', '/expenses')->withStatus(302);
    }


    public function import(Request $request, Response $response): Response
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = $this->authService->getById($userId);
        $uploadedFile = $request->getUploadedFiles()['csv_file'] ?? null;

        if ($uploadedFile === null || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
            $_SESSION['flash'] = '❌ Failed to upload CSV file.';
            return $response->withHeader('Location', '/expenses')->withStatus(302);
        }

        $importedCount = $this->expenseService->importFromCsv($user, $uploadedFile);
        $_SESSION['flash'] = "✅ Successfully imported $importedCount expenses.";

        return $response->withHeader('Location', '/expenses')->withStatus(302);
    }

    private function getCategories(): array
    {
        return ['groceries', 'transport', 'utilities', 'entertainment', 'health'];
    }
}
