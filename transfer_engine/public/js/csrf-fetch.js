/**
 * CSRF-Protected Fetch Wrapper
 * 
 * Automatically injects CSRF token into all mutating requests (POST, PUT, PATCH, DELETE).
 * Provides a drop-in replacement for the native fetch() API with built-in CSRF protection.
 * 
 * @module csrf-fetch
 * @author Autonomous Remediation Bot
 * @since 2.0.0
 * 
 * Usage:
 *   import { csrfFetch } from '/assets/js/csrf-fetch.js';
 *   
 *   // Automatically adds CSRF token to POST requests
 *   const response = await csrfFetch('/api/endpoint', {
 *     method: 'POST',
 *     body: JSON.stringify({ data: 'value' })
 *   });
 */

/**
 * Get CSRF token from meta tag
 * @returns {string|null} CSRF token or null if not found
 */
export function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : null;
}

/**
 * Check if HTTP method requires CSRF protection
 * @param {string} method - HTTP method
 * @returns {boolean} True if method is mutating
 */
function isMutatingMethod(method) {
    const upperMethod = (method || 'GET').toUpperCase();
    return ['POST', 'PUT', 'PATCH', 'DELETE'].includes(upperMethod);
}

/**
 * Fetch wrapper with automatic CSRF token injection
 * 
 * @param {string|Request} input - URL or Request object
 * @param {RequestInit} [init={}] - Fetch options
 * @returns {Promise<Response>} Fetch response
 * 
 * @example
 * // GET request (no CSRF needed)
 * const users = await csrfFetch('/api/users').then(r => r.json());
 * 
 * @example
 * // POST request (CSRF auto-injected)
 * const result = await csrfFetch('/api/users', {
 *   method: 'POST',
 *   headers: { 'Content-Type': 'application/json' },
 *   body: JSON.stringify({ name: 'John' })
 * });
 * 
 * @example
 * // DELETE request (CSRF auto-injected)
 * await csrfFetch('/api/users/123', { method: 'DELETE' });
 * 
 * @example
 * // Override CSRF token (rare)
 * await csrfFetch('/api/endpoint', {
 *   method: 'POST',
 *   headers: { 'X-CSRF-Token': 'custom-token' }
 * });
 */
export async function csrfFetch(input, init = {}) {
    // Determine the HTTP method
    let method = 'GET';
    
    if (input instanceof Request) {
        method = input.method;
    } else if (init && init.method) {
        method = init.method;
    }
    
    // Only inject CSRF for mutating methods
    if (isMutatingMethod(method)) {
        const token = getCsrfToken();
        
        if (token) {
            // Create Headers object if needed
            const headers = new Headers(init.headers || {});
            
            // Only add token if not already present
            if (!headers.has('X-CSRF-Token') && !headers.has('X-CSRF-TOKEN')) {
                headers.set('X-CSRF-Token', token);
            }
            
            // Merge headers back into init
            init = { ...init, headers };
        } else {
            // Warn if no token available for mutating request
            console.warn('[CSRF] No CSRF token found in meta tag for', method, 'request to', input);
        }
    }
    
    // Call native fetch
    return fetch(input, init);
}

/**
 * Helper: POST with JSON body
 * @param {string} url - Target URL
 * @param {object} data - Data to send as JSON
 * @param {RequestInit} [options={}] - Additional fetch options
 * @returns {Promise<Response>}
 * 
 * @example
 * const response = await csrfPost('/api/users', { name: 'John', email: 'john@example.com' });
 * const result = await response.json();
 */
export async function csrfPost(url, data, options = {}) {
    return csrfFetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            ...options.headers,
        },
        body: JSON.stringify(data),
        ...options,
    });
}

/**
 * Helper: PUT with JSON body
 * @param {string} url - Target URL
 * @param {object} data - Data to send as JSON
 * @param {RequestInit} [options={}] - Additional fetch options
 * @returns {Promise<Response>}
 */
export async function csrfPut(url, data, options = {}) {
    return csrfFetch(url, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            ...options.headers,
        },
        body: JSON.stringify(data),
        ...options,
    });
}

/**
 * Helper: PATCH with JSON body
 * @param {string} url - Target URL
 * @param {object} data - Data to send as JSON
 * @param {RequestInit} [options={}] - Additional fetch options
 * @returns {Promise<Response>}
 */
export async function csrfPatch(url, data, options = {}) {
    return csrfFetch(url, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            ...options.headers,
        },
        body: JSON.stringify(data),
        ...options,
    });
}

/**
 * Helper: DELETE request
 * @param {string} url - Target URL
 * @param {RequestInit} [options={}] - Additional fetch options
 * @returns {Promise<Response>}
 */
export async function csrfDelete(url, options = {}) {
    return csrfFetch(url, {
        method: 'DELETE',
        ...options,
    });
}

/**
 * Helper: Submit FormData with CSRF protection
 * @param {string} url - Target URL
 * @param {FormData} formData - Form data to submit
 * @param {RequestInit} [options={}] - Additional fetch options
 * @returns {Promise<Response>}
 * 
 * @example
 * const form = document.querySelector('form');
 * const formData = new FormData(form);
 * const response = await csrfSubmitForm('/api/upload', formData);
 */
export async function csrfSubmitForm(url, formData, options = {}) {
    const token = getCsrfToken();
    
    // Add CSRF token to form data if available
    if (token && !formData.has('csrf_token') && !formData.has('_csrf')) {
        formData.append('csrf_token', token);
    }
    
    return csrfFetch(url, {
        method: 'POST',
        body: formData,
        ...options,
    });
}

/**
 * Initialize CSRF protection on all forms
 * Adds hidden CSRF token input to all forms without one
 * 
 * @param {string} [selector='form'] - CSS selector for forms
 * 
 * @example
 * // Initialize on page load
 * document.addEventListener('DOMContentLoaded', () => {
 *   initCsrfForms();
 * });
 */
export function initCsrfForms(selector = 'form') {
    const token = getCsrfToken();
    
    if (!token) {
        console.warn('[CSRF] No token found, skipping form initialization');
        return;
    }
    
    document.querySelectorAll(selector).forEach(form => {
        // Skip forms that already have CSRF token
        if (form.querySelector('input[name="csrf_token"]') || 
            form.querySelector('input[name="_csrf"]')) {
            return;
        }
        
        // Skip GET forms
        const method = (form.method || 'GET').toUpperCase();
        if (method === 'GET') {
            return;
        }
        
        // Add hidden CSRF token input
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'csrf_token';
        input.value = token;
        form.appendChild(input);
    });
}

/**
 * Validate that CSRF protection is properly configured
 * Checks for meta tag presence and logs warnings
 * 
 * @returns {boolean} True if CSRF is properly configured
 */
export function validateCsrfSetup() {
    const token = getCsrfToken();
    
    if (!token) {
        console.error('[CSRF] ❌ Missing <meta name="csrf-token"> in page <head>');
        console.error('[CSRF] Add to layout: <meta name="csrf-token" content="<?= $_SESSION[\'csrf_token\'] ?? \'\' ?>">');
        return false;
    }
    
    if (token.length < 16) {
        console.warn('[CSRF] ⚠️ CSRF token seems too short (< 16 chars). Ensure server generates secure tokens.');
        return false;
    }
    
    console.log('[CSRF] ✅ CSRF protection initialized. Token length:', token.length);
    return true;
}

// Auto-initialize on module load (if DOM ready)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        validateCsrfSetup();
    });
} else {
    validateCsrfSetup();
}

// Export default as csrfFetch for convenience
export default csrfFetch;
