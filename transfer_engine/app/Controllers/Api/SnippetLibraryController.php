<?php

/**
 * SnippetLibraryController
 *
 * Code snippet management with categorization, search, and execution
 *
 * @package VapeshedTransfer\Controllers\Api
 * @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @version 1.0.0
 */

namespace VapeshedTransfer\Controllers\Api;

use VapeshedTransfer\Controllers\BaseController;
use VapeshedTransfer\Core\Logger;
use VapeshedTransfer\Core\Security;

class SnippetLibraryController extends BaseController
{
    private Logger $logger;
    private Security $security;

    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger();
        $this->security = new Security();
    }

    /**
     * Get all code snippets
     */
    public function getSnippets(): array
    {
        try {
            $category = $_GET['category'] ?? 'all';
            $language = $_GET['language'] ?? 'all';
            $complexity = $_GET['complexity'] ?? 'all';

            $snippets = $this->fetchSnippets($category, $language, $complexity);

            return $this->successResponse([
                'snippets' => $snippets,
                'count' => count($snippets),
                'filters' => [
                    'category' => $category,
                    'language' => $language,
                    'complexity' => $complexity
                ]
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get snippets: ' . $e->getMessage());
        }
    }

    /**
     * Get snippet by ID
     */
    public function getSnippet(): array
    {
        try {
            $snippetId = $_GET['id'] ?? '';

            if (!$snippetId) {
                return $this->errorResponse('Snippet ID is required');
            }

            $snippet = $this->fetchSnippetById($snippetId);

            if (!$snippet) {
                return $this->errorResponse('Snippet not found', 404);
            }

            return $this->successResponse($snippet);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get snippet: ' . $e->getMessage());
        }
    }

    /**
     * Search snippets
     */
    public function searchSnippets(): array
    {
        try {
            $query = $_GET['q'] ?? '';

            if (strlen($query) < 2) {
                return $this->errorResponse('Search query must be at least 2 characters');
            }

            $results = $this->performSearch($query);

            return $this->successResponse([
                'query' => $query,
                'results' => $results,
                'count' => count($results)
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Search failed: ' . $e->getMessage());
        }
    }

    /**
     * Save a new snippet
     */
    public function saveSnippet(): array
    {
        try {
            if (!$this->security->validateCsrfToken($_POST['_token'] ?? '')) {
                return $this->errorResponse('Invalid CSRF token', 403);
            }

            $snippet = [
                'id' => uniqid('snip_'),
                'name' => $_POST['name'] ?? '',
                'category' => $_POST['category'] ?? 'general',
                'language' => $_POST['language'] ?? 'php',
                'complexity' => $_POST['complexity'] ?? 'intermediate',
                'description' => $_POST['description'] ?? '',
                'code' => $_POST['code'] ?? '',
                'tags' => json_decode($_POST['tags'] ?? '[]', true),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Validate required fields
            if (empty($snippet['name']) || empty($snippet['code'])) {
                return $this->errorResponse('Name and code are required');
            }

            // In real implementation, save to database
            $this->logger->info('Snippet saved', ['id' => $snippet['id'], 'name' => $snippet['name']]);

            return $this->successResponse([
                'message' => 'Snippet saved successfully',
                'snippet' => $snippet
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to save snippet: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing snippet
     */
    public function updateSnippet(): array
    {
        try {
            if (!$this->security->validateCsrfToken($_POST['_token'] ?? '')) {
                return $this->errorResponse('Invalid CSRF token', 403);
            }

            $snippetId = $_POST['id'] ?? '';

            if (!$snippetId) {
                return $this->errorResponse('Snippet ID is required');
            }

            $updates = [
                'name' => $_POST['name'] ?? null,
                'category' => $_POST['category'] ?? null,
                'language' => $_POST['language'] ?? null,
                'complexity' => $_POST['complexity'] ?? null,
                'description' => $_POST['description'] ?? null,
                'code' => $_POST['code'] ?? null,
                'tags' => isset($_POST['tags']) ? json_decode($_POST['tags'], true) : null,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Remove null values
            $updates = array_filter($updates, function($value) {
                return $value !== null;
            });

            $this->logger->info('Snippet updated', ['id' => $snippetId]);

            return $this->successResponse([
                'message' => 'Snippet updated successfully',
                'id' => $snippetId,
                'updates' => $updates
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update snippet: ' . $e->getMessage());
        }
    }

    /**
     * Delete a snippet
     */
    public function deleteSnippet(): array
    {
        try {
            if (!$this->security->validateCsrfToken($_POST['_token'] ?? '')) {
                return $this->errorResponse('Invalid CSRF token', 403);
            }

            $snippetId = $_POST['id'] ?? '';

            if (!$snippetId) {
                return $this->errorResponse('Snippet ID is required');
            }

            // In real implementation, delete from database
            $this->logger->info('Snippet deleted', ['id' => $snippetId]);

            return $this->successResponse([
                'message' => 'Snippet deleted successfully',
                'id' => $snippetId
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete snippet: ' . $e->getMessage());
        }
    }

    /**
     * Execute a snippet (with safety checks)
     */
    public function executeSnippet(): array
    {
        try {
            if (!$this->security->validateCsrfToken($_POST['_token'] ?? '')) {
                return $this->errorResponse('Invalid CSRF token', 403);
            }

            $snippetId = $_POST['id'] ?? '';
            $simulate = filter_var($_POST['simulate'] ?? true, FILTER_VALIDATE_BOOLEAN);

            if (!$snippetId) {
                return $this->errorResponse('Snippet ID is required');
            }

            $snippet = $this->fetchSnippetById($snippetId);

            if (!$snippet) {
                return $this->errorResponse('Snippet not found', 404);
            }

            // Safety check
            if (!$simulate && !$this->isSnippetSafe($snippet['code'])) {
                return $this->errorResponse('Snippet contains potentially dangerous operations. Use simulate mode.', 403);
            }

            $startTime = microtime(true);

            if ($simulate) {
                $result = $this->simulateSnippetExecution($snippet);
            } else {
                $result = $this->executeSnippetCode($snippet);
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return $this->successResponse([
                'snippet' => $snippet,
                'result' => $result,
                'execution_time_ms' => $executionTime,
                'simulated' => $simulate
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Snippet execution failed: ' . $e->getMessage());
        }
    }

    /**
     * Get snippet categories
     */
    public function getCategories(): array
    {
        $categories = [
            'vend_api' => ['name' => 'Vend API', 'count' => 15],
            'lightspeed_api' => ['name' => 'Lightspeed API', 'count' => 12],
            'database' => ['name' => 'Database Queries', 'count' => 20],
            'transfers' => ['name' => 'Transfer Operations', 'count' => 10],
            'webhooks' => ['name' => 'Webhook Handlers', 'count' => 8],
            'utilities' => ['name' => 'Utility Functions', 'count' => 18],
            'general' => ['name' => 'General', 'count' => 25]
        ];

        return $this->successResponse(['categories' => $categories]);
    }

    /**
     * Get popular snippets
     */
    public function getPopular(): array
    {
        try {
            $limit = min((int)($_GET['limit'] ?? 10), 50);

            $popular = $this->fetchPopularSnippets($limit);

            return $this->successResponse([
                'snippets' => $popular,
                'count' => count($popular)
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get popular snippets: ' . $e->getMessage());
        }
    }

    /**
     * Fetch snippets with filters
     */
    private function fetchSnippets(string $category, string $language, string $complexity): array
    {
        // Mock data - in real implementation, query database
        $allSnippets = [
            [
                'id' => 'snip_1',
                'name' => 'Vend Product Sync',
                'category' => 'vend_api',
                'language' => 'php',
                'complexity' => 'intermediate',
                'description' => 'Sync products from Vend API',
                'tags' => ['vend', 'products', 'sync'],
                'usage_count' => 45,
                'created_at' => '2025-10-01 10:00:00'
            ],
            [
                'id' => 'snip_2',
                'name' => 'Transfer Validation',
                'category' => 'transfers',
                'language' => 'php',
                'complexity' => 'beginner',
                'description' => 'Validate transfer data before processing',
                'tags' => ['transfer', 'validation'],
                'usage_count' => 32,
                'created_at' => '2025-10-02 14:30:00'
            ],
            [
                'id' => 'snip_3',
                'name' => 'Webhook Signature Validation',
                'category' => 'webhooks',
                'language' => 'php',
                'complexity' => 'advanced',
                'description' => 'Validate webhook signatures for security',
                'tags' => ['webhook', 'security', 'validation'],
                'usage_count' => 28,
                'created_at' => '2025-10-03 09:15:00'
            ]
        ];

        // Apply filters
        return array_filter($allSnippets, function($snippet) use ($category, $language, $complexity) {
            if ($category !== 'all' && $snippet['category'] !== $category) return false;
            if ($language !== 'all' && $snippet['language'] !== $language) return false;
            if ($complexity !== 'all' && $snippet['complexity'] !== $complexity) return false;
            return true;
        });
    }

    /**
     * Fetch snippet by ID
     */
    private function fetchSnippetById(string $snippetId): ?array
    {
        // Mock implementation
        return [
            'id' => $snippetId,
            'name' => 'Example Snippet',
            'category' => 'general',
            'language' => 'php',
            'complexity' => 'intermediate',
            'description' => 'An example code snippet',
            'code' => '<?php\necho "Hello, World!";\n',
            'tags' => ['example', 'demo'],
            'usage_count' => 15,
            'created_at' => '2025-10-01 10:00:00',
            'updated_at' => '2025-10-05 15:30:00'
        ];
    }

    /**
     * Perform search
     */
    private function performSearch(string $query): array
    {
        // Mock search results
        return [
            [
                'id' => 'snip_search_1',
                'name' => 'Search Result 1',
                'category' => 'general',
                'relevance_score' => 0.95,
                'description' => "Contains query: {$query}"
            ],
            [
                'id' => 'snip_search_2',
                'name' => 'Search Result 2',
                'category' => 'vend_api',
                'relevance_score' => 0.82,
                'description' => "Related to: {$query}"
            ]
        ];
    }

    /**
     * Check if snippet code is safe to execute
     */
    private function isSnippetSafe(string $code): bool
    {
        $dangerousFunctions = [
            'exec', 'shell_exec', 'system', 'passthru', 'eval',
            'unlink', 'rmdir', 'file_put_contents', 'chmod'
        ];

        foreach ($dangerousFunctions as $func) {
            if (stripos($code, $func) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Simulate snippet execution
     */
    private function simulateSnippetExecution(array $snippet): array
    {
        return [
            'success' => true,
            'output' => 'Simulated execution output',
            'message' => 'Snippet would execute successfully',
            'operations_performed' => rand(1, 5)
        ];
    }

    /**
     * Execute snippet code (with extreme caution)
     */
    private function executeSnippetCode(array $snippet): array
    {
        // In a real implementation, this would use proper sandboxing
        throw new \Exception('Live snippet execution not implemented for security reasons');
    }

    /**
     * Fetch popular snippets
     */
    private function fetchPopularSnippets(int $limit): array
    {
        $snippets = $this->fetchSnippets('all', 'all', 'all');

        // Sort by usage_count
        usort($snippets, function($a, $b) {
            return $b['usage_count'] - $a['usage_count'];
        });

        return array_slice($snippets, 0, $limit);
    }
}