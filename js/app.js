// Inventory Tracker JavaScript Application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    initializeTheme();
    initializePullToRefresh();
    initializeSwipeGestures();
    initializeVoiceInput();
    initializeOfflineSupport();
    initializeFormEnhancements();
    initializeSearchAndFilter();
    initializeTouchFeedback();
    initializeQuickActions();
}

// Theme Management
function initializeTheme() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.setAttribute('data-bs-theme', 'dark');
    }
}

function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-bs-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-bs-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    // Update theme icon
    const icon = document.querySelector('[data-feather="moon"]');
    if (icon) {
        icon.setAttribute('data-feather', newTheme === 'dark' ? 'sun' : 'moon');
        feather.replace();
    }
}

// Pull to Refresh
function initializePullToRefresh() {
    let startY = 0;
    let currentY = 0;
    let pullDistance = 0;
    const threshold = 100;
    
    document.addEventListener('touchstart', function(e) {
        startY = e.touches[0].clientY;
    }, { passive: true });
    
    document.addEventListener('touchmove', function(e) {
        currentY = e.touches[0].clientY;
        pullDistance = currentY - startY;
        
        if (pullDistance > 0 && window.scrollY === 0) {
            e.preventDefault();
            // Visual feedback for pull to refresh
            const navbar = document.querySelector('.navbar');
            if (navbar && pullDistance > threshold) {
                navbar.style.transform = `translateY(${Math.min(pullDistance - threshold, 50)}px)`;
            }
        }
    }, { passive: false });
    
    document.addEventListener('touchend', function(e) {
        if (pullDistance > threshold && window.scrollY === 0) {
            // Trigger refresh
            location.reload();
        }
        
        // Reset navbar position
        const navbar = document.querySelector('.navbar');
        if (navbar) {
            navbar.style.transform = '';
        }
        
        startY = 0;
        currentY = 0;
        pullDistance = 0;
    });
}

// Swipe Gestures
function initializeSwipeGestures() {
    let startX = 0;
    let startY = 0;
    
    document.addEventListener('touchstart', function(e) {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
    }, { passive: true });
    
    document.addEventListener('touchend', function(e) {
        if (!startX || !startY) return;
        
        const endX = e.changedTouches[0].clientX;
        const endY = e.changedTouches[0].clientY;
        
        const diffX = startX - endX;
        const diffY = startY - endY;
        
        // Only trigger swipe if horizontal movement is greater than vertical
        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
            if (diffX > 0) {
                // Swiped left
                handleSwipeLeft(e.target);
            } else {
                // Swiped right
                handleSwipeRight(e.target);
            }
        }
        
        startX = 0;
        startY = 0;
    });
}

function handleSwipeLeft(element) {
    // Find closest list item
    const listItem = element.closest('.list-group-item');
    if (listItem && listItem.dataset.swipeActions) {
        showSwipeActions(listItem, 'left');
    }
}

function handleSwipeRight(element) {
    const listItem = element.closest('.list-group-item');
    if (listItem && listItem.dataset.swipeActions) {
        showSwipeActions(listItem, 'right');
    }
}

function showSwipeActions(listItem, direction) {
    // Implementation for swipe actions (e.g., quick edit, delete)
    const actions = JSON.parse(listItem.dataset.swipeActions);
    if (actions[direction]) {
        // Show action buttons or trigger action
        console.log(`Swipe ${direction} action:`, actions[direction]);
    }
}

// Voice Input Support
function initializeVoiceInput() {
    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        return; // Speech recognition not supported
    }
    
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const recognition = new SpeechRecognition();
    
    recognition.continuous = false;
    recognition.interimResults = false;
    recognition.lang = 'en-US';
    
    // Add voice input buttons to number inputs
    document.querySelectorAll('input[type="number"]').forEach(input => {
        if (input.classList.contains('voice-input-enabled')) {
            addVoiceInputButton(input, recognition);
        }
    });
}

function addVoiceInputButton(input, recognition) {
    const container = document.createElement('div');
    container.className = 'input-group';
    
    input.parentNode.insertBefore(container, input);
    container.appendChild(input);
    
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-outline-secondary';
    button.innerHTML = '<i data-feather="mic"></i>';
    button.title = 'Voice Input';
    
    container.appendChild(button);
    
    button.addEventListener('click', function() {
        recognition.start();
        button.classList.add('btn-danger');
        button.innerHTML = '<i data-feather="mic-off"></i>';
    });
    
    recognition.onresult = function(event) {
        const transcript = event.results[0][0].transcript;
        const number = parseFloat(transcript.replace(/[^\d.]/g, ''));
        if (!isNaN(number)) {
            input.value = number;
            input.dispatchEvent(new Event('input'));
        }
    };
    
    recognition.onend = function() {
        button.classList.remove('btn-danger');
        button.innerHTML = '<i data-feather="mic"></i>';
        feather.replace();
    };
    
    feather.replace();
}

// Offline Support
function initializeOfflineSupport() {
    // Store form data locally when offline
    let offlineData = JSON.parse(localStorage.getItem('offlineData') || '[]');
    
    window.addEventListener('online', function() {
        if (offlineData.length > 0) {
            showAlert('Connection restored. Syncing offline data...', 'info');
            syncOfflineData();
        }
    });
    
    window.addEventListener('offline', function() {
        showAlert('You are now offline. Changes will be saved locally.', 'warning');
    });
    
    // Intercept form submissions when offline
    document.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            storeOfflineData(e.target);
        }
    });
}

function storeOfflineData(form) {
    const formData = new FormData(form);
    const data = {
        timestamp: Date.now(),
        action: form.action,
        method: form.method,
        data: Object.fromEntries(formData)
    };
    
    let offlineData = JSON.parse(localStorage.getItem('offlineData') || '[]');
    offlineData.push(data);
    localStorage.setItem('offlineData', JSON.stringify(offlineData));
    
    showAlert('Data saved offline. Will sync when connection is restored.', 'info');
}

function syncOfflineData() {
    const offlineData = JSON.parse(localStorage.getItem('offlineData') || '[]');
    
    offlineData.forEach(async (item, index) => {
        try {
            const response = await fetch(item.action, {
                method: item.method,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(item.data)
            });
            
            if (response.ok) {
                // Remove synced item
                offlineData.splice(index, 1);
            }
        } catch (error) {
            console.error('Sync failed:', error);
        }
    });
    
    localStorage.setItem('offlineData', JSON.stringify(offlineData));
    
    if (offlineData.length === 0) {
        showAlert('All offline data synced successfully!', 'success');
        setTimeout(() => location.reload(), 1000);
    }
}

// Form Enhancements
function initializeFormEnhancements() {
    // Auto-focus on quantity fields
    document.querySelectorAll('.auto-focus').forEach(input => {
        input.addEventListener('click', function() {
            this.select();
        });
    });
    
    // Auto-submit forms on Enter
    document.querySelectorAll('.auto-submit').forEach(form => {
        form.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                this.submit();
            }
        });
    });
    
    // Number input formatters
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('input', function() {
            if (this.dataset.format === 'currency') {
                formatCurrencyInput(this);
            } else if (this.dataset.format === 'percentage') {
                formatPercentageInput(this);
            }
        });
    });
    
    // Loading states for forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
            }
        });
    });
}

function formatCurrencyInput(input) {
    let value = input.value.replace(/[^\d.]/g, '');
    if (value) {
        value = parseFloat(value).toFixed(2);
        input.value = value;
    }
}

function formatPercentageInput(input) {
    let value = parseFloat(input.value);
    if (value > 100) {
        input.value = 100;
    } else if (value < 0) {
        input.value = 0;
    }
}

// Search and Filter
function initializeSearchAndFilter() {
    document.querySelectorAll('.search-input').forEach(input => {
        input.addEventListener('input', debounce(function() {
            const query = this.value.toLowerCase();
            const targetSelector = this.dataset.target;
            const items = document.querySelectorAll(targetSelector);
            
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(query) || query === '') {
                    item.style.display = '';
                    item.classList.add('fade-in');
                } else {
                    item.style.display = 'none';
                }
            });
        }, 300));
    });
    
    // Quick filters
    document.querySelectorAll('.quick-filter').forEach(filter => {
        filter.addEventListener('click', function() {
            const filterType = this.dataset.filter;
            const targetSelector = this.dataset.target;
            const items = document.querySelectorAll(targetSelector);
            
            // Remove active state from other filters
            document.querySelectorAll('.quick-filter').forEach(f => f.classList.remove('active'));
            this.classList.add('active');
            
            items.forEach(item => {
                if (filterType === 'all' || item.classList.contains(filterType)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Touch Feedback
function initializeTouchFeedback() {
    // Add haptic feedback on supported devices
    document.querySelectorAll('.btn, .list-group-item, .card').forEach(element => {
        element.addEventListener('touchstart', function() {
            if (navigator.vibrate) {
                navigator.vibrate(10); // Very short vibration
            }
            this.classList.add('touch-active');
        });
        
        element.addEventListener('touchend', function() {
            this.classList.remove('touch-active');
        });
    });
}

// Quick Actions
function initializeQuickActions() {
    // Long press menus
    let longPressTimer;
    
    document.querySelectorAll('[data-long-press]').forEach(element => {
        element.addEventListener('touchstart', function(e) {
            longPressTimer = setTimeout(() => {
                showLongPressMenu(this, e.touches[0].clientX, e.touches[0].clientY);
            }, 800);
        });
        
        element.addEventListener('touchend', function() {
            clearTimeout(longPressTimer);
        });
        
        element.addEventListener('touchmove', function() {
            clearTimeout(longPressTimer);
        });
    });
}

function showLongPressMenu(element, x, y) {
    const menu = document.createElement('div');
    menu.className = 'long-press-menu';
    menu.style.position = 'fixed';
    menu.style.left = x + 'px';
    menu.style.top = y + 'px';
    menu.style.zIndex = '1050';
    menu.style.background = 'white';
    menu.style.borderRadius = '8px';
    menu.style.boxShadow = '0 4px 8px rgba(0,0,0,0.2)';
    menu.style.padding = '8px';
    
    const actions = JSON.parse(element.dataset.longPress);
    actions.forEach(action => {
        const button = document.createElement('button');
        button.className = 'btn btn-sm btn-outline-primary d-block mb-1';
        button.textContent = action.label;
        button.onclick = () => {
            eval(action.action);
            document.body.removeChild(menu);
        };
        menu.appendChild(button);
    });
    
    document.body.appendChild(menu);
    
    // Remove menu on outside click
    setTimeout(() => {
        document.addEventListener('click', function removeMenu() {
            if (document.body.contains(menu)) {
                document.body.removeChild(menu);
            }
            document.removeEventListener('click', removeMenu);
        });
    }, 100);
}

// Utility Functions
function showAlert(message, type = 'info') {
    const alertContainer = document.createElement('div');
    alertContainer.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertContainer.style.top = '20px';
    alertContainer.style.right = '20px';
    alertContainer.style.zIndex = '1055';
    alertContainer.style.maxWidth = '300px';
    
    alertContainer.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertContainer);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (document.body.contains(alertContainer)) {
            alertContainer.classList.remove('show');
            setTimeout(() => {
                if (document.body.contains(alertContainer)) {
                    document.body.removeChild(alertContainer);
                }
            }, 150);
        }
    }, 5000);
}

function updateStockProgress(element, current, reorder, max) {
    const percentage = Math.min((current / max) * 100, 100);
    const progressBar = element.querySelector('.stock-progress-bar');
    
    if (progressBar) {
        progressBar.style.width = percentage + '%';
        
        if (current <= 0) {
            progressBar.className = 'stock-progress-bar bg-danger';
        } else if (current <= reorder) {
            progressBar.className = 'stock-progress-bar bg-warning';
        } else {
            progressBar.className = 'stock-progress-bar bg-success';
        }
    }
}

function animateNumber(element, start, end, duration = 1000) {
    const startTime = performance.now();
    const difference = end - start;
    
    function updateNumber(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Easing function (ease-out)
        const easeOut = 1 - Math.pow(1 - progress, 3);
        
        const current = Math.round(start + (difference * easeOut));
        element.textContent = current.toLocaleString();
        
        if (progress < 1) {
            requestAnimationFrame(updateNumber);
        }
    }
    
    requestAnimationFrame(updateNumber);
}

// Production Calculator Functions
function calculateProduction(moldId, targetParts, availableMaterial) {
    // This would typically make an AJAX call to the server
    // For now, we'll do a simple client-side calculation
    
    const mockMoldData = {
        totalCavities: 4,
        shotSize: 2.5
    };
    
    const shotsNeeded = Math.ceil(targetParts / mockMoldData.totalCavities);
    const materialNeeded = shotsNeeded * mockMoldData.shotSize;
    const maxParts = Math.floor(availableMaterial / mockMoldData.shotSize) * mockMoldData.totalCavities;
    
    return {
        shotsNeeded,
        materialNeeded,
        maxParts,
        canProduce: materialNeeded <= availableMaterial
    };
}

// API Helper Functions
async function apiCall(url, data = null, method = 'GET') {
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.getElementById('csrf-token')?.value
        }
    };
    
    if (data) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.error || 'Request failed');
        }
        
        return result;
    } catch (error) {
        console.error('API call failed:', error);
        showAlert(error.message, 'error');
        throw error;
    }
}

// Export functions for global use
window.toggleTheme = toggleTheme;
window.showAlert = showAlert;
window.updateStockProgress = updateStockProgress;
window.animateNumber = animateNumber;
window.calculateProduction = calculateProduction;
window.apiCall = apiCall;