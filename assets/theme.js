// assets/theme.js
// Theme toggle functionality with localStorage persistence

(function() {
  'use strict';

  // Get the current theme from localStorage or default to dark
  function getCurrentTheme() {
    return localStorage.getItem('theme') || 'dark';
  }

  // Set theme on the document
  function setTheme(theme) {
    if (theme === 'light') {
      document.documentElement.setAttribute('data-theme', 'light');
    } else {
      document.documentElement.removeAttribute('data-theme');
    }
    localStorage.setItem('theme', theme);
  }

  // Toggle between light and dark themes
  function toggleTheme() {
    const currentTheme = getCurrentTheme();
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    setTheme(newTheme);
    updateToggleButton();
  }

  // Update the toggle button text
  function updateToggleButton() {
    const button = document.getElementById('theme-toggle');
    if (button) {
      const currentTheme = getCurrentTheme();
      button.textContent = currentTheme === 'dark' ? '☀️ Light' : '🌙 Dark';
      button.setAttribute('aria-label', `Switch to ${currentTheme === 'dark' ? 'light' : 'dark'} mode`);
    }
  }

  // Initialize theme on page load
  function initTheme() {
    const theme = getCurrentTheme();
    setTheme(theme);
    updateToggleButton();
  }

  // Setup event listeners
  function setupEventListeners() {
    const toggleButton = document.getElementById('theme-toggle');
    if (toggleButton) {
      toggleButton.addEventListener('click', toggleTheme);
    }
  }

  // User dropdown toggle
  function initUserDropdown() {
    const userBox = document.querySelector('.user-box');
    const dropdownMenu = document.querySelector('.dropdown-menu');
    
    if (userBox && dropdownMenu) {
      userBox.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdownMenu.classList.toggle('show');
      });

      // Close dropdown when clicking outside
      document.addEventListener('click', function(e) {
        if (!userBox.contains(e.target) && !dropdownMenu.contains(e.target)) {
          dropdownMenu.classList.remove('show');
        }
      });

      // Close dropdown when pressing Escape
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && dropdownMenu.classList.contains('show')) {
          dropdownMenu.classList.remove('show');
        }
      });
    }
  }

  // Initialize on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      initTheme();
      setupEventListeners();
      initUserDropdown();
    });
  } else {
    initTheme();
    setupEventListeners();
    initUserDropdown();
  }

  // Expose setTheme globally for external use
  window.setTheme = setTheme;
})();
