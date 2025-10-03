/**
 * Vapeshed Transfer Engine - Main JavaScript
 * Self-Contained Utilities (CSP Compliant)
 */

// Global namespace for Vapeshed Transfer functionality
var VapeshedTransfer = {
    baseUrl: '',
    
    // Initialize the application
    init: function() {
        this.setupCSRF();
        this.setupNavigation();
        this.setupForms();
        console.log('VapeshedTransfer initialized with self-contained icons');
    },
    
    // Utility functions
    utils: {        
        // URL helper
        url: function(path) {
            return VapeshedTransfer.baseUrl.replace(/\/$/, '') + '/' + path.replace(/^\//, '');
        },
        
        // Loading state management
        showLoading: function(element) {
            $(element).prop('disabled', true).addClass('loading');
        },
        
        hideLoading: function(element) {
            $(element).prop('disabled', false).removeClass('loading');
        },
        
        // Flash message handler
        showMessage: function(message, type) {
            type = type || 'info';
            const alertClass = 'alert-' + type;
            const alert = $('<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
                message +
                '<button type="button" class="close" data-dismiss="alert">' +
                '<span aria-hidden="true">&times;</span>' +
                '</button>' +
                '</div>');
            
            $('.container').first().prepend(alert);
            setTimeout(() => alert.fadeOut(), 5000);
        }
    },
    
    // CSRF token management
    setupCSRF: function() {
        const token = $('meta[name="csrf-token"]').attr('content');
        if (token) {
            // Set up AJAX defaults for jQuery
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                beforeSend: function(xhr) {
                    // Ensure CSRF token is included in all requests
                    xhr.setRequestHeader('X-CSRF-TOKEN', token);
                }
            });
            
            // Also add to all forms as hidden input if not already present
            $('form').each(function() {
                const form = $(this);
                if (form.find('input[name="_token"]').length === 0) {
                    form.append('<input type="hidden" name="_token" value="' + token + '">');
                }
            });
        }
    },
    
    // Navigation enhancements
    setupNavigation: function() {
        // Add active state to current nav items
        const currentPath = window.location.pathname;
        $('.nav-link').each(function() {
            if ($(this).attr('href') === currentPath) {
                $(this).addClass('active');
            }
        });
    },
    
    // Form enhancements
    setupForms: function() {
        // Auto-submit forms with data-auto-submit after change
        $('form[data-auto-submit]').on('change', 'select, input', function() {
            $(this).closest('form').submit();
        });
        
        // Confirmation dialogs
        $('[data-confirm]').on('click', function(e) {
            if (!confirm($(this).data('confirm'))) {
                e.preventDefault();
                return false;
            }
        });
    }
};

// Main initialization when DOM is ready
$(document).ready(function() {
    // Ensure jQuery easing names exist (polyfill minimal set)
    if ($.easing) {
        $.easing.easeOutCubic = $.easing.easeOutCubic || function (x, t, b, c, d) {
            t /= d; t--; return c*(t*t*t + 1) + b;
        };
        $.easing.easeInOutQuad = $.easing.easeInOutQuad || function (x, t, b, c, d) {
            t /= d/2; if (t < 1) return c/2*t*t + b; t--; return -c/2 * (t*(t-2) - 1) + b;
        };
        $.easing.swing = $.easing.swing || function (x, t, b, c, d) {
            return $.easing.easeInOutQuad(x, t, b, c, d);
        };
    }
    // Set base URL from meta tag or current path
        var baseUrlMeta = $('meta[name="base-url"]').attr('content');
        if (baseUrlMeta) {
            // Normalize: remove any trailing slash for consistent joins
            VapeshedTransfer.baseUrl = String(baseUrlMeta).replace(/\/$/, '');
        }
    
    // Initialize main functionality
    VapeshedTransfer.init();
    
    // Auto-dismiss alerts after 5 seconds
    $('.alert:not(.alert-permanent)').delay(5000).fadeOut(350);
    
    // Add loading states to buttons
    $('.btn[data-loading-text]').on('click', function() {
        var $btn = $(this);
        var loadingText = $btn.data('loading-text');
        if (loadingText) {
            $btn.data('original-text', $btn.html());
            $btn.html('<i class="fas fa-spinner fa-spin"></i> ' + loadingText);
            $btn.prop('disabled', true);
        }
    });
    
    // Reset button states on page show (for back button)
    $(window).on('pageshow', function() {
        $('.btn[data-original-text]').each(function() {
            var $btn = $(this);
            $btn.html($btn.data('original-text'));
            $btn.prop('disabled', false);
        });
    });
    
    // Enhanced card interactions
    $('.card').on('mouseenter', function() {
        $(this).addClass('shadow-soft');
    }).on('mouseleave', function() {
        $(this).removeClass('shadow-soft');
    });
    
    // Dashboard View Toggle (Grid/List)
    $('#gridView').on('click', function() {
        $('#quickNav').removeClass('list-view').addClass('quick-nav-grid');
        $(this).addClass('active').siblings().removeClass('active');
        localStorage.setItem('dashboardView', 'grid');
    });
    
    $('#listView').on('click', function() {
        $('#quickNav').removeClass('quick-nav-grid').addClass('list-view');
        $(this).addClass('active').siblings().removeClass('active');
        localStorage.setItem('dashboardView', 'list');
    });
    
    // Restore dashboard view preference
    const savedView = localStorage.getItem('dashboardView');
    if (savedView === 'list') {
        $('#listView').click();
    }
    
    // Real-time clock update
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-NZ', {
            hour12: false,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        $('#current-time').text(timeString);
    }
    
    // Update clock every second on dashboard
    if (window.location.pathname.includes('dashboard') || window.location.pathname.endsWith('/')) {
        setInterval(updateClock, 1000);
    }
    
    // Enhanced metrics animations with better easing
    $('.display-4, .metric-value').each(function() {
        const $this = $(this);
        const text = $this.text().trim();
        const hasPercent = text.includes('%');
        const hasComma = text.includes(',');
        const finalValue = parseInt(text.replace(/[,%]/g, '')) || 0;
        
        if (finalValue > 0) {
            let currentValue = 0;
            const duration = 2000; // 2 seconds
            const startTime = performance.now();
            
            function animateValue(timestamp) {
                const elapsed = timestamp - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // Easing function for smooth acceleration/deceleration
                const easedProgress = progress < 0.5 
                    ? 2 * progress * progress 
                    : 1 - Math.pow(-2 * progress + 2, 2) / 2;
                
                currentValue = Math.floor(finalValue * easedProgress);
                
                let displayValue = hasComma ? currentValue.toLocaleString() : currentValue.toString();
                if (hasPercent) displayValue += '%';
                
                $this.text(displayValue);
                
                if (progress < 1) {
                    requestAnimationFrame(animateValue);
                }
            }
            
            // Add small delay for staggered effect
            const delay = Math.random() * 500;
            setTimeout(() => {
                requestAnimationFrame(animateValue);
            }, delay);
        }
    });
    
    // Animate progress bars
    $('.progress-bar').each(function() {
        const $this = $(this);
        const targetWidth = $this.attr('aria-valuenow') + '%';
        
        $this.css('width', '0%');
        setTimeout(() => {
            $this.animate({ width: targetWidth }, 1500, 'easeOutCubic');
        }, 300);
    });
    
    // Smooth scrolling for anchor links
    $('a[href*="#"]:not([href="#"])').click(function() {
        if (location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '') && location.hostname == this.hostname) {
            var target = $(this.hash);
            target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 70
                }, 1000);
                return false;
            }
        }
    });
});

// Enhanced system health monitoring with better error handling
function updateSystemHealth() {
    const baseUrl = VapeshedTransfer.baseUrl || '';
    // Canonical health endpoint
    const healthApi = baseUrl.replace(/\/$/, '') + '/api/health';
    fetch(healthApi, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Health check failed');
            }
            return response.json();
        })
        .then(data => {
            // Support both flat and enveloped responses
            const payload = (data && typeof data.data === 'object') ? data.data : data || {};
            const status = payload.status || (payload.healthy ? 'ok' : 'warning') || 'ok';
            const title = payload.message || `System Status: ${String(status).toUpperCase()}`;

            // Header indicators (preferred)
            const dot = document.getElementById('vsSubnavStatus');
            const stateText = document.getElementById('vsSubnavEngineState');
            const lastRun = document.getElementById('vsSubnavLastRun');

            // Map to existing CSS classes used in header: status-dot status-ok|status-warn|status-bad
            const dotClass = (status === 'ok' || status === 'healthy' || status === 'ready') ? 'status-ok'
                           : (status === 'degraded' || status === 'warning') ? 'status-warn'
                           : 'status-bad';

            if (dot) {
                dot.className = 'status-dot ' + dotClass;
                dot.title = title;
            }
            if (stateText) stateText.textContent = String(status).toUpperCase();
            if (lastRun && payload.last_run) lastRun.textContent = payload.last_run;

            // Fallback legacy selector if present
            const legacyIndicator = document.querySelector('.status-indicator');
            if (legacyIndicator) {
                const legacyClass = (dotClass === 'status-ok') ? 'success' : (dotClass === 'status-warn') ? 'warning' : 'danger';
                legacyIndicator.className = 'status-indicator status-' + legacyClass;
                legacyIndicator.title = title;
            }
        })
        .catch(() => {
            const dot = document.getElementById('vsSubnavStatus');
            const stateText = document.getElementById('vsSubnavEngineState');
            if (dot) dot.className = 'status-dot status-warn';
            if (stateText) stateText.textContent = 'CONNECTION ERROR';

            const legacyIndicator = document.querySelector('.status-indicator');
            if (legacyIndicator) {
                legacyIndicator.className = 'status-indicator status-warning';
                legacyIndicator.title = 'Connection Error';
            }
        });
}

// Start health monitoring if on dashboard
// Periodic health checks
if (window.location.pathname.includes('dashboard') || window.location.pathname.endsWith('/')) {
    // Update health status every 30 seconds on dashboard/root
    setInterval(updateSystemHealth, 30000);
}
// Always perform an initial health check shortly after load on any page
setTimeout(updateSystemHealth, 2000);

// Wire subnav refresh globally for all pages
$(document).on('click', '#vsSubnavRefresh', function(){
    if (window.transferControlPanel && typeof window.transferControlPanel.checkEngineStatus === 'function') {
        window.transferControlPanel.checkEngineStatus();
    } else {
        updateSystemHealth();
    }
});

// Keep subnav clock ticking
setInterval(function(){
    const el = document.getElementById('vsSubnavTime');
    if (el) {
        const now = new Date();
        el.textContent = now.toLocaleTimeString('en-NZ', { hour12:false, hour:'2-digit', minute:'2-digit', second:'2-digit' });
    }
}, 1000);

// Wire emergency controls (START/KILL) to kill-switch API with CSRF
$(document).on('click', '#emergencyStop', async function(e) {
    e.preventDefault();
    const confirmed = confirm('Activate kill switch? This will immediately block all write operations.');
    if (!confirmed) return;
    await toggleKillSwitch(true);
});

$(document).on('click', '#systemResume', async function(e) {
    e.preventDefault();
    const confirmed = confirm('Deactivate kill switch and resume operations?');
    if (!confirmed) return;
    await toggleKillSwitch(false);
});

async function toggleKillSwitch(activate = true) {
    try {
        const token = $('meta[name="csrf-token"]').attr('content') || '';
        const baseUrl = VapeshedTransfer.baseUrl || '';
        const endpoint = activate ? '/api/kill-switch/activate' : '/api/kill-switch/deactivate';
        const resp = await fetch(baseUrl.replace(/\/$/, '') + endpoint, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ reason: activate ? 'emergency_stop' : 'resume_operations' })
        });
        const data = await resp.json().catch(() => ({}));
        if (!resp.ok || data.success === false || data.ok === false) {
            const msg = (data && (data.error?.message || data.error)) || ('Request failed (' + resp.status + ')');
            showNotification('Kill-switch action blocked: ' + msg, 'warning', 6000);
            return;
        }
        showNotification(activate ? 'Kill switch activated.' : 'Kill switch deactivated.', 'success');
        // Update health indicator shortly after
        setTimeout(() => { try { updateSystemHealth(); } catch(e){} }, 500);
    } catch (err) {
        showNotification('Kill-switch request error: ' + (err?.message || err), 'danger');
    }
}

// Optional lightweight UI exerciser — activates only with ?ui_test=1
(function(){
    try {
        const params = new URLSearchParams(window.location.search || '');
        if (!params.has('ui_test')) return; // opt-in only

        console.group('[UI-TEST] Exerciser start');
        const skipSelectors = '#emergencyStop, #systemResume, [data-confirm], [href^="javascript:"], a[href^="/api/kill"], button[name="delete"], button[data-action="danger"]';

        // Visual banner
        const banner = document.createElement('div');
        banner.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:1050;background:#0d6efd;color:#fff;padding:6px 10px;font-size:12px;text-align:center';
        banner.textContent = 'UI Test Mode — clicks are simulated; destructive actions are skipped';
        document.body.appendChild(banner);

        const safeClick = (el) => {
            try {
                const tag = (el.tagName || '').toLowerCase();
                if (el.matches(skipSelectors)) return { skipped: true };
                // Prevent navigation/defaults
                el.addEventListener('click', (e)=>{ e.preventDefault(); e.stopPropagation(); }, { once:true, capture:true });
                el.dispatchEvent(new MouseEvent('mouseenter', { bubbles:true }));
                el.dispatchEvent(new FocusEvent('focus', { bubbles:true }));
                el.dispatchEvent(new MouseEvent('click', { bubbles:true, cancelable:true }));
                el.dispatchEvent(new MouseEvent('mouseleave', { bubbles:true }));
                el.dispatchEvent(new FocusEvent('blur', { bubbles:true }));
                return { ok:true, tag, id: el.id || null };
            } catch (e) {
                console.warn('[UI-TEST] click failed', el, e);
                return { ok:false, error: String(e) };
            }
        };

        // Tabs
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(a => safeClick(a));
        // Collapses
        document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(btn => safeClick(btn));
        // Buttons
        document.querySelectorAll('button, .btn, a.btn').forEach(btn => safeClick(btn));

        // Page-specific hooks if present
        try { if (typeof updateSystemHealth === 'function') updateSystemHealth(); } catch(_){}
        try { if (typeof refreshLogs === 'function') refreshLogs(); } catch(_){}
        try { if (typeof loadQuickStats === 'function') loadQuickStats(); } catch(_){}
        try { if (typeof loadRecentTransfers === 'function') loadRecentTransfers(); } catch(_){}

        console.groupEnd();
        // Auto-hide banner
        setTimeout(()=>{ banner.remove(); }, 3000);
    } catch (e) {
        console.warn('[UI-TEST] exerciser init failed', e);
    }
})();

// Enhanced Notification System
function showNotification(message, type = 'info', duration = 5000) {
    const id = 'notification-' + Date.now();
    const typeClass = type === 'error' ? 'danger' : type;
    
    const notification = $(`
        <div id="${id}" class="alert alert-${typeClass} alert-dismissible fade show position-fixed shadow-lg" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 350px; max-width: 500px;">
            <div class="d-flex align-items-center">
                <span class="me-2">${getNotificationIcon(type)}</span>
                <div class="flex-grow-1">${message}</div>
                <button type="button" class="btn-close ms-2" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    `);
    
    $('body').append(notification);
    
    // Auto-dismiss
    if (duration > 0) {
        setTimeout(() => {
            $('#' + id).alert('close');
        }, duration);
    }
    
    return id;
}

function getNotificationIcon(type) {
    const icons = {
        'success': '✅',
        'info': 'ℹ️',
        'warning': '⚠️',
        'error': '❌',
        'danger': '❌'
    };
    return icons[type] || 'ℹ️';
}

// Enhanced Loading States
function setLoadingState(element, loading = true, text = 'Loading...') {
    const $el = $(element);
    
    if (loading) {
        $el.data('original-html', $el.html());
        $el.html(`<span class="fas">⏳</span> ${text}`);
        $el.prop('disabled', true);
        $el.addClass('loading');
    } else {
        const originalHtml = $el.data('original-html');
        if (originalHtml) {
            $el.html(originalHtml);
        }
        $el.prop('disabled', false);
        $el.removeClass('loading');
    }
}

// Page transition effects
$(document).on('click', 'a:not([target="_blank"]):not([href^="#"]):not(.no-transition)', function(e) {
    const href = $(this).attr('href');
    if (href && href !== '#' && !href.startsWith('javascript:')) {
        e.preventDefault();
        
        $('body').addClass('page-transition');
        
        setTimeout(() => {
            window.location.href = href;
        }, 150);
    }
});

// Enhanced form validation
$(document).on('submit', 'form', function() {
    const $form = $(this);
    const $submitBtn = $form.find('button[type="submit"], input[type="submit"]');
    
    if (!$form.hasClass('no-loading')) {
        setLoadingState($submitBtn, true, 'Processing...');
    }
    
    // Re-enable form on page show (back button handling)
    $(window).on('pageshow', function() {
        setLoadingState($submitBtn, false);
    });
});

// Auto-save functionality for forms
let autoSaveTimeout;
$(document).on('input change', 'form[data-auto-save] input, form[data-auto-save] textarea, form[data-auto-save] select', function() {
    const $form = $(this).closest('form');
    
    clearTimeout(autoSaveTimeout);
    autoSaveTimeout = setTimeout(() => {
        saveFormData($form);
    }, 2000); // Auto-save after 2 seconds of inactivity
});

function saveFormData($form) {
    const formId = $form.attr('id');
    if (!formId) return;
    
    const formData = {};
    $form.find('input, textarea, select').each(function() {
        const $field = $(this);
        const name = $field.attr('name');
        if (name && !$field.is(':password')) {
            formData[name] = $field.val();
        }
    });
    
    localStorage.setItem(`form_${formId}`, JSON.stringify(formData));
    
    // Show brief save indicator
    showNotification('Form data saved', 'success', 2000);
}

// Restore form data on page load
$(document).ready(function() {
    $('form[data-auto-save]').each(function() {
        const $form = $(this);
        const formId = $form.attr('id');
        if (!formId) return;
        
        const savedData = localStorage.getItem(`form_${formId}`);
        if (savedData) {
            try {
                const data = JSON.parse(savedData);
                Object.keys(data).forEach(name => {
                    const $field = $form.find(`[name="${name}"]`);
                    if ($field.length && !$field.is(':password')) {
                        $field.val(data[name]);
                    }
                });
            } catch (e) {
                console.warn('Failed to restore form data:', e);
            }
        }
    });
});

// Keyboard shortcuts
$(document).on('keydown', function(e) {
    // Ctrl/Cmd + S to save forms
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        const $form = $('form:visible').first();
        if ($form.length && $form.attr('data-auto-save')) {
            e.preventDefault();
            saveFormData($form);
        }
    }
    
    // Escape to close modals
    if (e.key === 'Escape') {
        $('.modal.show').modal('hide');
    }
});