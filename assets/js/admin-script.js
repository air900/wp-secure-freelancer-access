/**
 * Restricted Pages Access - Admin Script
 * Enhanced UI with search, filters, sorting, and counters
 */

(function() {
	'use strict';

	let originalCheckboxStates = {};
	let hasUnsavedChanges = false;

	/**
	 * Initialize on DOM ready
	 */
	document.addEventListener('DOMContentLoaded', function() {
		initSelectAllButtons();
		initSearchFilters();
		initStatusFilters();
		initSortButtons();
		initCounters();
		initVisibilityFilters();
		initUnsavedChangesWarning();
		initCheckboxChangeTracking();
	});

	/**
	 * Initialize "Select All" and "Deselect All" buttons
	 */
	function initSelectAllButtons() {
		// Handle "Select All" buttons
		const selectAllButtons = document.querySelectorAll('.rpa-select-all');
		selectAllButtons.forEach(function(button) {
			button.addEventListener('click', function(e) {
				e.preventDefault();
				const targetName = this.getAttribute('data-target');
				toggleVisibleCheckboxes(targetName, true);
			});
		});

		// Handle "Deselect All" buttons
		const deselectAllButtons = document.querySelectorAll('.rpa-deselect-all');
		deselectAllButtons.forEach(function(button) {
			button.addEventListener('click', function(e) {
				e.preventDefault();
				const targetName = this.getAttribute('data-target');
				toggleVisibleCheckboxes(targetName, false);
			});
		});

		// Handle "Select Published" buttons
		const selectPublishedButtons = document.querySelectorAll('.rpa-select-published');
		selectPublishedButtons.forEach(function(button) {
			button.addEventListener('click', function(e) {
				e.preventDefault();
				const targetName = this.getAttribute('data-target');
				selectByStatus(targetName, 'publish');
			});
		});
	}

	/**
	 * Toggle only visible checkboxes (respecting filters)
	 */
	function toggleVisibleCheckboxes(targetName, checked) {
		const container = document.querySelector('[data-content-type="' + targetName + '"]');
		if (!container) return;

		const visibleLabels = container.querySelectorAll('label:not([style*="display: none"])');
		visibleLabels.forEach(function(label) {
			const checkbox = label.querySelector('input[name="' + targetName + '[]"]');
			if (checkbox) {
				checkbox.checked = checked;
			}
		});
		updateCounter(targetName);
		markAsUnsaved();
	}

	/**
	 * Select checkboxes by status
	 */
	function selectByStatus(targetName, status) {
		const container = document.querySelector('[data-content-type="' + targetName + '"]');
		if (!container) return;

		const labels = container.querySelectorAll('label');
		labels.forEach(function(label) {
			const statusSpan = label.querySelector('.rpa-content-status');
			if (statusSpan && statusSpan.textContent.includes(status)) {
				const checkbox = label.querySelector('input[name="' + targetName + '[]"]');
				if (checkbox) {
					checkbox.checked = true;
				}
			}
		});
		updateCounter(targetName);
		markAsUnsaved();
	}

	/**
	 * Initialize search filters
	 */
	function initSearchFilters() {
		const searchInputs = document.querySelectorAll('.rpa-search-input');
		searchInputs.forEach(function(input) {
			input.addEventListener('input', function() {
				const searchTerm = this.value.toLowerCase();
				const targetType = this.getAttribute('data-target');
				filterItems(targetType, searchTerm);
			});
		});
	}

	/**
	 * Filter items by search term
	 */
	function filterItems(targetType, searchTerm) {
		const container = document.querySelector('[data-content-type="' + targetType + '"]');
		if (!container) return;

		const labels = container.querySelectorAll('label');
		let visibleCount = 0;

		labels.forEach(function(label) {
			const text = label.textContent.toLowerCase();
			const isVisible = text.includes(searchTerm);

			label.style.display = isVisible ? 'block' : 'none';
			if (isVisible) visibleCount++;
		});

		updateVisibleCount(targetType, visibleCount);
	}

	/**
	 * Initialize status filters
	 */
	function initStatusFilters() {
		const statusSelects = document.querySelectorAll('.rpa-status-filter');
		statusSelects.forEach(function(select) {
			select.addEventListener('change', function() {
				const targetType = this.getAttribute('data-target');
				const selectedStatus = this.value;
				filterByStatus(targetType, selectedStatus);
			});
		});
	}

	/**
	 * Filter items by status
	 */
	function filterByStatus(targetType, status) {
		const container = document.querySelector('[data-content-type="' + targetType + '"]');
		if (!container) return;

		const labels = container.querySelectorAll('label');
		let visibleCount = 0;

		labels.forEach(function(label) {
			const statusSpan = label.querySelector('.rpa-content-status');
			let isVisible = true;

			if (status !== 'all' && statusSpan) {
				isVisible = statusSpan.textContent.includes(status);
			}

			label.style.display = isVisible ? 'block' : 'none';
			if (isVisible) visibleCount++;
		});

		updateVisibleCount(targetType, visibleCount);
	}

	/**
	 * Initialize sort buttons
	 */
	function initSortButtons() {
		const sortSelects = document.querySelectorAll('.rpa-sort-select');
		sortSelects.forEach(function(select) {
			select.addEventListener('change', function() {
				const targetType = this.getAttribute('data-target');
				const sortBy = this.value;
				sortItems(targetType, sortBy);
			});
		});
	}

	/**
	 * Sort items
	 */
	function sortItems(targetType, sortBy) {
		const container = document.querySelector('[data-content-type="' + targetType + '"]');
		if (!container) return;

		const labels = Array.from(container.querySelectorAll('label'));

		labels.sort(function(a, b) {
			if (sortBy === 'id') {
				const idA = parseInt(a.textContent.match(/\[(\d+)\]/)[1]);
				const idB = parseInt(b.textContent.match(/\[(\d+)\]/)[1]);
				return idA - idB;
			} else if (sortBy === 'title') {
				const titleA = a.textContent.split(']')[1].toLowerCase();
				const titleB = b.textContent.split(']')[1].toLowerCase();
				return titleA.localeCompare(titleB);
			}
			return 0;
		});

		// Re-append sorted labels
		labels.forEach(function(label) {
			container.appendChild(label);
		});
	}

	/**
	 * Initialize counters
	 */
	function initCounters() {
		const containers = document.querySelectorAll('[data-content-type]');
		containers.forEach(function(container) {
			const targetType = container.getAttribute('data-content-type');
			updateCounter(targetType);

			// Update counter on checkbox change
			const checkboxes = container.querySelectorAll('input[type="checkbox"]');
			checkboxes.forEach(function(checkbox) {
				checkbox.addEventListener('change', function() {
					updateCounter(targetType);
					markAsUnsaved();
				});
			});
		});
	}

	/**
	 * Update counter for content type
	 */
	function updateCounter(targetType) {
		const container = document.querySelector('[data-content-type="' + targetType + '"]');
		if (!container) return;

		const allCheckboxes = container.querySelectorAll('input[type="checkbox"]');
		const checkedCheckboxes = container.querySelectorAll('input[type="checkbox"]:checked');

		const counterElement = document.querySelector('.rpa-counter[data-target="' + targetType + '"]');
		if (counterElement) {
			counterElement.textContent = checkedCheckboxes.length + ' / ' + allCheckboxes.length;
		}
	}

	/**
	 * Update visible count
	 */
	function updateVisibleCount(targetType, count) {
		const visibleCountElement = document.querySelector('.rpa-visible-count[data-target="' + targetType + '"]');
		if (visibleCountElement) {
			visibleCountElement.textContent = '(' + count + ' shown)';
		}
	}

	/**
	 * Initialize visibility filters (show selected/unselected)
	 */
	function initVisibilityFilters() {
		const visibilitySelects = document.querySelectorAll('.rpa-visibility-filter');
		visibilitySelects.forEach(function(select) {
			select.addEventListener('change', function() {
				const targetType = this.getAttribute('data-target');
				const filterType = this.value;
				filterByVisibility(targetType, filterType);
			});
		});
	}

	/**
	 * Filter by visibility (all/selected/unselected)
	 */
	function filterByVisibility(targetType, filterType) {
		const container = document.querySelector('[data-content-type="' + targetType + '"]');
		if (!container) return;

		const labels = container.querySelectorAll('label');
		let visibleCount = 0;

		labels.forEach(function(label) {
			const checkbox = label.querySelector('input[type="checkbox"]');
			let isVisible = true;

			if (filterType === 'selected') {
				isVisible = checkbox && checkbox.checked;
			} else if (filterType === 'unselected') {
				isVisible = checkbox && !checkbox.checked;
			}

			label.style.display = isVisible ? 'block' : 'none';
			if (isVisible) visibleCount++;
		});

		updateVisibleCount(targetType, visibleCount);
	}

	/**
	 * Initialize unsaved changes warning
	 */
	function initUnsavedChangesWarning() {
		// Save original states
		const checkboxes = document.querySelectorAll('input[type="checkbox"][name^="allowed_"]');
		checkboxes.forEach(function(checkbox) {
			originalCheckboxStates[checkbox.value] = checkbox.checked;
		});

		// Warn before leaving page
		window.addEventListener('beforeunload', function(e) {
			if (hasUnsavedChanges) {
				e.preventDefault();
				e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
				return e.returnValue;
			}
		});

		// Clear warning on form submit
		const form = document.querySelector('form[method="post"]');
		if (form) {
			form.addEventListener('submit', function() {
				hasUnsavedChanges = false;
			});
		}
	}

	/**
	 * Track checkbox changes
	 */
	function initCheckboxChangeTracking() {
		const checkboxes = document.querySelectorAll('input[type="checkbox"][name^="allowed_"]');
		checkboxes.forEach(function(checkbox) {
			checkbox.addEventListener('change', function() {
				markAsUnsaved();
			});
		});
	}

	/**
	 * Mark form as having unsaved changes
	 */
	function markAsUnsaved() {
		hasUnsavedChanges = true;
		const indicator = document.querySelector('.rpa-unsaved-indicator');
		if (indicator) {
			indicator.style.display = 'block';
		}
	}

})();
