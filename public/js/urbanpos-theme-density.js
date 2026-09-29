/**
 * UrbanPOS Modern Theme & Table Density Engine
 * Persistent Dark/Light Mode and Compact/Comfortable Data-Table Controls
 */
(function() {
    'use strict';

    // 1. THEME CONTROLLER
    var UrbanPosTheme = {
        STORAGE_KEY: 'urbanpos_theme',

        get: function() {
            var saved = localStorage.getItem(this.STORAGE_KEY);
            if (saved === 'dark' || saved === 'light') {
                return saved;
            }
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                return 'dark';
            }
            return 'light';
        },

        set: function(theme) {
            var isDark = (theme === 'dark');
            localStorage.setItem(this.STORAGE_KEY, theme);

            if (isDark) {
                document.documentElement.classList.add('dark-mode');
                document.body && document.body.classList.add('dark-mode');
            } else {
                document.documentElement.classList.remove('dark-mode');
                document.body && document.body.classList.remove('dark-mode');
            }

            this.updateIcons(isDark);
        },

        toggle: function() {
            var current = this.get();
            var next = (current === 'dark') ? 'light' : 'dark';
            this.set(next);
        },

        updateIcons: function(isDark) {
            var iconEls = document.querySelectorAll('#theme-mode-icon, .theme-mode-icon');
            iconEls.forEach(function(icon) {
                if (isDark) {
                    icon.className = 'fas fa-sun text-warning';
                    if (icon.parentElement) {
                        icon.parentElement.setAttribute('title', 'Switch to Light Mode (Shift+Alt+D)');
                    }
                } else {
                    icon.className = 'fas fa-moon text-secondary';
                    if (icon.parentElement) {
                        icon.parentElement.setAttribute('title', 'Switch to Dark Mode (Shift+Alt+D)');
                    }
                }
            });
        },

        init: function() {
            document.documentElement.classList.remove('dark-mode');
            document.body && document.body.classList.remove('dark-mode');
        }
    };

    // 2. DENSITY CONTROLLER
    var UrbanPosDensity = {
        STORAGE_KEY: 'urbanpos_table_density',

        get: function() {
            var saved = localStorage.getItem(this.STORAGE_KEY);
            return (saved === 'comfortable') ? 'comfortable' : 'compact';
        },

        set: function(density) {
            var isComfortable = (density === 'comfortable');
            localStorage.setItem(this.STORAGE_KEY, density);

            if (isComfortable) {
                document.documentElement.classList.remove('density-compact');
                document.documentElement.classList.add('density-comfortable');
                if (document.body) {
                    document.body.classList.remove('density-compact');
                    document.body.classList.add('density-comfortable');
                }
            } else {
                document.documentElement.classList.remove('density-comfortable');
                document.documentElement.classList.add('density-compact');
                if (document.body) {
                    document.body.classList.remove('density-comfortable');
                    document.body.classList.add('density-compact');
                }
            }

            this.updateIcons(isComfortable);
        },

        toggle: function() {
            var current = this.get();
            var next = (current === 'comfortable') ? 'compact' : 'comfortable';
            this.set(next);
        },

        updateIcons: function(isComfortable) {
            var iconEls = document.querySelectorAll('#table-density-icon, .table-density-icon');
            iconEls.forEach(function(icon) {
                if (isComfortable) {
                    icon.className = 'fas fa-compress-arrows-alt text-primary';
                    if (icon.parentElement) {
                        icon.parentElement.setAttribute('title', 'Switch to Compact View (Shift+Alt+C)');
                    }
                } else {
                    icon.className = 'fas fa-expand-arrows-alt text-secondary';
                    if (icon.parentElement) {
                        icon.parentElement.setAttribute('title', 'Switch to Comfortable View (Shift+Alt+C)');
                    }
                }
            });
        },

        init: function() {
            var current = this.get();
            this.set(current);
        }
    };

    // Expose globally
    window.UrbanPosTheme = UrbanPosTheme;
    window.UrbanPosDensity = UrbanPosDensity;

    // Apply immediately to prevent layout shifts
    UrbanPosTheme.init();
    UrbanPosDensity.init();

    // DOM Ready binds
    document.addEventListener('DOMContentLoaded', function() {
        UrbanPosTheme.init();
        UrbanPosDensity.init();

        // Theme toggle button
        var themeBtn = document.getElementById('btn-toggle-theme-mode');
        if (themeBtn) {
            themeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                UrbanPosTheme.toggle();
            });
        }

        // Density toggle button
        var densityBtn = document.getElementById('btn-toggle-table-density');
        if (densityBtn) {
            densityBtn.addEventListener('click', function(e) {
                e.preventDefault();
                UrbanPosDensity.toggle();
            });
        }

        // Keyboard hotkeys
        document.addEventListener('keydown', function(e) {
            // Shift + Alt + D => Toggle Dark/Light Theme
            if (e.shiftKey && e.altKey && (e.key === 'D' || e.key === 'd')) {
                e.preventDefault();
                UrbanPosTheme.toggle();
            }
            // Shift + Alt + C => Toggle Table Density
            if (e.shiftKey && e.altKey && (e.key === 'C' || e.key === 'c')) {
                e.preventDefault();
                UrbanPosDensity.toggle();
            }
        });
    });
})();
