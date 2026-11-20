/**
 * Copyright (c) 2026 Indaka Barody
 * Multi-Journal Control Panel
 * Developed for Open Journal Systems (OJS) 3.3.x.
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

(function () {
	'use strict';

	function initMultiJournalControlPanel() {
		// 1. Apply dynamic progress widths and bar chart heights from data-* attributes
		var progressFills = document.querySelectorAll('.mjcp-progress-fill[data-width]');
		progressFills.forEach(function (el) {
			var w = parseFloat(el.getAttribute('data-width') || '0');
			if (!isNaN(w)) {
				el.style.width = Math.min(100, Math.max(0, w)) + '%';
			}
		});

		var chartBars = document.querySelectorAll('.mjcp-barchart__bar[data-height]');
		chartBars.forEach(function (bar) {
			var h = parseFloat(bar.getAttribute('data-height') || '10');
			if (!isNaN(h)) {
				bar.style.height = Math.min(100, Math.max(6, h)) + '%';
			}
		});

		// Initialize AjaxFormHandler if modal form is present in OJS backend
		if (window.jQuery && window.jQuery('#mjcpPluginSettingsForm').length && window.jQuery.fn.pkpHandler) {
			window.jQuery('#mjcpPluginSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
		}

		var root = document.getElementById('mjcpControlPanelRoot');
		if (!root) {
			return;
		}

		var csrfToken = root.getAttribute('data-csrf-token') || '';
		var exportBaseUrl = root.getAttribute('data-export-url') || '';
		var syncUrl = root.getAttribute('data-sync-url') || '';
		var generateTokenUrl = root.getAttribute('data-generate-token-url') || '';
		var revokeTokenUrl = root.getAttribute('data-revoke-token-url') || '';
		var toggleTestimonialUrl = root.getAttribute('data-toggle-testimonial-url') || '';
		var importPaymentsUrl = root.getAttribute('data-import-payments-url') || '';
		var addHolidayUrl = root.getAttribute('data-add-holiday-url') || '';
		var deleteHolidayUrl = root.getAttribute('data-delete-holiday-url') || '';
		var copiedText = root.getAttribute('data-copied-text') || '';

		// 2. Auto-submit filter dropdowns
		var filterForm = document.getElementById('mjcpFilterForm');
		var autoSelects = root.querySelectorAll('.mjcp-auto-submit');
		autoSelects.forEach(function (sel) {
			sel.addEventListener('change', function () {
				if (filterForm) {
					filterForm.submit();
				}
			});
		});

		// 3. Export triggers (Excel, CSV, PDF) preserving active filters
		var exportBtns = root.querySelectorAll('.mjcp-export-trigger');
		exportBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!exportBaseUrl) {
					return;
				}
				var mod = btn.getAttribute('data-module') || 'pipeline';
				var fmt = btn.getAttribute('data-format') || 'xlsx';
				var params = new URLSearchParams();
				if (filterForm) {
					var fd = new FormData(filterForm);
					fd.forEach(function (val, key) {
						if (val !== null && String(val).trim() !== '') {
							params.set(key, String(val));
						}
					});
				}
				params.set('module', mod);
				params.set('format', fmt);
				var sep = exportBaseUrl.indexOf('?') === -1 ? '?' : '&';
				window.location.href = exportBaseUrl + sep + params.toString();
			});
		});

		// 4. Per-journal manual sync button
		var syncJournalBtns = root.querySelectorAll('.mjcp-sync-journal-btn');
		syncJournalBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!syncUrl) {
					return;
				}
				var ctxId = btn.getAttribute('data-context-id');
				btn.disabled = true;
				postUrlEncoded(syncUrl, {
					csrfToken: csrfToken,
					contextId: ctxId,
					ajax: 1
				}).then(function () {
					window.location.reload();
				}).catch(function () {
					btn.disabled = false;
				});
			});
		});

		var triggerSyncNowBtn = document.getElementById('mjcpTriggerSyncNowBtn');
		if (triggerSyncNowBtn) {
			triggerSyncNowBtn.addEventListener('click', function () {
				var targetUrl = syncUrl || window.location.href.replace(/\/settings.*$/, '/sync');
				triggerSyncNowBtn.disabled = true;
				postUrlEncoded(targetUrl, {
					csrfToken: csrfToken,
					ajax: 1
				}).then(function () {
					window.location.reload();
				}).catch(function () {
					triggerSyncNowBtn.disabled = false;
				});
			});
		}

		// 5. Author Link Management: Select All, Create Token, Bulk Create, Copy Link, Revoke Token
		var selectAllCheckbox = document.getElementById('mjcpSelectAllLinksCheckbox');
		if (selectAllCheckbox) {
			selectAllCheckbox.addEventListener('change', function () {
				var boxes = root.querySelectorAll('.mjcp-link-row-checkbox');
				boxes.forEach(function (cb) {
					cb.checked = selectAllCheckbox.checked;
				});
			});
		}

		var createTokenBtns = root.querySelectorAll('.mjcp-create-token-btn');
		createTokenBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!generateTokenUrl) {
					return;
				}
				var subId = btn.getAttribute('data-submission-id');
				btn.disabled = true;
				postUrlEncoded(generateTokenUrl, {
					csrfToken: csrfToken,
					submissionId: subId,
					ajax: 1
				}).then(function () {
					window.location.reload();
				});
			});
		});

		var bulkGenerateBtn = document.getElementById('mjcpGenerateSelectedTokensBtn');
		if (bulkGenerateBtn && generateTokenUrl) {
			bulkGenerateBtn.addEventListener('click', function () {
				var checked = root.querySelectorAll('.mjcp-link-row-checkbox:checked');
				var ids = [];
				checked.forEach(function (cb) {
					ids.push(cb.value);
				});
				if (ids.length === 0) {
					var allBoxes = root.querySelectorAll('.mjcp-link-row-checkbox');
					allBoxes.forEach(function (cb) {
						ids.push(cb.value);
					});
				}
				if (ids.length === 0) {
					return;
				}
				bulkGenerateBtn.disabled = true;
				postUrlEncoded(generateTokenUrl, {
					csrfToken: csrfToken,
					submissionIds: ids.join(','),
					ajax: 1
				}).then(function () {
					window.location.reload();
				});
			});
		}

		var revokeTokenBtns = root.querySelectorAll('.mjcp-revoke-token-btn');
		revokeTokenBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!revokeTokenUrl) {
					return;
				}
				var subId = btn.getAttribute('data-submission-id');
				btn.disabled = true;
				postUrlEncoded(revokeTokenUrl, {
					csrfToken: csrfToken,
					submissionId: subId,
					ajax: 1
				}).then(function () {
					window.location.reload();
				});
			});
		});

		var copyLinkBtns = root.querySelectorAll('.mjcp-copy-link-btn');
		copyLinkBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var url = btn.getAttribute('data-url') || '';
				if (!url) {
					return;
				}
				var origLabel = btn.textContent;
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(url).then(function () {
						btn.textContent = copiedText;
						setTimeout(function () {
							btn.textContent = origLabel;
						}, 1500);
					});
				} else {
					var ta = document.createElement('textarea');
					ta.value = url;
					document.body.appendChild(ta);
					ta.select();
					document.execCommand('copy');
					document.body.removeChild(ta);
					btn.textContent = copiedText;
					setTimeout(function () {
						btn.textContent = origLabel;
					}, 1500);
				}
			});
		});

		// 6. Toggle Testimonial Consent Withdrawal
		var toggleTestimonialBtns = root.querySelectorAll('.mjcp-toggle-testimonial-btn');
		toggleTestimonialBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!toggleTestimonialUrl) {
					return;
				}
				var tid = btn.getAttribute('data-testimonial-id');
				var withdraw = btn.getAttribute('data-withdraw');
				btn.disabled = true;
				postUrlEncoded(toggleTestimonialUrl, {
					csrfToken: csrfToken,
					testimonialId: tid,
					withdraw: withdraw,
					ajax: 1
				}).then(function () {
					window.location.reload();
				});
			});
		});

		// 7. Settings: Add / Delete Holiday
		var addHolidayBtn = document.getElementById('mjcpAddHolidayBtn');
		if (addHolidayBtn && addHolidayUrl) {
			addHolidayBtn.addEventListener('click', function () {
				var dateInput = document.getElementById('mjcpNewHolidayDate');
				var descInput = document.getElementById('mjcpNewHolidayDesc');
				if (!dateInput || !descInput || !dateInput.value || !descInput.value) {
					return;
				}
				addHolidayBtn.disabled = true;
				postUrlEncoded(addHolidayUrl, {
					csrfToken: csrfToken,
					holidayDate: dateInput.value,
					description: descInput.value,
					ajax: 1
				}).then(function (res) {
					if (res && res.ok) {
						window.location.reload();
					} else {
						addHolidayBtn.disabled = false;
					}
				});
			});
		}

		var deleteHolidayBtns = root.querySelectorAll('.mjcp-delete-holiday-btn');
		deleteHolidayBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!deleteHolidayUrl) {
					return;
				}
				var hid = btn.getAttribute('data-holiday-id');
				btn.disabled = true;
				postUrlEncoded(deleteHolidayUrl, {
					csrfToken: csrfToken,
					holidayId: hid,
					ajax: 1
				}).then(function () {
					window.location.reload();
				});
			});
		});

		// 8. Bulk Payment Import Modal (Preview & Commit supporting XLSX/CSV upload or pasted CSV)
		var bulkModal = document.getElementById('mjcpBulkImportModal');
		var openBulkBtn = document.getElementById('mjcpOpenBulkImportBtn');
		var closeBulkBtn = document.getElementById('mjcpCloseBulkImportBtn');
		var closeBulkBackdrop = document.getElementById('mjcpCloseBulkImportBackdrop');
		var previewBulkBtn = document.getElementById('mjcpPreviewBulkImportBtn');
		var commitBulkBtn = document.getElementById('mjcpCommitBulkImportBtn');
		var csvTextarea = document.getElementById('mjcpBulkImportCsvText');
		var fileInput = document.getElementById('mjcpBulkImportFile');
		var previewArea = document.getElementById('mjcpBulkImportPreviewArea');

		if (openBulkBtn && bulkModal) {
			openBulkBtn.addEventListener('click', function () {
				bulkModal.removeAttribute('hidden');
			});
		}
		if (closeBulkBtn && bulkModal) {
			closeBulkBtn.addEventListener('click', function () {
				bulkModal.setAttribute('hidden', 'hidden');
			});
		}
		if (closeBulkBackdrop && bulkModal) {
			closeBulkBackdrop.addEventListener('click', function () {
				bulkModal.setAttribute('hidden', 'hidden');
			});
		}

		function sendBulkImport(commitFlag) {
			var fd = new FormData();
			fd.append('csrfToken', csrfToken);
			fd.append('csvText', csvTextarea ? csvTextarea.value : '');
			fd.append('commit', commitFlag ? '1' : '0');
			fd.append('ajax', '1');
			if (fileInput && fileInput.files && fileInput.files.length > 0) {
				fd.append('importFile', fileInput.files[0]);
			}
			return fetch(importPaymentsUrl, {
				method: 'POST',
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				},
				body: fd,
				credentials: 'same-origin'
			}).then(function (r) {
				return r.json();
			});
		}

		if (previewBulkBtn && importPaymentsUrl) {
			previewBulkBtn.addEventListener('click', function () {
				sendBulkImport(false).then(function (res) {
					if (!res || !previewArea) {
						return;
					}
					var colCode = previewArea.getAttribute('data-col-code') || '';
					var colStatus = previewArea.getAttribute('data-col-status') || '';
					var colAmount = previewArea.getAttribute('data-col-amount') || '';
					var colNotes = previewArea.getAttribute('data-col-notes') || '';
					var colValidation = previewArea.getAttribute('data-col-validation') || '';
					var okBadgeText = previewArea.getAttribute('data-ok-badge') || '';

					var rows = res.previewRows || [];
					var html = '<table class="mjcp-table mjcp-table--compact"><thead><tr>'
						+ '<th>' + escapeHtml(colCode) + '</th>'
						+ '<th>' + escapeHtml(colStatus) + '</th>'
						+ '<th>' + escapeHtml(colAmount) + '</th>'
						+ '<th>' + escapeHtml(colNotes) + '</th>'
						+ '<th>' + escapeHtml(colValidation) + '</th>'
						+ '</tr></thead><tbody>';
					rows.forEach(function (r) {
						var badge = r.isValid
							? '<span class="mjcp-badge mjcp-badge-success">' + escapeHtml(okBadgeText) + '</span>'
							: '<span class="mjcp-badge mjcp-badge-danger">' + escapeHtml(r.errorText) + '</span>';
						html += '<tr>'
							+ '<td>' + escapeHtml(r.submissionId) + '</td>'
							+ '<td>' + escapeHtml(r.paymentStatusLabel || r.paymentStatus) + '</td>'
							+ '<td>' + escapeHtml(r.amount) + '</td>'
							+ '<td>' + escapeHtml(r.notes) + '</td>'
							+ '<td>' + badge + '</td>'
							+ '</tr>';
					});
					html += '</tbody></table>';
					previewArea.innerHTML = html;

					if (commitBulkBtn) {
						commitBulkBtn.disabled = Boolean(res.hasErrors || rows.length === 0);
					}
				});
			});
		}

		if (commitBulkBtn && importPaymentsUrl) {
			commitBulkBtn.addEventListener('click', function () {
				commitBulkBtn.disabled = true;
				sendBulkImport(true).then(function (res) {
					if (res && res.ok) {
						window.location.reload();
					} else {
						commitBulkBtn.disabled = false;
					}
				});
			});
		}
	}

	function postUrlEncoded(url, data) {
		var params = new URLSearchParams();
		Object.keys(data).forEach(function (k) {
			if (data[k] !== undefined && data[k] !== null) {
				params.append(k, String(data[k]));
			}
		});
		return fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With': 'XMLHttpRequest'
			},
			body: params.toString(),
			credentials: 'same-origin'
		}).then(function (r) {
			return r.json();
		});
	}

	function escapeHtml(str) {
		return String(str === null || str === undefined ? '' : str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initMultiJournalControlPanel);
	} else {
		initMultiJournalControlPanel();
	}
})();
