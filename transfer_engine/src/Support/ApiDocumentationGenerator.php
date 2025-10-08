<?php
/**
 * ApiDocumentationGenerator.php - Automatic API Documentation Generator
 * 
 * Generates comprehensive API documentation in OpenAPI 3.0 (Swagger) format
 * with automatic endpoint discovery, schema generation, and interactive UI.
 * 
 * Features:
 * - OpenAPI 3.0 specification generation
 * - Automatic endpoint discovery from routes
 * - Schema generation from models
 * - Request/response examples
 * - Authentication documentation
 * - Interactive Swagger UI integration
 * - Markdown documentation export
 * - Postman collection export
 * - API versioning support
 * - Deprecation tracking
 * 
 * @package VapeshedTransfer
 * @subpackage Support
 * @author Vapeshed Transfer Engine
 * @version 2.0.0
 */

namespace Unified\Support;

use Unified\Support\Logger;
use Unified\Support\NeuroContext;

class ApiDocumentationGenerator
{
    private Logger $logger;
    private array $config;
    private array $spec;

    /**
     * Initialize ApiDocumentationGenerator
     *
     * @param Logger $logger Logger instance
     * @param array $config Configuration options
     */
    public function __construct(Logger $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->spec = $this->initializeSpec();
    }

    /**
     * Generate complete API documentation
     *
     * @param array $routes Array of route definitions
     * @param array $options Generation options
     * @return array Generated documentation
     */
    public function generate(array $routes, array $options = []): array
    {
        $startTime = microtime(true);
        
        // Process routes
        foreach ($routes as $route) {
            $this->addPath($route);
        }
        
        // Add common components
        $this->addSecuritySchemes();
        $this->addCommonSchemas();
        $this->addCommonResponses();
        
        // Generate output formats
        $outputs = [];
        
        if ($options['openapi'] ?? true) {
            $outputs['openapi'] = $this->generateOpenApi();
        }
        
        if ($options['markdown'] ?? true) {
            $outputs['markdown'] = $this->generateMarkdown();
        }
        
        if ($options['postman'] ?? false) {
            $outputs['postman'] = $this->generatePostman();
        }
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $this->logger->info('API documentation generated', NeuroContext::wrap('api_doc_generator', [
            'routes_count' => count($routes),
            'outputs' => array_keys($outputs),
            'duration_ms' => $duration,
        ]));
        
        return [
            'outputs' => $outputs,
            'generation_time_ms' => $duration,
            'routes_count' => count($routes),
            'endpoints_count' => count($this->spec['paths'] ?? []),
        ];
    }

    /**
     * Generate OpenAPI 3.0 specification
     *
     * @return string JSON specification
     */
    public function generateOpenApi(): string
    {
        return json_encode($this->spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate Markdown documentation
     *
     * @return string Markdown documentation
     */
    public function generateMarkdown(): string
    {
        $markdown = "# {$this->spec['info']['title']}\n\n";
        $markdown .= "{$this->spec['info']['description']}\n\n";
        $markdown .= "**Version:** {$this->spec['info']['version']}\n\n";
        
        $markdown .= "## Base URL\n\n";
        $markdown .= "`{$this->spec['servers'][0]['url']}`\n\n";
        
        $markdown .= "## Authentication\n\n";
        $markdown .= "API uses bearer token authentication. Include token in Authorization header:\n";
        $markdown .= "```\nAuthorization: Bearer YOUR_TOKEN\n```\n\n";
        
        $markdown .= "## Endpoints\n\n";
        
        foreach ($this->spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if ($method === 'parameters') {
                    continue;
                }
                
                $markdown .= "### {$operation['summary']}\n\n";
                $markdown .= "`" . strtoupper($method) . " {$path}`\n\n";
                $markdown .= "{$operation['description']}\n\n";
                
                // Parameters
                if (!empty($operation['parameters'])) {
                    $markdown .= "**Parameters:**\n\n";
                    $markdown .= "| Name | Type | Required | Description |\n";
                    $markdown .= "|------|------|----------|-------------|\n";
                    
                    foreach ($operation['parameters'] as $param) {
                        $required = ($param['required'] ?? false) ? 'Yes' : 'No';
                        $markdown .= "| {$param['name']} | {$param['schema']['type']} | {$required} | {$param['description']} |\n";
                    }
                    
                    $markdown .= "\n";
                }
                
                // Request body
                if (!empty($operation['requestBody'])) {
                    $markdown .= "**Request Body:**\n\n";
                    $markdown .= "```json\n";
                    $markdown .= json_encode($this->getExampleFromSchema($operation['requestBody']['content']['application/json']['schema'] ?? []), JSON_PRETTY_PRINT);
                    $markdown .= "\n```\n\n";
                }
                
                // Responses
                $markdown .= "**Responses:**\n\n";
                foreach ($operation['responses'] as $code => $response) {
                    $markdown .= "**{$code}** - {$response['description']}\n\n";
                    
                    if (!empty($response['content']['application/json']['example'])) {
                        $markdown .= "```json\n";
                        $markdown .= json_encode($response['content']['application/json']['example'], JSON_PRETTY_PRINT);
                        $markdown .= "\n```\n\n";
                    }
                }
                
                $markdown .= "---\n\n";
            }
        }
        
        return $markdown;
    }

    /**
     * Generate Postman collection
     *
     * @return string JSON collection
     */
    public function generatePostman(): string
    {
        $collection = [
            'info' => [
                'name' => $this->spec['info']['title'],
                'description' => $this->spec['info']['description'],
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'auth' => [
                'type' => 'bearer',
                'bearer' => [
                    ['key' => 'token', 'value' => '{{api_token}}', 'type' => 'string'],
                ],
            ],
            'item' => [],
            'variable' => [
                [
                    'key' => 'base_url',
                    'value' => $this->spec['servers'][0]['url'],
                ],
                [
                    'key' => 'api_token',
                    'value' => '',
                    'type' => 'string',
                ],
            ],
        ];
        
        foreach ($this->spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if ($method === 'parameters') {
                    continue;
                }
                
                $request = [
                    'name' => $operation['summary'],
                    'request' => [
                        'method' => strtoupper($method),
                        'header' => [
                            ['key' => 'Content-Type', 'value' => 'application/json'],
                        ],
                        'url' => [
                            'raw' => '{{base_url}}' . $path,
                            'host' => ['{{base_url}}'],
                            'path' => array_filter(explode('/', $path)),
                        ],
                        'description' => $operation['description'],
                    ],
                ];
                
                // Add request body if present
                if (!empty($operation['requestBody'])) {
                    $schema = $operation['requestBody']['content']['application/json']['schema'] ?? [];
                    $request['request']['body'] = [
                        'mode' => 'raw',
                        'raw' => json_encode($this->getExampleFromSchema($schema), JSON_PRETTY_PRINT),
                    ];
                }
                
                $collection['item'][] = $request;
            }
        }
        
        return json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Add path to specification
     *
     * @param array $route Route definition
     * @return void
     */
    private function addPath(array $route): void
    {
        $path = $route['path'];
        $method = strtolower($route['method'] ?? 'get');
        
        if (!isset($this->spec['paths'][$path])) {
            $this->spec['paths'][$path] = [];
        }
        
        $operation = [
            'summary' => $route['summary'] ?? $this->generateSummary($path, $method),
            'description' => $route['description'] ?? '',
            'operationId' => $route['operation_id'] ?? $this->generateOperationId($path, $method),
            'tags' => $route['tags'] ?? [$this->extractTag($path)],
            'parameters' => $this->extractParameters($route),
            'responses' => $this->generateResponses($route),
        ];
        
        // Add request body for POST/PUT/PATCH
        if (in_array($method, ['post', 'put', 'patch'])) {
            $operation['requestBody'] = $this->generateRequestBody($route);
        }
        
        // Add security if required
        if ($route['auth'] ?? true) {
            $operation['security'] = [['bearerAuth' => []]];
        }
        
        // Add deprecation notice if applicable
        if ($route['deprecated'] ?? false) {
            $operation['deprecated'] = true;
        }
        
        $this->spec['paths'][$path][$method] = $operation;
    }

    /**
     * Extract parameters from route
     *
     * @param array $route Route definition
     * @return array Parameters
     */
    private function extractParameters(array $route): array
    {
        $parameters = [];
        
        // Path parameters
        if (preg_match_all('/{([^}]+)}/', $route['path'], $matches)) {
            foreach ($matches[1] as $param) {
                $parameters[] = [
                    'name' => $param,
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                    'description' => ucfirst($param) . ' identifier',
                ];
            }
        }
        
        // Query parameters
        foreach ($route['query_params'] ?? [] as $param => $info) {
            $parameters[] = [
                'name' => $param,
                'in' => 'query',
                'required' => $info['required'] ?? false,
                'schema' => ['type' => $info['type'] ?? 'string'],
                'description' => $info['description'] ?? '',
            ];
        }
        
        return $parameters;
    }

    /**
     * Generate request body specification
     *
     * @param array $route Route definition
     * @return array Request body spec
     */
    private function generateRequestBody(array $route): array
    {
        $schema = $route['request_schema'] ?? [
            'type' => 'object',
            'properties' => [],
        ];
        
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => $schema,
                    'example' => $this->getExampleFromSchema($schema),
                ],
            ],
        ];
    }

    /**
     * Generate responses specification
     *
     * @param array $route Route definition
     * @return array Responses spec
     */
    private function generateResponses(array $route): array
    {
        $responses = [
            '200' => [
                'description' => 'Successful response',
                'content' => [
                    'application/json' => [
                        'schema' => $route['response_schema'] ?? ['type' => 'object'],
                        'example' => $route['response_example'] ?? ['success' => true, 'data' => []],
                    ],
                ],
            ],
            '400' => [
                'description' => 'Bad request',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error'],
                        'example' => [
                            'success' => false,
                            'error' => [
                                'code' => 'VALIDATION_ERROR',
                                'message' => 'Invalid input parameters',
                            ],
                        ],
                    ],
                ],
            ],
            '401' => [
                'description' => 'Unauthorized',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error'],
                    ],
                ],
            ],
            '500' => [
                'description' => 'Internal server error',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error'],
                    ],
                ],
            ],
        ];
        
        // Add custom responses
        foreach ($route['responses'] ?? [] as $code => $response) {
            $responses[$code] = $response;
        }
        
        return $responses;
    }

    /**
     * Add security schemes to specification
     *
     * @return void
     */
    private function addSecuritySchemes(): void
    {
        $this->spec['components']['securitySchemes'] = [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
                'description' => 'Enter your API bearer token',
            ],
        ];
    }

    /**
     * Add common schemas to specification
     *
     * @return void
     */
    private function addCommonSchemas(): void
    {
        $this->spec['components']['schemas'] = [
            'Error' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => false],
                    'error' => [
                        'type' => 'object',
                        'properties' => [
                            'code' => ['type' => 'string', 'example' => 'ERROR_CODE'],
                            'message' => ['type' => 'string', 'example' => 'Error description'],
                            'details' => ['type' => 'object'],
                        ],
                    ],
                    'request_id' => ['type' => 'string', 'example' => '550e8400-e29b-41d4-a716-446655440000'],
                ],
            ],
            'SuccessResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'data' => ['type' => 'object'],
                    'meta' => ['type' => 'object'],
                    'request_id' => ['type' => 'string'],
                ],
            ],
            'PaginatedResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                    'meta' => [
                        'type' => 'object',
                        'properties' => [
                            'page' => ['type' => 'integer'],
                            'per_page' => ['type' => 'integer'],
                            'total' => ['type' => 'integer'],
                            'total_pages' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Add common responses to specification
     *
     * @return void
     */
    private function addCommonResponses(): void
    {
        $this->spec['components']['responses'] = [
            'Unauthorized' => [
                'description' => 'Authentication required',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error'],
                    ],
                ],
            ],
            'Forbidden' => [
                'description' => 'Access forbidden',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error'],
                    ],
                ],
            ],
            'NotFound' => [
                'description' => 'Resource not found',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate example from schema
     *
     * @param array $schema Schema definition
     * @return mixed Example value
     */
    private function getExampleFromSchema(array $schema)
    {
        if (isset($schema['example'])) {
            return $schema['example'];
        }
        
        $type = $schema['type'] ?? 'object';
        
        return match($type) {
            'object' => $this->generateObjectExample($schema),
            'array' => [$this->getExampleFromSchema($schema['items'] ?? ['type' => 'string'])],
            'string' => 'string',
            'integer' => 0,
            'number' => 0.0,
            'boolean' => true,
            default => null,
        };
    }

    /**
     * Generate example for object schema
     *
     * @param array $schema Schema definition
     * @return array Example object
     */
    private function generateObjectExample(array $schema): array
    {
        $example = [];
        
        foreach ($schema['properties'] ?? [] as $name => $prop) {
            $example[$name] = $this->getExampleFromSchema($prop);
        }
        
        return $example;
    }

    /**
     * Generate summary from path and method
     *
     * @param string $path Path
     * @param string $method HTTP method
     * @return string Summary
     */
    private function generateSummary(string $path, string $method): string
    {
        $resource = basename($path);
        $resource = str_replace(['-', '_'], ' ', $resource);
        
        return match($method) {
            'get' => "Get {$resource}",
            'post' => "Create {$resource}",
            'put' => "Update {$resource}",
            'patch' => "Partially update {$resource}",
            'delete' => "Delete {$resource}",
            default => ucfirst($method) . " {$resource}",
        };
    }

    /**
     * Generate operation ID from path and method
     *
     * @param string $path Path
     * @param string $method HTTP method
     * @return string Operation ID
     */
    private function generateOperationId(string $path, string $method): string
    {
        $parts = array_filter(explode('/', $path));
        $parts = array_map(fn($p) => preg_replace('/{([^}]+)}/', 'By' . ucfirst('$1'), $p), $parts);
        $parts = array_map('ucfirst', $parts);
        
        return $method . implode('', $parts);
    }

    /**
     * Extract tag from path
     *
     * @param string $path Path
     * @return string Tag
     */
    private function extractTag(string $path): string
    {
        $parts = array_filter(explode('/', $path));
        return ucfirst($parts[0] ?? 'default');
    }

    /**
     * Initialize OpenAPI specification structure
     *
     * @return array Spec structure
     */
    private function initializeSpec(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $this->config['title'],
                'description' => $this->config['description'],
                'version' => $this->config['version'],
                'contact' => $this->config['contact'] ?? [],
            ],
            'servers' => [
                [
                    'url' => $this->config['base_url'],
                    'description' => $this->config['server_description'] ?? 'API Server',
                ],
            ],
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => [],
                'responses' => [],
            ],
            'tags' => [],
        ];
    }

    /**
     * Save documentation to files
     *
     * @param array $outputs Generated outputs
     * @param string $directory Output directory
     * @return array Saved file paths
     */
    public function saveToFiles(array $outputs, string $directory): array
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $files = [];
        
        foreach ($outputs as $format => $content) {
            $extension = match($format) {
                'openapi' => 'json',
                'markdown' => 'md',
                'postman' => 'json',
                default => 'txt',
            };
            
            $filename = "api_documentation.{$extension}";
            if ($format === 'postman') {
                $filename = 'postman_collection.json';
            }
            
            $filepath = $directory . '/' . $filename;
            file_put_contents($filepath, $content);
            
            $files[$format] = $filepath;
        }
        
        $this->logger->info('Documentation saved to files', NeuroContext::wrap('api_doc_generator', [
            'directory' => $directory,
            'files' => array_keys($files),
        ]));
        
        return $files;
    }

    /**
     * Get default configuration
     *
     * @return array Default config
     */
    private function getDefaultConfig(): array
    {
        return [
            'title' => 'Vapeshed Transfer Engine API',
            'description' => 'Complete API documentation for the Vapeshed Transfer Engine',
            'version' => '2.0.0',
            'base_url' => 'https://api.example.com/v1',
            'server_description' => 'Production API Server',
            'contact' => [
                'name' => 'API Support',
                'email' => 'api@example.com',
            ],
        ];
    }
}
