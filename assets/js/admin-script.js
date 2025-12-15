/**
 * Restricted Pages Access - Admin Script
 * Handles checkbox selection functionality
 */

(function() {
	'use strict';

	/**
	 * Initialize on DOM ready
	 */
	document.addEventListener('DOMContentLoaded', function() {
		initSelectAllButtons();
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
				toggleCheckboxes(targetName, true);
			});
		});

		// Handle "Deselect All" buttons
		const deselectAllButtons = document.querySelectorAll('.rpa-deselect-all');
		deselectAllButtons.forEach(function(button) {
			button.addEventListener('click', function(e) {
				e.preventDefault();
				const targetName = this.getAttribute('data-target');
				toggleCheckboxes(targetName, false);
			});
		});
	}

	/**
	 * Toggle all checkboxes with given name
	 * @param {string} targetName - The name attribute of checkboxes
	 * @param {boolean} checked - Whether to check or uncheck
	 */
	function toggleCheckboxes(targetName, checked) {
		const checkboxes = document.querySelectorAll('input[name="' + targetName + '[]"]');
		checkboxes.forEach(function(checkbox) {
			checkbox.checked = checked;
		});
	}

})();
