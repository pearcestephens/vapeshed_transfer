<?php
/**
 * Input Sanitizer Service
 *
 * Comprehensive input validation and sanitization for:
 * - Form inputs (text, email, URLs, numbers)
 * - Arrays and nested data
 * - SQL injection prevention
 * - XSS prevention
 * - File uploads
 * - JSON payloads
 * - Custom validation rules
 *
 * @category   Service
 * @package    VapeshedTransfer
 * @subpackage Security
 * @version    1.0.0
 */

namespace App\Services;

/**
 * Input Sanitizer Service
 */
class InputSanitizer
{
    /**
     * Validation errors
     *
     * @var array
     */
    private $errors = [];

    /**
     * Custom validation rules
     *
     * @var array
     */
    private $customRules = [];

    /**
     * Sanitize string input
     *
     * @param mixed $input Input value
     * @param array $options Sanitization options
     * @return string Sanitized string
     */
    public function sanitizeString($input, array $options = []): string
    {
        if (!is_string($input)) {
            $input = (string)$input;
        }

        $defaults = [
            'trim' => true,
            'strip_tags' => true,
            'decode_entities' => false,
            'allow_html' => false,
            'max_length' => null,
            'normalize_whitespace' => false
        ];

        $options = array_merge($defaults, $options);

        // Trim whitespace
        if ($options['trim']) {
            $input = trim($input);
        }

        // Strip HTML tags
        if ($options['strip_tags'] && !$options['allow_html']) {
            $input = strip_tags($input);
        }

        // Decode HTML entities
        if ($options['decode_entities']) {
            $input = html_entity_decode($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Normalize whitespace
        if ($options['normalize_whitespace']) {
            $input = preg_replace('/\s+/', ' ', $input);
        }

        // Truncate to max length
        if ($options['max_length'] && strlen($input) > $options['max_length']) {
            $input = substr($input, 0, $options['max_length']);
        }

        return $input;
    }

    /**
     * Sanitize integer input
     *
     * @param mixed $input Input value
     * @param array $options Validation options
     * @return int|null Sanitized integer or null if invalid
     */
    public function sanitizeInt($input, array $options = []): ?int
    {
        $defaults = [
            'min' => null,
            'max' => null,
            'default' => null
        ];

        $options = array_merge($defaults, $options);

        $value = filter_var($input, FILTER_VALIDATE_INT);

        if ($value === false) {
            return $options['default'];
        }

        // Check min/max bounds
        if ($options['min'] !== null && $value < $options['min']) {
            $this->addError('Value must be at least ' . $options['min']);
            return $options['default'];
        }

        if ($options['max'] !== null && $value > $options['max']) {
            $this->addError('Value must not exceed ' . $options['max']);
            return $options['default'];
        }

        return $value;
    }

    /**
     * Sanitize float input
     *
     * @param mixed $input Input value
     * @param array $options Validation options
     * @return float|null Sanitized float or null if invalid
     */
    public function sanitizeFloat($input, array $options = []): ?float
    {
        $defaults = [
            'min' => null,
            'max' => null,
            'decimals' => null,
            'default' => null
        ];

        $options = array_merge($defaults, $options);

        $value = filter_var($input, FILTER_VALIDATE_FLOAT);

        if ($value === false) {
            return $options['default'];
        }

        // Check min/max bounds
        if ($options['min'] !== null && $value < $options['min']) {
            $this->addError('Value must be at least ' . $options['min']);
            return $options['default'];
        }

        if ($options['max'] !== null && $value > $options['max']) {
            $this->addError('Value must not exceed ' . $options['max']);
            return $options['default'];
        }

        // Round to specified decimals
        if ($options['decimals'] !== null) {
            $value = round($value, $options['decimals']);
        }

        return $value;
    }

    /**
     * Sanitize email address
     *
     * @param string $input Email address
     * @return string|null Sanitized email or null if invalid
     */
    public function sanitizeEmail(string $input): ?string
    {
        $email = filter_var($input, FILTER_SANITIZE_EMAIL);
        
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return strtolower($email);
        }

        $this->addError('Invalid email address');
        return null;
    }

    /**
     * Sanitize URL
     *
     * @param string $input URL
     * @param array $options Validation options
     * @return string|null Sanitized URL or null if invalid
     */
    public function sanitizeUrl(string $input, array $options = []): ?string
    {
        $defaults = [
            'require_protocol' => true,
            'allowed_protocols' => ['http', 'https'],
            'allow_relative' => false
        ];

        $options = array_merge($defaults, $options);

        $url = filter_var($input, FILTER_SANITIZE_URL);

        // Check if valid URL
        if (!filter_var($url, FILTER_VALIDATE_URL) && !$options['allow_relative']) {
            $this->addError('Invalid URL');
            return null;
        }

        // Check protocol
        if ($options['require_protocol']) {
            $protocol = parse_url($url, PHP_URL_SCHEME);
            
            if (!$protocol || !in_array($protocol, $options['allowed_protocols'])) {
                $this->addError('Invalid URL protocol');
                return null;
            }
        }

        return $url;
    }

    /**
     * Sanitize boolean input
     *
     * @param mixed $input Input value
     * @return bool Boolean value
     */
    public function sanitizeBool($input): bool
    {
        return filter_var($input, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Sanitize array recursively
     *
     * @param array $input Input array
     * @param string $method Sanitization method
     * @param array $options Options for sanitization method
     * @return array Sanitized array
     */
    public function sanitizeArray(array $input, string $method = 'sanitizeString', array $options = []): array
    {
        $result = [];

        foreach ($input as $key => $value) {
            $key = $this->sanitizeString($key);

            if (is_array($value)) {
                $result[$key] = $this->sanitizeArray($value, $method, $options);
            } else {
                $result[$key] = $this->$method($value, $options);
            }
        }

        return $result;
    }

    /**
     * Sanitize filename
     *
     * @param string $filename Filename
     * @param array $options Validation options
     * @return string Sanitized filename
     */
    public function sanitizeFilename(string $filename, array $options = []): string
    {
        $defaults = [
            'max_length' => 255,
            'lowercase' => false,
            'allowed_extensions' => null
        ];

        $options = array_merge($defaults, $options);

        // Get file extension
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // Check allowed extensions
        if ($options['allowed_extensions'] && !in_array($ext, $options['allowed_extensions'])) {
            $this->addError('File type not allowed');
            return '';
        }

        // Remove special characters
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);

        // Convert to lowercase if requested
        if ($options['lowercase']) {
            $name = strtolower($name);
        }

        // Truncate to max length
        if (strlen($name) > $options['max_length'] - strlen($ext) - 1) {
            $name = substr($name, 0, $options['max_length'] - strlen($ext) - 1);
        }

        return $ext ? $name . '.' . $ext : $name;
    }

    /**
     * Sanitize JSON input
     *
     * @param string $input JSON string
     * @param int $depth Maximum nesting depth
     * @return array|null Decoded array or null if invalid
     */
    public function sanitizeJson(string $input, int $depth = 512): ?array
    {
        $data = json_decode($input, true, $depth);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError('Invalid JSON: ' . json_last_error_msg());
            return null;
        }

        return $data;
    }

    /**
     * Prevent SQL injection
     *
     * @param string $input Input value
     * @return string Escaped string
     */
    public function escapeSql(string $input): string
    {
        return addslashes($input);
    }

    /**
     * Prevent XSS attacks
     *
     * @param string $input Input value
     * @param bool $doubleEncode Whether to double encode
     * @return string Escaped string
     */
    public function escapeHtml(string $input, bool $doubleEncode = true): string
    {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8', $doubleEncode);
    }

    /**
     * Validate input against rules
     *
     * @param array $data Input data
     * @param array $rules Validation rules
     * @return bool True if valid
     */
    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            foreach ($fieldRules as $rule) {
                // Parse rule parameters
                if (strpos($rule, ':') !== false) {
                    list($ruleName, $params) = explode(':', $rule, 2);
                    $params = explode(',', $params);
                } else {
                    $ruleName = $rule;
                    $params = [];
                }

                // Apply rule
                if (!$this->applyRule($field, $value, $ruleName, $params)) {
                    break; // Stop validating this field on first error
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Apply validation rule
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Rule name
     * @param array $params Rule parameters
     * @return bool True if valid
     */
    private function applyRule(string $field, $value, string $rule, array $params): bool
    {
        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError("{$field} is required");
                    return false;
                }
                break;

            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError("{$field} must be a valid email");
                    return false;
                }
                break;

            case 'url':
                if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError("{$field} must be a valid URL");
                    return false;
                }
                break;

            case 'numeric':
                if ($value && !is_numeric($value)) {
                    $this->addError("{$field} must be numeric");
                    return false;
                }
                break;

            case 'integer':
                if ($value && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->addError("{$field} must be an integer");
                    return false;
                }
                break;

            case 'min':
                if (is_numeric($value) && $value < $params[0]) {
                    $this->addError("{$field} must be at least {$params[0]}");
                    return false;
                } elseif (is_string($value) && strlen($value) < $params[0]) {
                    $this->addError("{$field} must be at least {$params[0]} characters");
                    return false;
                }
                break;

            case 'max':
                if (is_numeric($value) && $value > $params[0]) {
                    $this->addError("{$field} must not exceed {$params[0]}");
                    return false;
                } elseif (is_string($value) && strlen($value) > $params[0]) {
                    $this->addError("{$field} must not exceed {$params[0]} characters");
                    return false;
                }
                break;

            case 'between':
                if (is_numeric($value) && ($value < $params[0] || $value > $params[1])) {
                    $this->addError("{$field} must be between {$params[0]} and {$params[1]}");
                    return false;
                }
                break;

            case 'in':
                if ($value && !in_array($value, $params)) {
                    $this->addError("{$field} must be one of: " . implode(', ', $params));
                    return false;
                }
                break;

            case 'regex':
                if ($value && !preg_match($params[0], $value)) {
                    $this->addError("{$field} format is invalid");
                    return false;
                }
                break;

            case 'alpha':
                if ($value && !ctype_alpha($value)) {
                    $this->addError("{$field} must contain only letters");
                    return false;
                }
                break;

            case 'alphanumeric':
                if ($value && !ctype_alnum($value)) {
                    $this->addError("{$field} must contain only letters and numbers");
                    return false;
                }
                break;

            case 'date':
                if ($value && !strtotime($value)) {
                    $this->addError("{$field} must be a valid date");
                    return false;
                }
                break;

            default:
                // Check for custom rules
                if (isset($this->customRules[$rule])) {
                    if (!call_user_func($this->customRules[$rule], $value, $params)) {
                        $this->addError("{$field} validation failed for rule: {$rule}");
                        return false;
                    }
                }
                break;
        }

        return true;
    }

    /**
     * Add custom validation rule
     *
     * @param string $name Rule name
     * @param callable $callback Validation callback
     * @return self
     */
    public function addRule(string $name, callable $callback): self
    {
        $this->customRules[$name] = $callback;
        return $this;
    }

    /**
     * Add validation error
     *
     * @param string $message Error message
     * @return void
     */
    private function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    /**
     * Get validation errors
     *
     * @return array Errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if there are errors
     *
     * @return bool True if errors exist
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Clear errors
     *
     * @return self
     */
    public function clearErrors(): self
    {
        $this->errors = [];
        return $this;
    }

    /**
     * Sanitize file upload
     *
     * @param array $file $_FILES entry
     * @param array $options Validation options
     * @return array|null File info or null if invalid
     */
    public function sanitizeUpload(array $file, array $options = []): ?array
    {
        $defaults = [
            'max_size' => 10485760, // 10MB
            'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf']
        ];

        $options = array_merge($defaults, $options);

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->addError('File upload error: ' . $this->getUploadError($file['error']));
            return null;
        }

        // Check file size
        if ($file['size'] > $options['max_size']) {
            $this->addError('File size exceeds maximum allowed');
            return null;
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $options['allowed_types'])) {
            $this->addError('File type not allowed');
            return null;
        }

        // Sanitize filename
        $filename = $this->sanitizeFilename($file['name'], [
            'allowed_extensions' => $options['allowed_extensions']
        ]);

        if (!$filename) {
            return null;
        }

        return [
            'name' => $filename,
            'tmp_name' => $file['tmp_name'],
            'size' => $file['size'],
            'mime_type' => $mimeType
        ];
    }

    /**
     * Get upload error message
     *
     * @param int $code Error code
     * @return string Error message
     */
    private function getUploadError(int $code): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];

        return $errors[$code] ?? 'Unknown upload error';
    }

    /**
     * Batch sanitize multiple fields
     *
     * @param array $data Input data
     * @param array $rules Sanitization rules per field
     * @return array Sanitized data
     */
    public function sanitizeBatch(array $data, array $rules): array
    {
        $result = [];

        foreach ($rules as $field => $rule) {
            if (!isset($data[$field])) {
                continue;
            }

            $method = $rule['method'] ?? 'sanitizeString';
            $options = $rule['options'] ?? [];

            $result[$field] = $this->$method($data[$field], $options);
        }

        return $result;
    }
}
