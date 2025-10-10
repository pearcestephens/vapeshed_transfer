<?php
/**
 * Code Snippet Library Interface
 * Copy-pasteable code examples with live testing integration
 */
?>

<div class="snippet-library">
    <!-- Header -->
    <div class="lab-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i data-feather="code" class="me-2"></i>
                Code Snippet Library
            </h4>
            <p class="text-muted mb-0">Copy-pasteable code examples with integrated testing</p>
        </div>
        <div class="lab-actions">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-success btn-sm" id="addSnippet">
                    <i data-feather="plus" class="me-1"></i>
                    Add Snippet
                </button>
                <button type="button" class="btn btn-outline-info btn-sm" id="importSnippets">
                    <i data-feather="upload" class="me-1"></i>
                    Import
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="exportSnippets">
                    <i data-feather="download" class="me-1"></i>
                    Export
                </button>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Panel: Categories & Filters -->
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i data-feather="filter" class="me-2"></i>
                        Categories & Search
                    </h6>
                </div>
                <div class="card-body">
                    <!-- Search -->
                    <div class="form-group mb-3">
                        <label for="snippetSearch" class="form-label">Search Snippets</label>
                        <div class="input-group">
                            <input type="text" id="snippetSearch" class="form-control" placeholder="Search by name, language, or tags...">
                            <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                <i data-feather="x" width="16" height="16"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Language Filter -->
                    <div class="form-group mb-3">
                        <label for="languageFilter" class="form-label">Language</label>
                        <select id="languageFilter" class="form-select">
                            <option value="">All Languages</option>
                            <option value="php">PHP</option>
                            <option value="javascript">JavaScript</option>
                            <option value="curl">cURL</option>
                            <option value="python">Python</option>
                            <option value="sql">SQL</option>
                            <option value="bash">Bash</option>
                        </select>
                    </div>

                    <!-- Category Filter -->
                    <div class="category-filters">
                        <label class="form-label">Categories</label>
                        <div class="category-list">
                            <div class="form-check">
                                <input class="form-check-input category-filter" type="checkbox" id="catTransfers" value="transfers" checked>
                                <label class="form-check-label" for="catTransfers">
                                    Transfer Engine <span class="badge bg-secondary ms-1">12</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input category-filter" type="checkbox" id="catPurchaseOrders" value="purchase_orders" checked>
                                <label class="form-check-label" for="catPurchaseOrders">
                                    Purchase Orders <span class="badge bg-secondary ms-1">8</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input category-filter" type="checkbox" id="catInventory" value="inventory" checked>
                                <label class="form-check-label" for="catInventory">
                                    Inventory <span class="badge bg-secondary ms-1">6</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input category-filter" type="checkbox" id="catWebhooks" value="webhooks" checked>
                                <label class="form-check-label" for="catWebhooks">
                                    Webhooks <span class="badge bg-secondary ms-1">5</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input category-filter" type="checkbox" id="catVendAPI" value="vend_api" checked>
                                <label class="form-check-label" for="catVendAPI">
                                    Vend API <span class="badge bg-secondary ms-1">10</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input category-filter" type="checkbox" id="catUtilities" value="utilities" checked>
                                <label class="form-check-label" for="catUtilities">
                                    Utilities <span class="badge bg-secondary ms-1">7</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input category-filter" type="checkbox" id="catTesting" value="testing" checked>
                                <label class="form-check-label" for="catTesting">
                                    Testing <span class="badge bg-secondary ms-1">9</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Complexity Filter -->
                    <div class="form-group mb-3">
                        <label for="complexityFilter" class="form-label">Complexity</label>
                        <select id="complexityFilter" class="form-select">
                            <option value="">All Levels</option>
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                        </select>
                    </div>

                    <!-- Tags -->
                    <div class="popular-tags">
                        <label class="form-label">Popular Tags</label>
                        <div id="popularTags" class="tag-cloud">
                            <span class="badge bg-light text-dark me-1 mb-1 tag-filter" data-tag="api">api</span>
                            <span class="badge bg-light text-dark me-1 mb-1 tag-filter" data-tag="authentication">auth</span>
                            <span class="badge bg-light text-dark me-1 mb-1 tag-filter" data-tag="validation">validation</span>
                            <span class="badge bg-light text-dark me-1 mb-1 tag-filter" data-tag="error-handling">errors</span>
                            <span class="badge bg-light text-dark me-1 mb-1 tag-filter" data-tag="database">database</span>
                            <span class="badge bg-light text-dark me-1 mb-1 tag-filter" data-tag="json">json</span>
                            <span class="badge bg-light text-dark me-1 mb-1 tag-filter" data-tag="async">async</span>
                            <span class="badge bg-light text-dark me-1 mb-1 tag-filter" data-tag="webhook">webhook</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Panel: Snippet Grid -->
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i data-feather="grid" class="me-2"></i>
                        Code Snippets <span id="snippetCount" class="badge bg-primary ms-2">57</span>
                    </h6>
                    <div class="view-controls btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="viewMode" id="gridView" value="grid" checked>
                        <label class="btn btn-outline-secondary" for="gridView">
                            <i data-feather="grid" width="16" height="16"></i>
                        </label>

                        <input type="radio" class="btn-check" name="viewMode" id="listView" value="list">
                        <label class="btn btn-outline-secondary" for="listView">
                            <i data-feather="list" width="16" height="16"></i>
                        </label>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="snippetsContainer" class="snippets-grid p-3">
                        <!-- Snippet cards will be populated here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Snippet Detail Modal -->
    <div class="modal fade" id="snippetModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="snippetModalTitle">
                        <i data-feather="code" class="me-2"></i>
                        Snippet Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- Left: Code -->
                        <div class="col-8">
                            <div class="snippet-code-container">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="snippet-meta">
                                        <span id="snippetLanguage" class="badge bg-primary me-2">PHP</span>
                                        <span id="snippetComplexity" class="badge bg-info me-2">Intermediate</span>
                                        <div id="snippetTags" class="d-inline-block">
                                            <!-- Tags will be populated here -->
                                        </div>
                                    </div>
                                    <div class="code-actions">
                                        <button type="button" class="btn btn-outline-secondary btn-sm me-2" id="copyCode">
                                            <i data-feather="copy" class="me-1"></i>
                                            Copy
                                        </button>
                                        <button type="button" class="btn btn-outline-info btn-sm" id="trySnippet">
                                            <i data-feather="play" class="me-1"></i>
                                            Try It
                                        </button>
                                    </div>
                                </div>
                                <div class="code-editor">
                                    <pre><code id="snippetCode" class="language-php"></code></pre>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Details -->
                        <div class="col-4">
                            <div class="snippet-details">
                                <h6>Description</h6>
                                <p id="snippetDescription" class="text-muted mb-3">
                                    <!-- Description will be populated here -->
                                </p>

                                <h6>Usage Example</h6>
                                <div id="snippetUsage" class="usage-example mb-3">
                                    <!-- Usage example will be populated here -->
                                </div>

                                <h6>Parameters</h6>
                                <div id="snippetParameters" class="parameters-list mb-3">
                                    <!-- Parameters will be populated here -->
                                </div>

                                <h6>Response</h6>
                                <div id="snippetResponse" class="response-example mb-3">
                                    <!-- Response example will be populated here -->
                                </div>

                                <div class="snippet-stats">
                                    <small class="text-muted">
                                        <i data-feather="eye" width="14" class="me-1"></i>
                                        <span id="snippetViews">0</span> views
                                        <br>
                                        <i data-feather="copy" width="14" class="me-1"></i>
                                        <span id="snippetCopies">0</span> copies
                                        <br>
                                        <i data-feather="star" width="14" class="me-1"></i>
                                        <span id="snippetRating">0</span>/5 rating
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-warning" id="editSnippet">
                        <i data-feather="edit" class="me-1"></i>
                        Edit
                    </button>
                    <button type="button" class="btn btn-outline-danger" id="deleteSnippet">
                        <i data-feather="trash-2" class="me-1"></i>
                        Delete
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Snippet Modal -->
    <div class="modal fade" id="addSnippetModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-feather="plus" class="me-2"></i>
                        Add New Snippet
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="snippetForm">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group mb-3">
                                    <label for="snippetName" class="form-label">Name *</label>
                                    <input type="text" id="snippetName" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group mb-3">
                                    <label for="snippetCategory" class="form-label">Category *</label>
                                    <select id="snippetCategory" class="form-select" required>
                                        <option value="">Select Category</option>
                                        <option value="transfers">Transfer Engine</option>
                                        <option value="purchase_orders">Purchase Orders</option>
                                        <option value="inventory">Inventory</option>
                                        <option value="webhooks">Webhooks</option>
                                        <option value="vend_api">Vend API</option>
                                        <option value="utilities">Utilities</option>
                                        <option value="testing">Testing</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group mb-3">
                                    <label for="snippetLang" class="form-label">Language *</label>
                                    <select id="snippetLang" class="form-select" required>
                                        <option value="">Select Language</option>
                                        <option value="php">PHP</option>
                                        <option value="javascript">JavaScript</option>
                                        <option value="curl">cURL</option>
                                        <option value="python">Python</option>
                                        <option value="sql">SQL</option>
                                        <option value="bash">Bash</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group mb-3">
                                    <label for="snippetComplexityForm" class="form-label">Complexity</label>
                                    <select id="snippetComplexityForm" class="form-select">
                                        <option value="beginner">Beginner</option>
                                        <option value="intermediate" selected>Intermediate</option>
                                        <option value="advanced">Advanced</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="snippetDescriptionForm" class="form-label">Description *</label>
                            <textarea id="snippetDescriptionForm" class="form-control" rows="3" required></textarea>
                        </div>

                        <div class="form-group mb-3">
                            <label for="snippetCodeForm" class="form-label">Code *</label>
                            <textarea id="snippetCodeForm" class="form-control code-textarea" rows="10" required></textarea>
                        </div>

                        <div class="form-group mb-3">
                            <label for="snippetTagsForm" class="form-label">Tags</label>
                            <input type="text" id="snippetTagsForm" class="form-control"
                                   placeholder="Enter tags separated by commas">
                            <small class="form-text text-muted">e.g., api, authentication, validation</small>
                        </div>

                        <div class="form-group mb-3">
                            <label for="snippetUsageForm" class="form-label">Usage Example</label>
                            <textarea id="snippetUsageForm" class="form-control" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="saveSnippet">
                        <i data-feather="save" class="me-1"></i>
                        Save Snippet
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Snippet Library Styles -->
<style>
.snippet-library .snippets-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1rem;
}

.snippet-library .snippet-card {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
    background: white;
    transition: all 0.2s ease;
    cursor: pointer;
}

.snippet-library .snippet-card:hover {
    border-color: #0d6efd;
    box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.snippet-library .snippet-card .snippet-header {
    display: flex;
    justify-content: between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}

.snippet-library .snippet-card .snippet-title {
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: #212529;
}

.snippet-library .snippet-card .snippet-preview {
    background: #f8f9fa;
    border-radius: 0.25rem;
    padding: 0.5rem;
    font-family: 'Courier New', monospace;
    font-size: 0.8rem;
    line-height: 1.3;
    max-height: 120px;
    overflow: hidden;
    position: relative;
}

.snippet-library .snippet-card .snippet-preview::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 20px;
    background: linear-gradient(transparent, #f8f9fa);
}

.snippet-library .snippet-card .snippet-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.75rem;
    font-size: 0.8rem;
}

.snippet-library .snippet-card .snippet-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.75rem;
}

.snippet-library .tag-cloud .tag-filter {
    cursor: pointer;
    transition: all 0.2s ease;
}

.snippet-library .tag-cloud .tag-filter:hover,
.snippet-library .tag-cloud .tag-filter.active {
    background-color: #0d6efd !important;
    color: white !important;
}

.snippet-library .snippets-list .snippet-list-item {
    border-bottom: 1px solid #dee2e6;
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.snippet-library .snippets-list .snippet-list-item:last-child {
    border-bottom: none;
}

.snippet-library .code-editor pre {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
    max-height: 400px;
    overflow-y: auto;
}

.snippet-library .code-textarea {
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .snippet-library .snippets-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Snippet Library JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const SnippetLibrary = {
        snippets: [],
        filteredSnippets: [],
        currentSnippet: null,

        init() {
            this.loadSnippets();
            this.bindEvents();
        },

        bindEvents() {
            // Search
            document.getElementById('snippetSearch').addEventListener('input', (e) => {
                this.filterSnippets();
            });

            // Category filters
            document.querySelectorAll('.category-filter').forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    this.filterSnippets();
                });
            });

            // Language filter
            document.getElementById('languageFilter').addEventListener('change', () => {
                this.filterSnippets();
            });

            // Complexity filter
            document.getElementById('complexityFilter').addEventListener('change', () => {
                this.filterSnippets();
            });

            // Tag filters
            document.querySelectorAll('.tag-filter').forEach(tag => {
                tag.addEventListener('click', (e) => {
                    this.toggleTagFilter(e.target);
                });
            });

            // View mode
            document.querySelectorAll('input[name="viewMode"]').forEach(radio => {
                radio.addEventListener('change', (e) => {
                    this.changeViewMode(e.target.value);
                });
            });

            // Add snippet
            document.getElementById('addSnippet').addEventListener('click', () => {
                this.showAddSnippetModal();
            });

            // Save snippet
            document.getElementById('saveSnippet').addEventListener('click', () => {
                this.saveSnippet();
            });

            // Clear search
            document.getElementById('clearSearch').addEventListener('click', () => {
                document.getElementById('snippetSearch').value = '';
                this.filterSnippets();
            });
        },

        async loadSnippets() {
            // In a real implementation, this would load from API
            this.snippets = this.getMockSnippets();
            this.filteredSnippets = [...this.snippets];
            this.renderSnippets();
        },

        getMockSnippets() {
            return [
                {
                    id: 1,
                    name: 'Create Transfer Request',
                    category: 'transfers',
                    language: 'php',
                    complexity: 'intermediate',
                    description: 'Create a new stock transfer request between outlets',
                    code: `<` + `?php
// Create transfer request
$transfer = [
    'from_outlet_id' => $fromOutlet,
    'to_outlet_id' => $toOutlet,
    'items' => [
        [
            'product_id' => $productId,
            'quantity' => $quantity,
            'cost' => $cost
        ]
    ],
    'notes' => $notes
];

$response = $transferEngine->createTransfer($transfer);

if ($response['success']) {
    echo "Transfer created: " . $response['transfer_id'];
} else {
    echo "Error: " . $response['error'];
}`,
                    tags: ['transfer', 'api', 'create'],
                    usage: 'Use this to programmatically create transfer requests',
                    views: 156,
                    copies: 23,
                    rating: 4.5
                },
                {
                    id: 2,
                    name: 'Vend API Authentication',
                    category: 'vend_api',
                    language: 'php',
                    complexity: 'beginner',
                    description: 'Authenticate with Vend API using OAuth2',
                    code: `<` + `?php
$vendAuth = new VendAuth([
    'client_id' => getenv('VEND_CLIENT_ID'),
    'client_secret' => getenv('VEND_CLIENT_SECRET'),
    'redirect_uri' => getenv('VEND_REDIRECT_URI')
]);

// Get authorization URL
$authUrl = $vendAuth->getAuthorizationUrl();

// Exchange code for token
$token = $vendAuth->getAccessToken($_GET['code']);

// Make authenticated request
$products = $vendAuth->request('GET', '/api/2.0/products');`,
                    tags: ['vend', 'api', 'authentication', 'oauth2'],
                    usage: 'Essential for all Vend API integrations',
                    views: 89,
                    copies: 34,
                    rating: 4.8
                },
                {
                    id: 3,
                    name: 'Webhook Event Handler',
                    category: 'webhooks',
                    language: 'php',
                    complexity: 'advanced',
                    description: 'Handle incoming webhook events with validation',
                    code: `<` + `?php
class WebhookHandler
{
    public function handle($payload, $signature)
    {
        // Validate signature
        if (!$this->validateSignature($payload, $signature)) {
            throw new InvalidSignatureException();
        }

        $event = json_decode($payload, true);

        switch ($event['type']) {
            case 'product.updated':
                $this->handleProductUpdate($event['data']);
                break;
            case 'sale.completed':
                $this->handleSaleCompleted($event['data']);
                break;
            default:
                error_log("Unknown webhook event: " . $event['type']);
        }
    }

    private function validateSignature($payload, $signature)
    {
        $expected = hash_hmac('sha256', $payload, getenv('WEBHOOK_SECRET'));
        return hash_equals($expected, $signature);
    }
}`,
                    tags: ['webhook', 'security', 'validation', 'events'],
                    usage: 'Base class for handling secure webhook events',
                    views: 203,
                    copies: 45,
                    rating: 4.7
                },
                {
                    id: 4,
                    name: 'Inventory Sync Query',
                    category: 'inventory',
                    language: 'sql',
                    complexity: 'intermediate',
                    description: 'Query to sync inventory levels between systems',
                    code: `SELECT
    p.id as product_id,
    p.sku,
    p.name,
    pi.outlet_id,
    pi.current_inventory as current_stock,
    pi.available_inventory as available_stock,
    COALESCE(pending_transfers.pending_out, 0) as pending_out,
    COALESCE(pending_transfers.pending_in, 0) as pending_in
FROM products p
JOIN product_inventory pi ON p.id = pi.product_id
LEFT JOIN (
    SELECT
        product_id,
        from_outlet_id,
        SUM(CASE WHEN from_outlet_id = pi.outlet_id THEN quantity ELSE 0 END) as pending_out,
        SUM(CASE WHEN to_outlet_id = pi.outlet_id THEN quantity ELSE 0 END) as pending_in
    FROM transfer_items ti
    JOIN transfers t ON ti.transfer_id = t.id
    WHERE t.status IN ('pending', 'in_transit')
    GROUP BY product_id, from_outlet_id
) pending_transfers ON p.id = pending_transfers.product_id
WHERE pi.outlet_id = ?
ORDER BY p.name;`,
                    tags: ['inventory', 'sql', 'sync', 'transfers'],
                    usage: 'Get comprehensive inventory status including pending transfers',
                    views: 178,
                    copies: 31,
                    rating: 4.3
                },
                {
                    id: 5,
                    name: 'cURL API Request',
                    category: 'utilities',
                    language: 'curl',
                    complexity: 'beginner',
                    description: 'Basic cURL request template for API testing',
                    code: `# GET request with authentication
curl -X GET "https://api.example.com/endpoint" \\
  -H "Authorization: Bearer YOUR_TOKEN" \\
  -H "Content-Type: application/json" \\
  -H "Accept: application/json"

# POST request with data
curl -X POST "https://api.example.com/endpoint" \\
  -H "Authorization: Bearer YOUR_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{
    "key": "value",
    "array": [1, 2, 3]
  }'

# Upload file
curl -X POST "https://api.example.com/upload" \\
  -H "Authorization: Bearer YOUR_TOKEN" \\
  -F "file=@/path/to/file.txt" \\
  -F "description=File description"`,
                    tags: ['curl', 'api', 'testing', 'http'],
                    usage: 'Template for testing API endpoints manually',
                    views: 267,
                    copies: 89,
                    rating: 4.9
                },
                {
                    id: 6,
                    name: 'Async JavaScript Request',
                    category: 'utilities',
                    language: 'javascript',
                    complexity: 'intermediate',
                    description: 'Modern async/await API request with error handling',
                    code: `async function apiRequest(endpoint, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };

    const config = { ...defaultOptions, ...options };

    try {
        const response = await fetch(endpoint, config);

        if (!response.ok) {
            throw new Error(\`HTTP error! status: \${response.status}\`);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'API request failed');
        }

        return data.result;

    } catch (error) {
        console.error('API request failed:', error);
        throw error;
    }
}

// Usage
try {
    const result = await apiRequest('/api/transfers', {
        method: 'POST',
        body: JSON.stringify({ from_outlet: 1, to_outlet: 2 })
    });
    console.log('Success:', result);
} catch (error) {
    console.error('Failed:', error.message);
}`,
                    tags: ['javascript', 'async', 'api', 'fetch', 'error-handling'],
                    usage: 'Reusable function for making API requests from frontend',
                    views: 145,
                    copies: 56,
                    rating: 4.6
                }
            ];
        },

        filterSnippets() {
            const searchTerm = document.getElementById('snippetSearch').value.toLowerCase();
            const selectedLanguage = document.getElementById('languageFilter').value;
            const selectedComplexity = document.getElementById('complexityFilter').value;
            const selectedCategories = Array.from(document.querySelectorAll('.category-filter:checked'))
                .map(cb => cb.value);
            const selectedTags = Array.from(document.querySelectorAll('.tag-filter.active'))
                .map(tag => tag.dataset.tag);

            this.filteredSnippets = this.snippets.filter(snippet => {
                // Search term
                if (searchTerm && !snippet.name.toLowerCase().includes(searchTerm) &&
                    !snippet.description.toLowerCase().includes(searchTerm) &&
                    !snippet.tags.some(tag => tag.toLowerCase().includes(searchTerm))) {
                    return false;
                }

                // Language
                if (selectedLanguage && snippet.language !== selectedLanguage) {
                    return false;
                }

                // Complexity
                if (selectedComplexity && snippet.complexity !== selectedComplexity) {
                    return false;
                }

                // Categories
                if (selectedCategories.length > 0 && !selectedCategories.includes(snippet.category)) {
                    return false;
                }

                // Tags
                if (selectedTags.length > 0 && !selectedTags.some(tag => snippet.tags.includes(tag))) {
                    return false;
                }

                return true;
            });

            this.renderSnippets();
        },

        renderSnippets() {
            const container = document.getElementById('snippetsContainer');
            const viewMode = document.querySelector('input[name="viewMode"]:checked').value;

            document.getElementById('snippetCount').textContent = this.filteredSnippets.length;

            if (viewMode === 'grid') {
                this.renderGridView(container);
            } else {
                this.renderListView(container);
            }
        },

        renderGridView(container) {
            container.className = 'snippets-grid p-3';
            container.innerHTML = this.filteredSnippets.map(snippet => this.createSnippetCard(snippet)).join('');

            // Bind card click events
            container.querySelectorAll('.snippet-card').forEach(card => {
                card.addEventListener('click', (e) => {
                    if (!e.target.closest('.snippet-actions')) {
                        const snippetId = parseInt(card.dataset.snippetId);
                        this.showSnippetDetail(snippetId);
                    }
                });
            });
        },

        renderListView(container) {
            container.className = 'snippets-list';
            container.innerHTML = this.filteredSnippets.map(snippet => this.createSnippetListItem(snippet)).join('');
        },

        createSnippetCard(snippet) {
            const complexityColor = {
                'beginner': 'success',
                'intermediate': 'warning',
                'advanced': 'danger'
            }[snippet.complexity];

            return `
                <div class="snippet-card" data-snippet-id="${snippet.id}">
                    <div class="snippet-header">
                        <div class="flex-grow-1">
                            <div class="snippet-title">${snippet.name}</div>
                            <small class="text-muted">${snippet.description}</small>
                        </div>
                        <div class="snippet-lang">
                            <span class="badge bg-primary">${snippet.language.toUpperCase()}</span>
                        </div>
                    </div>

                    <div class="snippet-preview">
                        <code>${this.escapeHtml(snippet.code.substring(0, 200))}${snippet.code.length > 200 ? '...' : ''}</code>
                    </div>

                    <div class="snippet-meta">
                        <div>
                            <span class="badge bg-${complexityColor} me-1">${snippet.complexity}</span>
                            ${snippet.tags.slice(0, 2).map(tag => `<span class="badge bg-light text-dark me-1">${tag}</span>`).join('')}
                        </div>
                        <small class="text-muted">
                            <i data-feather="copy" width="12"></i> ${snippet.copies}
                            <i data-feather="star" width="12" class="ms-2"></i> ${snippet.rating}
                        </small>
                    </div>

                    <div class="snippet-actions">
                        <button class="btn btn-outline-secondary btn-sm" onclick="SnippetLibrary.copySnippet(${snippet.id}); event.stopPropagation();">
                            <i data-feather="copy" width="14"></i>
                        </button>
                        <button class="btn btn-outline-info btn-sm" onclick="SnippetLibrary.trySnippet(${snippet.id}); event.stopPropagation();">
                            <i data-feather="play" width="14"></i>
                        </button>
                    </div>
                </div>
            `;
        },

        createSnippetListItem(snippet) {
            return `
                <div class="snippet-list-item" data-snippet-id="${snippet.id}">
                    <div class="snippet-info">
                        <h6 class="mb-1">${snippet.name}</h6>
                        <p class="text-muted mb-1">${snippet.description}</p>
                        <div>
                            <span class="badge bg-primary me-1">${snippet.language.toUpperCase()}</span>
                            <span class="badge bg-secondary me-1">${snippet.category.replace('_', ' ')}</span>
                            ${snippet.tags.slice(0, 3).map(tag => `<span class="badge bg-light text-dark me-1">${tag}</span>`).join('')}
                        </div>
                    </div>
                    <div class="snippet-stats text-end">
                        <div><small class="text-muted">${snippet.copies} copies</small></div>
                        <div><small class="text-muted">${snippet.rating}/5 rating</small></div>
                    </div>
                </div>
            `;
        },

        showSnippetDetail(snippetId) {
            const snippet = this.snippets.find(s => s.id === snippetId);
            if (!snippet) return;

            this.currentSnippet = snippet;

            // Populate modal
            document.getElementById('snippetModalTitle').innerHTML = `
                <i data-feather="code" class="me-2"></i>
                ${snippet.name}
            `;
            document.getElementById('snippetLanguage').textContent = snippet.language.toUpperCase();
            document.getElementById('snippetComplexity').textContent = snippet.complexity;
            document.getElementById('snippetTags').innerHTML = snippet.tags.map(tag =>
                `<span class="badge bg-light text-dark me-1">${tag}</span>`
            ).join('');
            document.getElementById('snippetCode').textContent = snippet.code;
            document.getElementById('snippetDescription').textContent = snippet.description;
            document.getElementById('snippetUsage').textContent = snippet.usage || 'No usage example provided';
            document.getElementById('snippetViews').textContent = snippet.views;
            document.getElementById('snippetCopies').textContent = snippet.copies;
            document.getElementById('snippetRating').textContent = snippet.rating;

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('snippetModal'));
            modal.show();

            // Update syntax highlighting
            if (window.Prism) {
                window.Prism.highlightAll();
            }
            feather.replace();
        },

        copySnippet(snippetId) {
            const snippet = this.snippets.find(s => s.id === snippetId);
            if (!snippet) return;

            navigator.clipboard.writeText(snippet.code).then(() => {
                this.showSuccess('Code copied to clipboard');
                snippet.copies++;
                this.renderSnippets();
            }).catch(err => {
                this.showError('Failed to copy code');
            });
        },

        trySnippet(snippetId) {
            const snippet = this.snippets.find(s => s.id === snippetId);
            if (!snippet) return;

            // Navigate to appropriate tester based on category
            const testerMap = {
                'transfers': 'webhook',
                'purchase_orders': 'webhook',
                'inventory': 'vend',
                'webhooks': 'webhook',
                'vend_api': 'vend',
                'utilities': 'webhook',
                'testing': 'suite'
            };

            const tester = testerMap[snippet.category] || 'webhook';
            window.location.href = `?page=api-lab&tab=${tester}&snippet=${snippetId}`;
        },

        changeViewMode(mode) {
            this.renderSnippets();
        },

        toggleTagFilter(tagElement) {
            tagElement.classList.toggle('active');
            this.filterSnippets();
        },

        showAddSnippetModal() {
            const modal = new bootstrap.Modal(document.getElementById('addSnippetModal'));
            modal.show();
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        showSuccess(message) {
            // Implementation for success notifications
            console.log('Success:', message);
        },

        showError(message) {
            // Implementation for error notifications
            console.error('Error:', message);
        }
    };

    // Initialize
    SnippetLibrary.init();

    // Make available globally
    window.SnippetLibrary = SnippetLibrary;
});
</script>